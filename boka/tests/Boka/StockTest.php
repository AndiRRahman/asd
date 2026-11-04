<?php

use App\Domain\BusinessRuleException;
use App\Domain\Cart\CartService;
use App\Domain\Catalog\StockService;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\Wishlist;
use App\Notifications\BackInStock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Tests\Boka\Helpers;

// K21: pengurangan stok berlebih ditolak; stok tetap, tidak ada mutasi tercatat.
it('menolak pengurangan melebihi stok', function () {
    $admin = Helpers::staff('admin');
    $variant = Helpers::variant(stock: 12);

    expect(fn () => app(StockService::class)->adjust($variant, 'keluar', -13, $admin, 'rusak'))
        ->toThrow(BusinessRuleException::class, 'Stok saat ini 12, pengurangan maksimal 12');

    expect($variant->fresh()->stock)->toBe(12)
        ->and(StockMovement::query()->where('variant_id', $variant->id)->count())->toBe(0);
});

it('mewajibkan alasan untuk penyesuaian keluar', function () {
    app(StockService::class)->adjust(Helpers::variant(), 'keluar', -1, Helpers::staff('admin'));
})->throws(BusinessRuleException::class, 'alasan');

// K22: stok masuk tidak wajar ditahan sampai dikonfirmasi password.
it('menahan stok masuk 1.500 unit sampai dikonfirmasi', function () {
    $admin = Helpers::staff('admin');
    $variant = Helpers::variant(stock: 0);
    $stock = app(StockService::class);

    expect(fn () => $stock->adjust($variant, 'masuk', 1500, $admin))->toThrow(BusinessRuleException::class);
    expect($variant->fresh()->stock)->toBe(0);

    $stock->adjust($variant, 'masuk', 1500, $admin, confirmed: true);
    expect($variant->fresh()->stock)->toBe(1500);
});

it('menolak mutasi manual dari pengguna tanpa izin kelola stok', function () {
    app(StockService::class)->adjust(Helpers::variant(), 'masuk', 1, Helpers::buyer());
})->throws(AuthorizationException::class);

it('mencatat stok sebelum dan sesudah, dan riwayat tidak bisa diubah', function () {
    $movement = app(StockService::class)->adjust(Helpers::variant(stock: 5), 'masuk', 3, Helpers::staff('admin'), note: 'nota 12');

    expect($movement->stock_before)->toBe(5)->and($movement->stock_after)->toBe(8);
    expect(fn () => $movement->update(['qty_change' => 99]))->toThrow(LogicException::class);
    expect(fn () => $movement->delete())->toThrow(LogicException::class);
});

// K2 (versi berurutan): stok 10, 50 percobaan beli 1 → tepat 10 berhasil, tidak pernah negatif.
it('tidak pernah membuat stok negatif', function () {
    $variant = Helpers::variant(stock: 10);
    $stock = app(StockService::class);
    $ok = 0;

    foreach (range(1, 50) as $i) {
        try {
            $stock->adjust($variant, 'checkout', -1, reference: "T{$i}");
            $ok++;
        } catch (BusinessRuleException) {
        }
    }

    expect($ok)->toBe(10)->and($variant->fresh()->stock)->toBe(0);
});

// K23: stok 3, keranjang berisi 2, menambah 2 ditolak dengan pesan sisa 1.
it('menolak tambah keranjang melebihi stok dan memberi tahu sisanya', function () {
    $buyer = Helpers::buyer();
    $variant = Helpers::variant(stock: 3);
    $cart = app(CartService::class);
    $cart->add($buyer, [], $variant->id, 2);

    try {
        $cart->add($buyer, [], $variant->id, 2);
        $this->fail('Seharusnya ditolak');
    } catch (BusinessRuleException $e) {
        expect($e->getMessage())->toContain('Anda bisa menambah 1 lagi')->and($e->context['can_add'])->toBe(1);
    }

    // K25: menambah ke keranjang tidak mengubah stok.
    expect($variant->fresh()->stock)->toBe(3);
});

// K24: stok awal sesi 20, hitung fisik 18, terjual 2 selama sesi → setelah posting stok 16.
it('stok opname tidak menimpa penjualan selama sesi', function () {
    $admin = Helpers::staff('admin');
    $variant = Helpers::variant(stock: 20);

    $this->actingAs($admin)->post('/panel/stok/opname', ['origin_id' => $variant->product->origin_id]);
    $opname = StockOpname::query()->firstOrFail();
    $item = $opname->items()->where('variant_id', $variant->id)->firstOrFail();

    app(StockService::class)->adjust($variant, 'checkout', -2, reference: 'BN-X');

    $this->actingAs($admin)->post("/panel/stok/opname/{$opname->hashid}/hitung", ['counts' => [$item->hashid => 18]]);
    $this->actingAs($admin)->post("/panel/stok/opname/{$opname->hashid}/posting");

    expect($variant->fresh()->stock)->toBe(16);
});

it('mengirim email ke pembeli wishlist saat stok habis tersedia kembali', function () {
    Notification::fake();
    $buyer = Helpers::buyer();
    $variant = Helpers::variant(stock: 0);
    Wishlist::query()->create(['user_id' => $buyer->id, 'product_id' => $variant->product_id]);

    app(StockService::class)->adjust($variant, 'masuk', 5, Helpers::staff('admin'));

    Notification::assertSentTo($buyer, BackInStock::class);
});
