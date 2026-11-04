<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Mail\AdminInvitationMail;
use App\Models\AdminInvitation;
use App\Models\BankAccount;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\BankAccountChanged;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Spatie\Activitylog\Models\Activity;

/**
 * Modul khusus Super Admin: pengguna panel, pengaturan, rekening, log audit, sistem.
 */
class AdminController extends Controller
{
    public const EDITABLE_SETTINGS = [
        'payment_deadline_hours' => 'Batas waktu bayar (jam)',
        'flash_sale_deadline_hours' => 'Batas bayar pesanan flash sale (jam)',
        'reupload_deadline_hours' => 'Batas unggah ulang setelah ditolak (jam)',
        'max_pending_orders' => 'Maksimal pesanan menunggu pembayaran per akun',
        'expired_block_count' => 'Jumlah kedaluwarsa pemicu blokir',
        'checkout_block_hours' => 'Lama blokir checkout (jam)',
        'orders_per_hour' => 'Maksimal pesanan per jam per akun',
        'second_approval_threshold' => 'Ambang persetujuan kedua (Rp)',
        'service_fee' => 'Biaya layanan (Rp)',
        'packaging_weight_gram' => 'Berat kemasan per paket (gram)',
        'stock_inbound_max' => 'Batas stok masuk sekali input',
    ];

    public function users(): Response
    {
        return Inertia::render('panel/users', [
            'users' => User::query()->role(User::STAFF_ROLES)->with('roles')->get()->map(fn (User $u) => [
                'id' => $u->hashid, 'name' => $u->name, 'email' => $u->email, 'status' => $u->status,
                'role' => $u->roles->first()?->name, 'two_factor' => $u->two_factor_confirmed_at !== null,
                'last_login_at' => $u->last_login_at?->toIso8601String(),
            ]),
            'invitations' => AdminInvitation::query()->whereNull('accepted_at')->where('expires_at', '>', now())->get(['id', 'email', 'role', 'expires_at']),
        ]);
    }

    /** Undangan bertanda tangan, kedaluwarsa 48 jam. Admin mengatur password sendiri (bagian 2). */
    public function invite(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(User::STAFF_ROLES)],
        ]);

        $token = Str::random(48);
        $invitation = AdminInvitation::query()->create([...$data, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours(48), 'invited_by' => $request->user()->id]);
        $url = URL::temporarySignedRoute('invitation.show', $invitation->expires_at, ['invitation' => $invitation, 'token' => $token]);

        Mail::to($data['email'])->send(new AdminInvitationMail($url, $data['role']));
        activity()->causedBy($request->user())->withProperties($data)->log('Undang Admin');
        Inertia::flash('toast', ['type' => 'success', 'message' => "Undangan dikirim ke {$data['email']}. Tautan berlaku 48 jam."]);

        return back();
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['nullable', Rule::in(User::STAFF_ROLES)], 'status' => ['nullable', 'in:aktif,nonaktif'], 'reset_2fa' => ['nullable', 'boolean']]);
        abort_if($user->is($request->user()) && ($data['status'] ?? null) === 'nonaktif', 422, 'Anda tidak bisa menonaktifkan akun sendiri.');

        if ($data['role'] ?? null) {
            $user->syncRoles([$data['role']]);
        }

        if ($data['status'] ?? null) {
            $user->update(['status' => $data['status']]);
        }

        if ($data['reset_2fa'] ?? false) {
            $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        }

        activity()->performedOn($user)->causedBy($request->user())->withProperties($data)->log('Ubah pengguna panel');

        return back();
    }

    public function settings(): Response
    {
        return Inertia::render('panel/settings', [
            'twoFactor' => config('boka.two_factor'),
            'settings' => collect(self::EDITABLE_SETTINGS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'value' => Setting::get($key)])->values(),
            'banks' => BankAccount::query()->latest('id')->get()->map(fn (BankAccount $b) => [
                'id' => $b->hashid, 'bank_name' => $b->bank_name, 'account_number' => $b->account_number, 'account_holder' => $b->account_holder,
                'is_active' => $b->is_active, 'active_from' => $b->active_from->toIso8601String(), 'waiting' => $b->active_from->isFuture(),
            ]),
            'synonyms' => collect(Setting::get('synonyms') ?? [])->map(fn ($g) => implode(', ', $g))->implode("\n"),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['required', 'integer', 'min:0'],
            'synonyms' => ['nullable', 'string', 'max:5000'],
        ]);

        foreach (array_intersect_key($data['settings'], self::EDITABLE_SETTINGS) as $key => $value) {
            Setting::put($key, (int) $value);
        }

        Setting::put('synonyms', collect(preg_split('/\r?\n/', (string) ($data['synonyms'] ?? '')))
            ->map(fn ($line) => collect(explode(',', $line))->map(fn ($w) => Str::lower(trim($w)))->filter()->values()->all())
            ->filter(fn ($g) => count($g) > 1)->values()->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengaturan tersimpan.']);

        return back();
    }

    /**
     * Rekening tujuan hanya bisa diubah Super Admin dengan password dan kode 2FA, semua Super Admin
     * menerima email, dan rekening baru baru tampil di checkout setelah 24 jam (11.1 T4, K28).
     */
    public function storeBank(Request $request, TwoFactorAuthenticationProvider $twoFactor): RedirectResponse
    {
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:60'], 'account_number' => ['required', 'string', 'max:40'],
            'account_holder' => ['required', 'string', 'max:120'], 'password' => ['required', 'current_password'],
            'code' => [config('boka.two_factor') ? 'required' : 'nullable', 'string'],
        ]);

        $this->verifyTwoFactor($request, $twoFactor, $data['code'] ?? null);

        $bank = BankAccount::query()->create([...collect($data)->only(['bank_name', 'account_number', 'account_holder'])->all(), 'active_from' => now()->addHours(24)]);
        Notification::send(User::role('super_admin')->get(), new BankAccountChanged($bank, $request->user(), 'ditambahkan'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rekening ditambahkan. Rekening ini tampil di checkout 24 jam lagi.']);

        return back();
    }

    public function toggleBank(Request $request, BankAccount $bank, TwoFactorAuthenticationProvider $twoFactor): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'current_password'], 'code' => [config('boka.two_factor') ? 'required' : 'nullable', 'string']]);
        $this->verifyTwoFactor($request, $twoFactor, $data['code'] ?? null);

        $bank->update(['is_active' => ! $bank->is_active, 'active_from' => $bank->is_active ? $bank->active_from : now()->addHours(24)]);
        Notification::send(User::role('super_admin')->get(), new BankAccountChanged($bank, $request->user(), $bank->is_active ? 'diaktifkan' : 'dinonaktifkan'));

        return back();
    }

    public function audit(Request $request): Response
    {
        $user = $request->user();
        $logs = Activity::query()
            ->with('causer')
            ->unless($user->can('audit.lihat_semua'), fn ($q) => $q->where('causer_id', $user->id))
            ->when($request->query('q'), fn ($q, $s) => $q->where('description', 'like', "%{$s}%"))
            ->latest('id')->paginate(40)->withQueryString();

        return Inertia::render('panel/audit', [
            'logs' => [
                'data' => $logs->getCollection()->map(fn (Activity $a) => [
                    'id' => $a->id, 'description' => $a->description, 'subject' => $a->subject_type ? class_basename($a->subject_type).' #'.$a->subject_id : null,
                    'causer' => $a->causer?->name ?? 'Sistem', 'changes' => $a->attribute_changes ?? $a->properties, 'at' => $a->created_at->toIso8601String(),
                ]),
                'current_page' => $logs->currentPage(), 'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    public function system(): Response
    {
        return Inertia::render('panel/system', [
            'maintenance' => app()->isDownForMaintenance(),
            'queue' => ['pending' => DB::table('jobs')->count(), 'failed' => DB::table('failed_jobs')->count()],
            'versions' => ['php' => PHP_VERSION, 'laravel' => app()->version()],
        ]);
    }

    public function clearCache(): RedirectResponse
    {
        Artisan::call('optimize:clear');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cache dibersihkan.']);

        return back();
    }

    private function verifyTwoFactor(Request $request, TwoFactorAuthenticationProvider $provider, ?string $code): void
    {
        $user = $request->user();

        if (! config('boka.two_factor')) {
            return;
        }

        if (! $user->two_factor_secret || ! $provider->verify(decrypt($user->two_factor_secret), (string) $code)) {
            throw ValidationException::withMessages(['code' => 'Kode 2FA salah.']);
        }
    }
}
