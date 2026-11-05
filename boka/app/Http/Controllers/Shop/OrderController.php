<?php

namespace App\Http\Controllers\Shop;

use App\Domain\BusinessRuleException;
use App\Domain\Order\OrderService;
use App\Domain\Order\OrderStatus;
use App\Domain\Payment\PaymentService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Support\HashId;
use App\Support\Present;
use App\Support\Qr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $orders = $request->user()->orders()
            ->when(in_array($status, array_column(OrderStatus::cases(), 'value'), true), fn ($q) => $q->where('status', $status))
            ->with('items')
            ->paginate(10)
            ->withQueryString();

        $orders->getCollection()->each(fn (Order $o) => $this->orders->expireIfDue($o));

        return Inertia::render('shop/orders/index', [
            'orders' => ['data' => $orders->getCollection()->map(fn ($o) => Present::orderSummary($o->refresh())), 'current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage()],
            'status' => $status,
        ]);
    }

    public function show(Request $request, string $code): Response
    {
        $order = $this->find($request, $code);
        $this->orders->expireIfDue($order);

        return Inertia::render('shop/orders/show', [
            'order' => Present::orderDetail($order->refresh()),
            'pickupQr' => collect(Present::orderDetail($order)['shipments'])
                ->filter(fn ($s) => $s['pickup_code'])
                ->mapWithKeys(fn ($s) => [$s['id'] => Qr::svg($order->code.':'.$s['pickup_code'])]),
        ]);
    }

    public function proof(Request $request, string $code, PaymentService $payments): RedirectResponse
    {
        $order = $this->find($request, $code);
        $data = $request->validate([
            // Tipe diperiksa dari isi file, bukan dari ekstensi (bagian 11).
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'sender_bank' => ['required', 'string', 'max:60'],
            'sender_name' => ['required', 'string', 'max:120'],
            'transferred_at' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $payments->submitProof($order, $request->user() ?: $order->user, $data['file'], $data['sender_bank'], $data['sender_name'], $data['transferred_at']);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('order.proof_uploaded')]);

        return back();
    }

    public function cancel(Request $request, string $code): RedirectResponse
    {
        $this->orders->cancelByBuyer($this->find($request, $code), $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('order.cancelled')]);

        return back();
    }

    public function complete(Request $request, string $code): RedirectResponse
    {
        $order = $this->find($request, $code);

        if ($order->status !== OrderStatus::Dikirim) {
            throw new BusinessRuleException('Pesanan belum dikirim.', 'invalid_transition', 409);
        }

        $this->orders->complete($order, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('order.completed')]);

        return back();
    }

    public function review(Request $request, string $code): RedirectResponse
    {
        $order = $this->find($request, $code);
        $request->merge(['order_item_id' => HashId::decode($request->input('order_item_id'))]);
        $data = $request->validate([
            'order_item_id' => ['required', 'integer', Rule::exists('order_items', 'id')->where('order_id', $order->id)],
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($order->status !== OrderStatus::Selesai) {
            throw new BusinessRuleException('Ulasan hanya untuk pesanan berstatus Selesai.', 'review_not_allowed', 403);
        }

        $item = OrderItem::query()->findOrFail($data['order_item_id']);

        if ($item->review()->exists()) {
            throw new BusinessRuleException('Produk ini sudah Anda ulas.', 'review_exists', 409);
        }

        DB::transaction(function () use ($item, $request, $data) {
            Review::query()->create([
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'user_id' => $request->user()->id,
                'rating' => $data['rating'],
                'body' => $data['body'] ?? null,
                'photos' => collect($request->file('photos', []))->map(fn ($f) => $f->store('ulasan', 'public'))->all(),
            ]);

            $stats = Review::query()->where('product_id', $item->product_id)->where('is_hidden', false)->selectRaw('avg(rating) as a, count(*) as c')->first();
            DB::table('products')->where('id', $item->product_id)->update(['rating_avg' => round((float) $stats->a, 2), 'rating_count' => $stats->c]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('order.reviewed')]);

        return back();
    }

    /** Invoice siap cetak dengan QR menuju halaman verifikasi publik (11.1 T14). */
    public function invoice(Request $request, string $code): View
    {
        $order = $this->find($request, $code)->load(['items', 'shipments']);

        return view('invoice', [
            'order' => $order,
            'qr' => Qr::svg(url('/invoice/verifikasi/'.$order->code)),
        ]);
    }

    /** Pesanan orang lain menghasilkan 404, bukan 403 (bagian 11, K6). */
    private function find(Request $request, string $code): Order
    {
        $user = $request->user();
        if ($user) {
            $order = $user->orders()->where('code', $code)->first();
            if ($order) {
                return $order;
            }
        }

        $sessionOrders = (array) $request->session()->get('guest_orders', []);
        if (in_array($code, $sessionOrders, true)) {
            return Order::query()->where('code', $code)->firstOrFail();
        }

        abort(404);
    }
}
