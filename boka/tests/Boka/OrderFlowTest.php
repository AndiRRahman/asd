<?php

use App\Domain\BusinessRuleException;
use App\Domain\Cart\CartService;
use App\Domain\Checkout\CreateOrder;
use App\Domain\Order\OrderService;
use App\Domain\Order\OrderStatus;
use App\Domain\Payment\PaymentService;
use App\Models\BankAccount;
use App\Models\FlashSale;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Voucher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Boka\Helpers;

beforeEach(function () {
    Storage::fake('local');
});

function placeOrder($buyer, array $variants, array $quantities = [], ?string $key = null, ?string $voucher = null): Order
{
    $cart = app(CartService::class);

    foreach ($variants as $i => $variant) {
        $cart->add($buyer, [], $variant->id, $quantities[$i] ?? 1);
    }

    $address = $buyer->addresses()->first() ?? Helpers::address($buyer);
    $bank = BankAccount::query()->first() ?? Helpers::bank();

    return app(CreateOrder::class)->handle($buyer, [
        'address_id' => $address->id,
        'shipping' => Helpers::localShipping($variants),
        'bank_account_id' => $bank->id,
        'voucher_code' => $voucher,
    ], $key ?? (string) str()->uuid());
}

function proofFile(): UploadedFile
{
    return UploadedFile::fake()->image('bukti.jpg', 600, 900);
}

// K25: stok dipotong tepat saat checkout.
it('memotong stok saat pesanan dibuat dan menambah kode unik', function () {
    $buyer = Helpers::buyer();
    $variant = Helpers::variant(price: 50_000, stock: 5);

    $order = placeOrder($buyer, [$variant], [2]);

    expect($variant->fresh()->stock)->toBe(3)
        ->and($order->status)->toBe(OrderStatus::MenungguPembayaran)
        ->and($order->unique_code)->toBeBetween(1, 999)
        ->and($order->grand_total)->toBe(100_000 + 10_000 + $order->unique_code)
        ->and($order->code)->toMatch('/^BN-\d{6}-[2-9A-HJ-KMNP-Z]{5}$/');
});

// K14: dua permintaan dengan kunci sama menghasilkan satu pesanan.
it('idempoten untuk kunci yang sama', function () {
    $buyer = Helpers::buyer();
    $variant = Helpers::variant(stock: 5);
    $first = placeOrder($buyer, [$variant], key: 'kunci-sama');
    $second = app(CreateOrder::class)->handle($buyer, ['address_id' => 0, 'shipping' => [], 'bank_account_id' => 0], 'kunci-sama');

    expect($second->id)->toBe($first->id)->and(Order::query()->count())->toBe(1);
});

// K23 bagian kedua: satu item kurang stok membatalkan seluruh pesanan tanpa memotong varian lain.
it('membatalkan seluruh pesanan jika satu varian kurang stok', function () {
    $buyer = Helpers::buyer();
    $a = Helpers::variant(stock: 5);
    $b = Helpers::variant(stock: 1);
    $cart = app(CartService::class);
    $cart->add($buyer, [], $a->id, 2);
    $cart->add($buyer, [], $b->id, 1);
    $b->update(['stock' => 0]); // terjual ke orang lain setelah masuk keranjang

    expect(fn () => app(CreateOrder::class)->handle($buyer, [
        'address_id' => Helpers::address($buyer)->id, 'shipping' => Helpers::localShipping([$a, $b]), 'bank_account_id' => Helpers::bank()->id,
    ], 'x'))->toThrow(BusinessRuleException::class);

    expect($a->fresh()->stock)->toBe(5)->and(Order::query()->count())->toBe(0);
});

// K3: pesanan tanpa bukti kedaluwarsa; stok dan item kembali.
it('mengedaluwarsakan pesanan, mengembalikan stok, dan item kembali ke keranjang', function () {
    $buyer = Helpers::buyer();
    $variant = Helpers::variant(stock: 5);
    $order = placeOrder($buyer, [$variant], [2]);

    $this->travel(25)->hours();
    $this->artisan('boka:expire-orders')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Kedaluwarsa)
        ->and($variant->fresh()->stock)->toBe(5)
        ->and(app(CartService::class)->count($buyer))->toBe(2);
});

// K4: upload bukti setelah batas ditolak (409 di web dan API).
it('menolak bukti transfer setelah batas waktu', function () {
    $buyer = Helpers::buyer();
    $order = placeOrder($buyer, [Helpers::variant()]);
    $this->travel(25)->hours();

    expect(fn () => app(PaymentService::class)->submitProof($order, $buyer, proofFile(), 'BRI', 'Rahma', now()->toDateString()))
        ->toThrow(BusinessRuleException::class);

    $order2 = Order::query()->first();
    $token = $buyer->createToken('hp')->plainTextToken;
    $this->withToken($token)->postJson("/api/v1/orders/{$order2->code}/payment-proof", [
        'file' => proofFile(), 'sender_bank' => 'BRI', 'sender_name' => 'Rahma', 'transferred_at' => now()->toDateString(),
    ])->assertStatus(409);
});

// K5 dan K27: penolakan kedua membatalkan; Setujui butuh daftar periksa mutasi.
it('menolak dua kali membatalkan pesanan dan mengembalikan stok serta voucher', function () {
    $buyer = Helpers::buyer();
    $finance = Helpers::staff('admin_transaksi');
    $variant = Helpers::variant(price: 100_000, stock: 5);
    $voucher = Voucher::query()->create(['code' => 'V10', 'type' => 'belanja', 'discount_type' => 'persen', 'value' => 10, 'quota' => 10, 'per_user_limit' => 1, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
    $order = placeOrder($buyer, [$variant], voucher: 'V10');
    $payments = app(PaymentService::class);

    expect($voucher->fresh()->used)->toBe(1);

    $payments->submitProof($order, $buyer, proofFile(), 'BRI', 'Rahma', now()->toDateString());
    expect(fn () => $payments->approve($order->fresh(), $finance, false, null))->toThrow(BusinessRuleException::class);

    $payments->reject($order->fresh(), $finance, 'Nominal tidak sesuai');
    expect($order->fresh()->status)->toBe(OrderStatus::MenungguPembayaran);

    $payments->submitProof($order->fresh(), $buyer, proofFile(), 'BRI', 'Rahma', now()->toDateString());
    $payments->reject($order->fresh(), $finance, 'Nominal tidak sesuai');

    expect($order->fresh()->status)->toBe(OrderStatus::Dibatalkan)
        ->and($variant->fresh()->stock)->toBe(5)
        ->and($voucher->fresh()->used)->toBe(0);
});

// K27 dan T3: pesanan di atas ambang butuh dua orang berbeda.
it('mewajibkan persetujuan kedua oleh orang lain untuk nominal besar', function () {
    Setting::put('second_approval_threshold', 50_000);
    $buyer = Helpers::buyer();
    $first = Helpers::staff('admin_transaksi');
    $second = Helpers::staff('super_admin');
    $order = placeOrder($buyer, [Helpers::variant(price: 100_000)]);
    $payments = app(PaymentService::class);
    $payments->submitProof($order, $buyer, proofFile(), 'BRI', 'Rahma', now()->toDateString());

    expect($payments->approve($order->fresh(), $first, true, now()->toDateTimeString()))->toBe('menunggu_persetujuan_kedua');
    expect(fn () => $payments->approve($order->fresh(), $first, true, now()->toDateTimeString()))->toThrow(BusinessRuleException::class);
    expect($payments->approve($order->fresh(), $second, true, now()->toDateTimeString()))->toBe('disetujui');
    expect($order->fresh()->status)->toBe(OrderStatus::Diproses);
});

// K31: Admin tanpa izin transaksi mendapat 403.
it('hanya Admin Transaksi yang bisa menyetujui pembayaran', function () {
    $buyer = Helpers::buyer();
    $order = placeOrder($buyer, [Helpers::variant()]);
    app(PaymentService::class)->submitProof($order, $buyer, proofFile(), 'BRI', 'Rahma', now()->toDateString());

    $this->actingAs(Helpers::staff('admin'))
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post("/panel/pembayaran/{$order->code}/setujui", ['mutation_checked' => '1', 'mutation_datetime' => now()->subMinute()->toDateTimeString()])
        ->assertForbidden();

    $this->actingAs(Helpers::staff('admin_transaksi'))
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post("/panel/pembayaran/{$order->code}/setujui", ['mutation_checked' => '1', 'mutation_datetime' => now()->subMinute()->toDateTimeString()])
        ->assertRedirect('/panel/pembayaran');

    expect($order->fresh()->status)->toBe(OrderStatus::Diproses);
});

// K9: bukti yang sama di pesanan kedua memunculkan peringatan.
it('mendeteksi bukti transfer ganda', function () {
    $buyer = Helpers::buyer();
    $file = proofFile();
    $copy = new UploadedFile($file->getRealPath(), 'lagi.jpg', 'image/jpeg', null, true);
    $one = placeOrder($buyer, [Helpers::variant()]);
    $two = placeOrder($buyer, [Helpers::variant()]);
    $payments = app(PaymentService::class);

    $payments->submitProof($one, $buyer, $file, 'BRI', 'Rahma', now()->toDateString());
    $proof = $payments->submitProof($two, $buyer, $copy, 'BRI', 'Rahma', now()->toDateString());

    expect($payments->duplicateOrders($proof))->toBe([$one->code]);
});

// K16: pesanan 2 paket dengan 1 resi tetap Diproses.
it('baru berstatus Dikirim setelah semua paket punya resi', function () {
    $buyer = Helpers::buyer();
    $admin = Helpers::staff('admin_transaksi');
    $order = placeOrder($buyer, [Helpers::variant(), Helpers::variant()]);
    $payments = app(PaymentService::class);
    $payments->submitProof($order, $buyer, proofFile(), 'BRI', 'Rahma', now()->toDateString());
    $payments->approve($order->fresh(), $admin, true, now()->toDateTimeString());
    [$s1, $s2] = $order->fresh()->shipments->all();
    $service = app(OrderService::class);

    $service->setTracking($s1, 'JNE001', $admin);
    expect($order->fresh()->status)->toBe(OrderStatus::Diproses);

    $service->setTracking($s2->fresh(), 'JNE002', $admin);
    expect($order->fresh()->status)->toBe(OrderStatus::Dikirim);
});

// K29: kode ambil wajib benar; 5 kali salah mengunci 15 menit.
it('mengunci input kode ambil setelah 5 kali salah', function () {
    $buyer = Helpers::buyer();
    $admin = Helpers::staff('admin_transaksi');
    $variant = Helpers::variant();
    $cart = app(CartService::class);
    $cart->add($buyer, [], $variant->id, 1);
    $order = app(CreateOrder::class)->handle($buyer, ['address_id' => Helpers::address($buyer)->id, 'shipping' => [$variant->product->origin_id => 'ambil'], 'bank_account_id' => Helpers::bank()->id], 'ambil-1');
    $payments = app(PaymentService::class);
    $payments->submitProof($order, $buyer, proofFile(), 'BRI', 'Rahma', now()->toDateString());
    $payments->approve($order->fresh(), $admin, true, now()->toDateTimeString());
    $service = app(OrderService::class);
    $shipment = $order->fresh()->shipments->first();
    $service->markReadyForPickup($shipment, $admin);

    foreach (range(1, 5) as $i) {
        try {
            $service->verifyPickup($shipment->fresh(), '000000');
        } catch (BusinessRuleException) {
        }
    }

    expect(fn () => $service->verifyPickup($shipment->fresh(), OrderService::pickupCode($shipment)))->toThrow(BusinessRuleException::class, 'dikunci');

    $this->travel(16)->minutes();
    $service->verifyPickup($shipment->fresh(), OrderService::pickupCode($shipment));
    expect($shipment->fresh()->status)->toBe('diambil');
});

// K26: aturan anti-penimbunan.
it('menolak pesanan ke-4 yang menunggu pembayaran', function () {
    $buyer = Helpers::buyer();
    foreach (range(1, 3) as $i) {
        placeOrder($buyer, [Helpers::variant()]);
    }

    placeOrder($buyer, [Helpers::variant()]);
})->throws(BusinessRuleException::class, 'Maksimal 3');

it('memblokir checkout 24 jam setelah 3 pesanan kedaluwarsa, tetapi pembatalan sendiri tidak dihitung', function () {
    $buyer = Helpers::buyer();
    $cancelled = placeOrder($buyer, [Helpers::variant()]);
    app(OrderService::class)->cancelByBuyer($cancelled, $buyer);

    foreach (range(1, 3) as $i) {
        placeOrder($buyer, [Helpers::variant()]);
    }
    $this->travel(25)->hours();
    $this->artisan('boka:expire-orders');

    expect($buyer->fresh()->checkout_blocked_until)->not->toBeNull();
    app(CartService::class)->clear($buyer);
    expect(fn () => placeOrder($buyer->fresh(), [Helpers::variant()]))->toThrow(BusinessRuleException::class, 'diblokir');
});

it('memberi batas bayar 1 jam untuk pesanan berisi flash sale', function () {
    $buyer = Helpers::buyer();
    $variant = Helpers::variant(price: 50_000);
    FlashSale::query()->create(['name' => 'F', 'starts_at' => now()->subHour(), 'ends_at' => now()->addDay()])
        ->items()->create(['variant_id' => $variant->id, 'sale_price' => 30_000, 'quota' => 3]);

    $order = placeOrder($buyer, [$variant]);

    expect((int) round(now()->diffInMinutes($order->payment_deadline_at)))->toBe(60);
});
