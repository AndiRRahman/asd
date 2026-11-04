<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Order\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\PaymentProof;
use App\Models\Review;
use App\Models\User;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pelanggan, sinyal risiko (11.1 T13), dan moderasi ulasan.
 */
class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $canSeePersonal = $request->user()->can('ekspor.data_pribadi');
        $customers = User::query()->role('pembeli')
            ->withCount('orders')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->latest('id')->paginate(25)->withQueryString();

        return Inertia::render('panel/customers', [
            'customers' => [
                'data' => $customers->getCollection()->map(fn (User $u) => [
                    'id' => $u->hashid, 'name' => $u->name, 'email' => $u->email,
                    'phone' => $canSeePersonal ? $u->phone : Present::maskPhone($u->phone),
                    'status' => $u->status, 'orders' => $u->orders_count,
                    'blocked_until' => $u->checkout_blocked_until?->isFuture() ? $u->checkout_blocked_until->toIso8601String() : null,
                    'risk' => self::riskSignals($u),
                    'joined' => $u->created_at->toIso8601String(),
                ]),
                'current_page' => $customers->currentPage(), 'last_page' => $customers->lastPage(),
            ],
            'q' => $request->query('q'),
        ]);
    }

    public function toggle(User $user): RedirectResponse
    {
        abort_if($user->isStaff(), 403);
        $user->update(['status' => $user->status === 'aktif' ? 'nonaktif' : 'aktif']);
        $user->tokens()->delete();

        return back();
    }

    public function blockCheckout(Request $request, User $user): RedirectResponse
    {
        $request->validate(['block' => ['required', 'boolean']]);
        $user->forceFill(['checkout_blocked_until' => $request->boolean('block') ? now()->addDays(30) : null])->save();
        activity()->performedOn($user)->causedBy($request->user())->log($request->boolean('block') ? 'Blokir checkout' : 'Buka blokir checkout');

        return back();
    }

    /** Panel sinyal risiko berbasis aturan, bukan AI (T13). */
    public static function riskSignals(User $user): array
    {
        $proofs = PaymentProof::query()->whereHas('order', fn ($q) => $q->where('user_id', $user->id));
        $hashes = (clone $proofs)->pluck('file_sha256');
        $sharedAddress = Address::query()->whereIn('full_address', $user->addresses()->pluck('full_address'))->where('user_id', '!=', $user->id)->distinct('user_id')->count('user_id');

        $signals = [
            'expired' => $user->orders()->where('status', OrderStatus::Kedaluwarsa)->count(),
            'rejected_proofs' => (clone $proofs)->where('status', 'ditolak')->count(),
            'duplicate_proofs' => PaymentProof::query()->whereIn('file_sha256', $hashes)->select('file_sha256')->groupBy('file_sha256')->havingRaw('count(distinct order_id) > 1')->get()->count(),
            'shared_address_accounts' => $sharedAddress,
        ];

        return [...$signals, 'level' => match (true) {
            $signals['duplicate_proofs'] > 0 || $signals['expired'] >= 3 => 'tinggi',
            array_sum($signals) > 0 => 'sedang',
            default => 'rendah',
        }];
    }

    public function reviews(Request $request): Response
    {
        $reviews = Review::query()->with(['user:id,name', 'product'])->latest('id')->paginate(25);

        return Inertia::render('panel/reviews', [
            'reviews' => [
                'data' => $reviews->getCollection()->map(fn (Review $r) => [...Present::review($r), 'is_hidden' => $r->is_hidden, 'author' => $r->user?->name]),
                'current_page' => $reviews->currentPage(), 'last_page' => $reviews->lastPage(),
            ],
        ]);
    }

    public function moderate(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate(['is_hidden' => ['nullable', 'boolean'], 'reply' => ['nullable', 'string', 'max:1000']]);
        $review->update(array_filter($data, fn ($v) => $v !== null));

        $stats = Review::query()->where('product_id', $review->product_id)->where('is_hidden', false)->selectRaw('avg(rating) as a, count(*) as c')->first();
        DB::table('products')->where('id', $review->product_id)->update(['rating_avg' => round((float) $stats->a, 2), 'rating_count' => $stats->c]);

        return back();
    }
}
