<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\BusinessRuleException;
use App\Domain\Cart\CartService;
use App\Domain\Checkout\CreateOrder;
use App\Domain\Checkout\PriceCalculator;
use App\Domain\Order\OrderService;
use App\Domain\Order\OrderStatus;
use App\Domain\Payment\PaymentService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\CheckoutController;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Support\HashId;
use App\Support\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Keranjang, checkout, dan pesanan untuk aplikasi mobile. Aturan bisnis sama persis dengan web.
 */
class OrderController extends Controller
{
    public function __construct(private CartService $cart, private OrderService $orders) {}

    public function cart(Request $request): JsonResponse
    {
        $lines = $this->cart->lines($this->cart->rawItems($request->user()));

        return response()->json(['items' => Present::cartLines($lines), 'subtotal' => $lines->sum(fn ($l) => $l['unit_price'] * $l['qty'])]);
    }

    public function addToCart(Request $request): JsonResponse
    {
        $data = $request->validate(['variant_id' => ['required', 'string'], 'qty' => ['required', 'integer', 'min:1']]);
        $this->cart->add($request->user(), [], HashId::decodeOrFail($data['variant_id']), $data['qty']);

        return $this->cart($request)->setStatusCode(201);
    }

    public function updateCart(Request $request, string $variant): JsonResponse
    {
        $this->cart->update($request->user(), [], HashId::decodeOrFail($variant), (int) $request->validate(['qty' => ['required', 'integer', 'min:0']])['qty']);

        return $this->cart($request);
    }

    public function removeFromCart(Request $request, string $variant): JsonResponse
    {
        $this->cart->remove($request->user(), [], HashId::decodeOrFail($variant));

        return $this->cart($request);
    }

    /** Rincian biaya tanpa membuat pesanan, termasuk paket per titik asal dan pilihan kirimnya. */
    public function quote(Request $request, PriceCalculator $calculator): JsonResponse
    {
        $data = CheckoutController::decodeIds($request->validate([
            'address_id' => ['nullable', 'string'], 'shipping' => ['array'],
            'voucher_code' => ['nullable', 'string'], 'shipping_voucher_code' => ['nullable', 'string'],
        ]));
        $user = $request->user();

        return response()->json(Present::quote($calculator->quote(
            $user, $this->cart->lines($this->cart->rawItems($user)),
            Address::query()->where('user_id', $user->id)->find($data['address_id'] ?? null),
            $data['shipping'] ?? [], $data['voucher_code'] ?? null, $data['shipping_voucher_code'] ?? null,
        )));
    }

    /** Wajib header Idempotency-Key, karena jaringan seluler sering mengirim ulang permintaan (K14). */
    public function store(Request $request, CreateOrder $create): JsonResponse
    {
        $key = $request->header('Idempotency-Key');

        if (! $key || strlen($key) > 64) {
            throw new BusinessRuleException('Header Idempotency-Key wajib diisi (maksimal 64 karakter).', 'idempotency_key_required', 400);
        }

        $data = CheckoutController::decodeIds($request->validate([
            'address_id' => ['required', 'string'], 'shipping' => ['required', 'array'], 'bank_account_id' => ['required', 'string'],
            'voucher_code' => ['nullable', 'string'], 'shipping_voucher_code' => ['nullable', 'string'],
        ]));

        $order = $create->handle($request->user(), $data, $key);

        return response()->json(['data' => Present::orderDetail($order->refresh())], $order->wasRecentlyCreated ? 201 : 200);
    }

    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()->orders()->with('items')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->cursorPaginate(20);

        return response()->json(['data' => collect($orders->items())->map(fn ($o) => Present::orderSummary($o)), 'next_cursor' => $orders->nextCursor()?->encode()]);
    }

    public function show(Request $request, string $code): JsonResponse
    {
        $order = $this->find($request, $code);
        $this->orders->expireIfDue($order);

        return response()->json(['data' => Present::orderDetail($order->refresh())]);
    }

    public function proof(Request $request, string $code, PaymentService $payments): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'sender_bank' => ['required', 'string', 'max:60'], 'sender_name' => ['required', 'string', 'max:120'],
            'transferred_at' => ['required', 'date', 'before_or_equal:today'],
        ]);
        $order = $this->find($request, $code);
        $payments->submitProof($order, $request->user(), $data['file'], $data['sender_bank'], $data['sender_name'], $data['transferred_at']);

        return response()->json(['data' => Present::orderDetail($order->refresh())]);
    }

    public function cancel(Request $request, string $code): JsonResponse
    {
        $order = $this->find($request, $code);
        $this->orders->cancelByBuyer($order, $request->user());

        return response()->json(['data' => Present::orderDetail($order->refresh())]);
    }

    public function complete(Request $request, string $code): JsonResponse
    {
        $order = $this->find($request, $code);

        if ($order->status !== OrderStatus::Dikirim) {
            throw new BusinessRuleException('Pesanan belum dikirim.', 'invalid_transition', 409);
        }

        $this->orders->complete($order, $request->user());

        return response()->json(['data' => Present::orderDetail($order->refresh())]);
    }

    public function review(Request $request, string $id): JsonResponse
    {
        $item = OrderItem::query()->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->id))->findOrFail(HashId::decodeOrFail($id));
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'body' => ['nullable', 'string', 'max:1000']]);

        if ($item->order->status !== OrderStatus::Selesai || $item->review()->exists()) {
            throw new BusinessRuleException('Ulasan hanya sekali untuk pesanan berstatus Selesai.', 'review_not_allowed', 409);
        }

        $review = DB::transaction(function () use ($item, $request, $data) {
            $review = Review::query()->create(['order_item_id' => $item->id, 'product_id' => $item->product_id, 'user_id' => $request->user()->id, ...$data]);
            $stats = Review::query()->where('product_id', $item->product_id)->where('is_hidden', false)->selectRaw('avg(rating) as a, count(*) as c')->first();
            DB::table('products')->where('id', $item->product_id)->update(['rating_avg' => round((float) $stats->a, 2), 'rating_count' => $stats->c]);

            return $review;
        });

        return response()->json(['data' => Present::review($review)], 201);
    }

    /** Pesanan orang lain menghasilkan 404 (K6). */
    private function find(Request $request, string $code): Order
    {
        return $request->user()->orders()->where('code', $code)->firstOrFail();
    }
}
