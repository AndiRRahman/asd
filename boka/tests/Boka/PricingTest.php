<?php

use App\Domain\BusinessRuleException;
use App\Domain\Cart\CartService;
use App\Domain\Checkout\PriceCalculator;
use App\Domain\Promotion\VoucherService;
use App\Models\FlashSale;
use App\Models\Voucher;
use Tests\Boka\Helpers;

function voucher(array $attributes): Voucher
{
    return Voucher::query()->create([
        'code' => 'V'.random_int(1000, 9999), 'type' => 'belanja', 'discount_type' => 'persen', 'value' => 10,
        'min_spend' => 0, 'per_user_limit' => 1, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), ...$attributes,
    ]);
}

function quoteFor($user, array $variants, ?string $shop = null, ?string $ship = null): array
{
    $cart = app(CartService::class);

    return app(PriceCalculator::class)->quote(
        $user, $cart->lines($cart->rawItems($user)), Helpers::address($user),
        Helpers::localShipping($variants), $shop, $ship,
    );
}

// K1: total pesanan selalu sama dengan T = (S − Dv) + (O − Do) + B + K
dataset('vouchers', [
    'persen tanpa batas' => [['discount_type' => 'persen', 'value' => 10], 100_000, 10_000],
    'persen dengan batas maksimal' => [['discount_type' => 'persen', 'value' => 50, 'max_discount' => 20_000], 100_000, 20_000],
    'persen dibulatkan ke bawah' => [['discount_type' => 'persen', 'value' => 15], 33_333, 4_999],
    'nominal' => [['discount_type' => 'nominal', 'value' => 25_000], 100_000, 25_000],
    'nominal tidak melebihi subtotal' => [['discount_type' => 'nominal', 'value' => 500_000], 100_000, 100_000],
]);

it('menghitung potongan voucher belanja sesuai aturan', function (array $attributes, int $base, int $expected) {
    expect(app(VoucherService::class)->discount(voucher($attributes), $base))->toBe($expected);
})->with('vouchers');

it('memenuhi rumus total dengan voucher belanja dan voucher ongkir', function () {
    $buyer = Helpers::buyer();
    $variant = Helpers::variant(price: 60_000);
    app(CartService::class)->add($buyer, [], $variant->id, 2);
    voucher(['code' => 'HEMAT10', 'discount_type' => 'persen', 'value' => 10]);
    voucher(['code' => 'ONGKIR5', 'type' => 'ongkir', 'discount_type' => 'nominal', 'value' => 5_000]);

    $quote = quoteFor($buyer, [$variant], 'HEMAT10', 'ONGKIR5');

    expect($quote['subtotal'])->toBe(120_000)
        ->and($quote['voucher_discount'])->toBe(12_000)
        ->and($quote['shipping_cost'])->toBe(10_000)
        ->and($quote['shipping_discount'])->toBe(5_000)
        ->and($quote['grand_total'])->toBe((120_000 - 12_000) + (10_000 - 5_000) + 0 + 0);
});

it('voucher ongkir tidak pernah melebihi total ongkir (ongkir gratis)', function () {
    $buyer = Helpers::buyer();
    $variant = Helpers::variant(price: 60_000);
    app(CartService::class)->add($buyer, [], $variant->id, 1);
    voucher(['code' => 'GRATIS', 'type' => 'ongkir', 'discount_type' => 'nominal', 'value' => 99_000]);

    $quote = quoteFor($buyer, [$variant], null, 'GRATIS');

    expect($quote['shipping_discount'])->toBe($quote['shipping_cost'])->and($quote['grand_total'])->toBe(60_000);
});

it('menolak voucher di bawah minimal belanja dan voucher kedaluwarsa', function () {
    $buyer = Helpers::buyer();
    $variant = Helpers::variant(price: 10_000);
    app(CartService::class)->add($buyer, [], $variant->id, 1);
    voucher(['code' => 'MIN50', 'min_spend' => 50_000]);
    voucher(['code' => 'LAMA', 'ends_at' => now()->subMinute()]);

    expect(quoteFor($buyer, [$variant], 'MIN50')['voucher_errors'])->toHaveKey('belanja');
    expect(quoteFor($buyer, [$variant], 'LAMA')['voucher_errors'])->toHaveKey('belanja');
});

it('memakai harga flash sale lebih dulu daripada harga normal', function () {
    $buyer = Helpers::buyer();
    $variant = Helpers::variant(price: 50_000);
    FlashSale::query()->create(['name' => 'Uji', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()])
        ->items()->create(['variant_id' => $variant->id, 'sale_price' => 40_000, 'quota' => 5]);
    app(CartService::class)->add($buyer, [], $variant->id, 1);

    $quote = quoteFor($buyer, [$variant]);

    expect($quote['subtotal'])->toBe(40_000)->and($quote['has_flash_sale'])->toBeTrue();
});

// K15: keranjang dari dua titik asal menjadi dua paket; O = jumlah ongkir kedua paket.
it('memecah pesanan dari dua titik asal menjadi dua paket', function () {
    $buyer = Helpers::buyer();
    $a = Helpers::variant();
    $b = Helpers::variant();
    $cart = app(CartService::class);
    $cart->add($buyer, [], $a->id, 1);
    $cart->add($buyer, [], $b->id, 1);

    $quote = quoteFor($buyer, [$a, $b]);

    expect($quote['packages'])->toHaveCount(2)
        ->and($quote['shipping_cost'])->toBe(collect($quote['packages'])->sum(fn ($p) => $p['selected']['cost']));
});

it('menolak kode voucher yang tidak dikenal saat pesanan dibuat', function () {
    $buyer = Helpers::buyer();
    $variant = Helpers::variant();
    app(CartService::class)->add($buyer, [], $variant->id, 1);
    $cart = app(CartService::class);

    app(PriceCalculator::class)->quote($buyer, $cart->lines($cart->rawItems($buyer)), Helpers::address($buyer), Helpers::localShipping([$variant]), 'TIDAKADA', strict: true);
})->throws(BusinessRuleException::class);
