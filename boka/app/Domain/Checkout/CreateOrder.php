<?php

namespace App\Domain\Checkout;

use App\Domain\BusinessRuleException;
use App\Domain\Cart\CartService;
use App\Domain\Catalog\StockService;
use App\Domain\Order\OrderStatus;
use App\Domain\Promotion\VoucherService;
use App\Models\Address;
use App\Models\BankAccount;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\OrderUpdate;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Membuat pesanan: memotong stok saat checkout (ADR-5) dalam satu transaksi. Jika satu varian gagal,
 * seluruh pesanan dibatalkan; tidak ada pesanan sebagian (bagian 4.4 titik 5).
 */
class CreateOrder
{
    public function __construct(
        private CartService $cart,
        private PriceCalculator $calculator,
        private StockService $stock,
        private VoucherService $vouchers,
    ) {}

    /**
     * @param  array{address_id: int, shipping: array<int|string, string>, bank_account_id: int, voucher_code?: ?string, shipping_voucher_code?: ?string}  $input
     */
    public function handle(User $user, array $input, string $idempotencyKey): Order
    {
        $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            if ($existing->user_id !== $user->id) {
                throw new BusinessRuleException('Kunci idempotensi sudah dipakai.', 'idempotency_conflict', 409);
            }

            return $existing;
        }

        $this->guardHoarding($user);

        $address = Address::query()->where('user_id', $user->id)->findOrFail($input['address_id']);
        $bank = BankAccount::query()->usable()->find($input['bank_account_id'])
            ?? throw new BusinessRuleException('Rekening tujuan tidak tersedia. Pilih rekening lain.', 'bank_unavailable');

        $lines = $this->cart->lines($this->cart->rawItems($user));
        $quote = $this->calculator->quote(
            $user, $lines, $address, $input['shipping'],
            $input['voucher_code'] ?? null, $input['shipping_voucher_code'] ?? null,
            strict: true, uniqueCode: random_int(1, 999),
        );

        try {
            $order = DB::transaction(fn () => $this->persist($user, $address, $bank, $quote, $idempotencyKey));
        } catch (UniqueConstraintViolationException) {
            // Permintaan kedua dengan kunci yang sama datang bersamaan.
            return Order::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
        }

        $user->notify(new OrderUpdate($order, 'dibuat'));

        return $order;
    }

    private function persist(User $user, Address $address, BankAccount $bank, array $quote, string $key): Order
    {
        $hours = $quote['has_flash_sale'] ? Setting::get('flash_sale_deadline_hours') : Setting::get('payment_deadline_hours');

        $order = Order::query()->create([
            'code' => $this->generateCode(),
            'user_id' => $user->id,
            'status' => OrderStatus::MenungguPembayaran,
            'subtotal' => $quote['subtotal'],
            'voucher_discount' => $quote['voucher_discount'],
            'shipping_cost' => $quote['shipping_cost'],
            'shipping_discount' => $quote['shipping_discount'],
            'service_fee' => $quote['service_fee'],
            'unique_code' => $quote['unique_code'],
            'grand_total' => $quote['grand_total'],
            'payment_deadline_at' => now()->addHours((int) $hours),
            'bank_account_id' => $bank->id,
            'bank_snapshot' => $bank->snapshot(),
            'address_snapshot' => $address->snapshot(),
            'has_flash_sale' => $quote['has_flash_sale'],
            'idempotency_key' => $key,
        ]);

        $failed = [];
        $remainingShipDiscount = $quote['shipping_discount'];

        foreach ($quote['packages'] as $package) {
            $selected = $package['selected'];
            $discount = min($remainingShipDiscount, $selected['cost']);
            $remainingShipDiscount -= $discount;

            $shipment = $order->shipments()->create([
                'origin_id' => $package['origin']->id,
                'origin_snapshot' => $package['origin']->snapshot(),
                'method' => $selected['method'],
                'courier' => $selected['courier'],
                'service' => $selected['service'],
                'weight_gram' => $package['weight_gram'],
                'cost' => $selected['cost'],
                'shipping_discount' => $discount,
            ]);

            foreach ($package['lines'] as $line) {
                $variant = $line['variant'];

                try {
                    $this->stock->adjust($variant, 'checkout', -$line['qty'], $user, reference: $order->code);
                } catch (BusinessRuleException $e) {
                    $failed[] = $e->context ?: ['variant_id' => $variant->id, 'requested' => $line['qty'], 'available' => 0];

                    continue;
                }

                if ($line['flash_sale_item_id']) {
                    $ok = DB::table('flash_sale_items')
                        ->where('id', $line['flash_sale_item_id'])
                        ->whereRaw('sold + ? <= quota', [$line['qty']])
                        ->increment('sold', $line['qty']);

                    if ($ok === 0) {
                        $failed[] = ['variant_id' => $variant->id, 'requested' => $line['qty'], 'available' => 0, 'reason' => 'flash_sale_quota'];
                    }
                }

                $order->items()->create([
                    'shipment_id' => $shipment->id,
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => $variant->product->getTranslation('name', 'id'),
                    'variant_name' => $variant->label(),
                    'unit_price' => $line['unit_price'],
                    'qty' => $line['qty'],
                    'weight_gram' => $variant->weight_gram,
                    'flash_sale_item_id' => $line['flash_sale_item_id'],
                ]);
            }
        }

        if ($failed !== []) {
            // Lempar agar transaksi di-rollback: stok varian lain yang sempat terpotong ikut kembali.
            throw new BusinessRuleException('Stok tidak mencukupi untuk sebagian item.', 'insufficient_stock', 409, ['items' => $failed]);
        }

        foreach (['shop_voucher', 'shipping_voucher'] as $slot) {
            if ($quote[$slot]) {
                $this->vouchers->reserve($quote[$slot], $user, $order);
            }
        }

        $order->histories()->create(['to_status' => OrderStatus::MenungguPembayaran->value, 'actor_id' => $user->id, 'note' => 'Pesanan dibuat']);
        $this->cart->clear($user);

        return $order;
    }

    /** Aturan anti-penimbunan stok (bagian 4.5). */
    private function guardHoarding(User $user): void
    {
        if ($user->checkout_blocked_until?->isFuture()) {
            throw new BusinessRuleException(
                'Checkout Anda diblokir sementara sampai '.$user->checkout_blocked_until->timezone('Asia/Makassar')->format('d M Y H:i').' WITA karena beberapa pesanan kedaluwarsa.',
                'checkout_blocked', 423,
            );
        }

        $pending = Order::query()->where('user_id', $user->id)->where('status', OrderStatus::MenungguPembayaran)->count();

        if ($pending >= (int) Setting::get('max_pending_orders')) {
            throw new BusinessRuleException('Selesaikan pembayaran pesanan Anda yang lain dulu. Maksimal '.Setting::get('max_pending_orders').' pesanan menunggu pembayaran.', 'too_many_pending', 429);
        }

        $lastHour = Order::query()->where('user_id', $user->id)->where('created_at', '>=', now()->subHour())->count();

        if ($lastHour >= (int) Setting::get('orders_per_hour')) {
            throw new BusinessRuleException('Terlalu banyak pesanan dalam satu jam. Coba lagi nanti.', 'rate_limited', 429);
        }
    }

    /** Contoh: BN-261102-7K3Q9. Acak, bukan angka berurutan. */
    private function generateCode(): string
    {
        do {
            // Tanpa huruf/angka yang mudah tertukar saat dibacakan (0/O, 1/I/L).
            $random = collect(range(1, 5))->map(fn () => '23456789ABCDEFGHJKMNPQRSTUVWXYZ'[random_int(0, 30)])->implode('');
            $code = 'BN-'.now('Asia/Makassar')->format('ymd').'-'.$random;
        } while (Order::query()->where('code', $code)->exists());

        return $code;
    }
}
