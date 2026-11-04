<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Order\OrderStatus;
use App\Domain\Payment\PaymentService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\Setting;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Layar verifikasi: bukti dan tagihan berdampingan, satu keputusan dalam satu layar (bagian 12.5).
 */
class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function index(): Response
    {
        return Inertia::render('panel/payments/index', [
            'orders' => Order::query()->where('status', OrderStatus::MenungguVerifikasi)
                ->with(['user:id,name', 'latestProof'])->get()
                ->sortBy(fn ($o) => $o->latestProof?->created_at)->values()
                ->map(fn (Order $o) => [
                    'code' => $o->code, 'buyer' => $o->user->name, 'grand_total' => $o->grand_total,
                    'bank' => $o->bank_snapshot['bank_name'] ?? null, 'sender' => $o->latestProof?->sender_name,
                    'waiting_since' => $o->latestProof?->created_at?->toIso8601String(),
                    'stage' => $o->latestProof?->status,
                ]),
        ]);
    }

    public function show(Request $request, string $code): Response
    {
        $order = Order::query()->where('code', $code)->with(['user', 'latestProof.reviewer'])->firstOrFail();
        $proof = $order->latestProof;
        $user = $request->user();

        return Inertia::render('panel/payments/show', [
            'order' => Present::orderDetail($order, forBuyer: false),
            'buyer' => [
                'name' => $order->user->name,
                'email' => $order->user->email,
                'phone' => $user->can('ekspor.data_pribadi') ? $order->user->phone : Present::maskPhone($order->user->phone),
                'orders' => $order->user->orders()->whereKeyNot($order->id)->limit(8)->get()->map(fn ($o) => Present::orderSummary($o)),
                'risk' => CustomerController::riskSignals($order->user),
            ],
            'proof' => $proof ? [
                'id' => $proof->hashid,
                'url' => URL::temporarySignedRoute('panel.proofs.file', now()->addMinutes(10), ['proof' => $proof]),
                'is_pdf' => str_ends_with($proof->file_path, '.pdf'),
                'sender_bank' => $proof->sender_bank,
                'sender_name' => $proof->sender_name,
                'transferred_at' => $proof->transferred_at?->toDateString(),
                'status' => $proof->status,
                'first_approver' => $proof->status === 'disetujui_1' ? $proof->reviewer?->name : null,
                'first_approver_is_me' => $proof->status === 'disetujui_1' && $proof->reviewed_by === $user->id,
                'mutation_datetime' => $proof->mutation_datetime?->timezone('Asia/Makassar')->format('Y-m-d\TH:i'),
                'duplicates' => $this->payments->duplicateOrders($proof),
            ] : null,
            'templates' => PaymentService::REJECT_TEMPLATES,
            'threshold' => (int) Setting::get('second_approval_threshold'),
            'canVerify' => $user->can('pembayaran.verifikasi'),
        ]);
    }

    public function approve(Request $request, string $code): RedirectResponse
    {
        $data = $request->validate([
            'mutation_checked' => ['accepted'],
            'mutation_datetime' => ['required', 'date', 'before_or_equal:now'],
        ]);

        $result = $this->payments->approve(Order::query()->where('code', $code)->firstOrFail(), $request->user(), true, $data['mutation_datetime']);

        Inertia::flash('toast', ['type' => 'success', 'message' => $result === 'disetujui'
            ? "Pembayaran {$code} disetujui. Pesanan masuk antrean kirim."
            : "Persetujuan pertama tercatat. Pesanan {$code} menunggu persetujuan kedua dari Admin Transaksi lain."]);

        return redirect('/panel/pembayaran');
    }

    public function reject(Request $request, string $code): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->payments->reject(Order::query()->where('code', $code)->firstOrFail(), $request->user(), $data['reason']);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Bukti {$code} ditolak. Pembeli mendapat email berisi alasan."]);

        return redirect('/panel/pembayaran');
    }

    /** Bukti transfer di disk privat, dibuka lewat URL bertanda tangan 10 menit, hanya untuk Admin (bagian 11). */
    public function file(Request $request, PaymentProof $proof): StreamedResponse
    {
        abort_unless($request->hasValidSignature() && $request->user()->can('pesanan.kelola'), 403);

        return Storage::disk('local')->response($proof->file_path, headers: ['Cache-Control' => 'private, no-store']);
    }
}
