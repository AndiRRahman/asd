<?php

namespace App\Domain\Checkout;

use App\Domain\BusinessRuleException;
use App\Domain\Promotion\VoucherService;
use App\Domain\Shipping\ShippingQuoter;
use App\Models\Address;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Satu-satunya tempat menghitung total (bagian 6). Dipakai checkout web, API mobile, dan pembuatan pesanan.
 *
 * T = (S − Dv) + (O − Do) + B + K
 */
class PriceCalculator
{
    public function __construct(
        private ShippingQuoter $shipping,
        private VoucherService $vouchers,
    ) {}

    /**
     * @param  Collection<int, array>  $lines  dari CartService::lines()
     * @param  array<int|string, string>  $shippingChoices  origin_id => kunci opsi kirim
     * @param  bool  $strict  true saat membuat pesanan: semua paket wajib punya pilihan dan voucher wajib sah
     */
    public function quote(
        User $user,
        Collection $lines,
        ?Address $address,
        array $shippingChoices = [],
        ?string $shopVoucher = null,
        ?string $shippingVoucher = null,
        bool $strict = false,
        int $uniqueCode = 0,
    ): array {
        if ($lines->isEmpty()) {
            throw new BusinessRuleException('Keranjang Anda kosong.', 'cart_empty');
        }

        $problems = $lines->whereNotNull('issue');

        if ($problems->isNotEmpty()) {
            throw new BusinessRuleException('Stok tidak mencukupi untuk sebagian item.', 'insufficient_stock', 409, [
                'items' => $problems->map(fn ($l) => ['variant_id' => $l['variant']->id, 'requested' => $l['qty'], 'available' => $l['available']])->values()->all(),
            ]);
        }

        $subtotal = (int) $lines->sum(fn ($l) => $l['unit_price'] * $l['qty']);
        $packages = [];
        $shippingCost = 0;
        $expeditionUnavailable = false;

        foreach ($this->shipping->packages($lines) as $index => $package) {
            $origin = $package['origin'];
            $quote = $address
                ? $this->shipping->options($origin, $package['weight_gram'], ['destination_id' => $address->destination_id, 'district' => $address->district])
                : ['options' => [], 'expedition_unavailable' => false];
            $expeditionUnavailable = $expeditionUnavailable || $quote['expedition_unavailable'];

            $choiceKey = $shippingChoices[$origin->id] ?? null;
            $selected = null;

            if ($strict || $choiceKey !== null) {
                try {
                    $selected = $this->shipping->pick($quote['options'], $choiceKey);
                } catch (BusinessRuleException $e) {
                    if ($strict) {
                        throw $e;
                    }
                }
            }

            $shippingCost += $selected['cost'] ?? 0;
            $packages[] = [
                'number' => $index + 1,
                'origin' => $origin,
                'lines' => $package['lines'],
                'weight_gram' => $package['weight_gram'],
                'options' => $quote['options'],
                'selected' => $selected,
            ];
        }

        [$shopDiscount, $shopVoucherModel, $shopError] = $this->applyVoucher($shopVoucher, 'belanja', $user, $lines, $subtotal, $subtotal, $strict);
        [$shipDiscount, $shipVoucherModel, $shipError] = $this->applyVoucher($shippingVoucher, 'ongkir', $user, $lines, $subtotal, $shippingCost, $strict);

        $serviceFee = (int) Setting::get('service_fee');
        $total = ($subtotal - $shopDiscount) + ($shippingCost - $shipDiscount) + $serviceFee + $uniqueCode;

        return [
            'packages' => $packages,
            'subtotal' => $subtotal,
            'voucher_discount' => $shopDiscount,
            'shipping_cost' => $shippingCost,
            'shipping_discount' => $shipDiscount,
            'service_fee' => $serviceFee,
            'unique_code' => $uniqueCode,
            'grand_total' => $total,
            'shop_voucher' => $shopVoucherModel,
            'shipping_voucher' => $shipVoucherModel,
            'voucher_errors' => array_filter(['belanja' => $shopError, 'ongkir' => $shipError]),
            'expedition_unavailable' => $expeditionUnavailable,
            'has_flash_sale' => $lines->contains(fn ($l) => $l['flash_sale_item_id'] !== null),
        ];
    }

    private function applyVoucher(?string $code, string $type, User $user, Collection $lines, int $subtotal, int $base, bool $strict): array
    {
        if (blank($code)) {
            return [0, null, null];
        }

        try {
            $result = $this->vouchers->evaluate($code, $type, $user, $lines, $subtotal, $base);

            return [$result['discount'], $result['voucher'], null];
        } catch (BusinessRuleException $e) {
            if ($strict) {
                throw $e;
            }

            return [0, null, $e->getMessage()];
        }
    }
}
