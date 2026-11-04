<?php

use App\Domain\BusinessRuleException;
use App\Domain\Cart\CartService;
use App\Domain\Checkout\CreateOrder;
use App\Domain\Payment\RefundService;
use App\Http\Controllers\Shop\ContentController;
use App\Models\AdminInvitation;
use App\Models\BankAccount;
use App\Models\User;
use App\Notifications\BankAccountChanged;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Boka\Helpers;

function simpleOrder(User $buyer)
{
    $variant = Helpers::variant();
    app(CartService::class)->add($buyer, [], $variant->id, 1);

    return app(CreateOrder::class)->handle($buyer, [
        'address_id' => Helpers::address($buyer)->id, 'shipping' => Helpers::localShipping([$variant]), 'bank_account_id' => (BankAccount::query()->first() ?? Helpers::bank())->id,
    ], (string) str()->uuid());
}

// K6: pembeli tidak bisa membuka pesanan orang lain (404, web dan API).
it('mengembalikan 404 untuk pesanan milik orang lain', function () {
    $owner = Helpers::buyer();
    $order = simpleOrder($owner);
    $intruder = Helpers::buyer();

    $this->actingAs($intruder)->get("/pesanan/{$order->code}")->assertNotFound();
    $this->withToken($intruder->createToken('hp')->plainTextToken)->getJson("/api/v1/orders/{$order->code}")->assertNotFound();
});

// K7: hanya Super Admin bisa membuat Admin.
it('hanya Super Admin yang bisa mengundang Admin', function () {
    Mail::fake();

    $this->actingAs(Helpers::staff('admin'))->post('/panel/pengguna/undang', ['email' => 'baru@boka.test', 'role' => 'admin'])->assertForbidden();
    $this->actingAs(Helpers::staff('super_admin'))->post('/panel/pengguna/undang', ['email' => 'baru@boka.test', 'role' => 'admin'])->assertSessionHasNoErrors();

    expect(AdminInvitation::query()->where('email', 'baru@boka.test')->exists())->toBeTrue();
});

// K8: checkout butuh email terverifikasi.
it('mengarahkan pengguna belum terverifikasi ke halaman verifikasi', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/checkout')->assertRedirect(route('verification.notice'));
});

it('membuka panel untuk Admin tanpa 2FA selama 2FA dinonaktifkan', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/panel')->assertOk();
    $this->actingAs(Helpers::buyer())->get('/panel')->assertForbidden();
});

it('mewajibkan 2FA di panel jika BOKA_TWO_FACTOR dinyalakan', function () {
    config(['boka.two_factor' => true]);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/panel')->assertRedirect(route('security.edit'));
});

// K28: rekening baru tertahan 24 jam dan semua Super Admin mendapat email.
it('menahan rekening baru 24 jam dan memberi tahu semua Super Admin', function () {
    Notification::fake();
    $super = Helpers::staff('super_admin');
    $super->forceFill(['two_factor_confirmed_at' => null])->save();
    $other = Helpers::staff('super_admin');

    $this->actingAs($super)->post('/panel/rekening', ['bank_name' => 'BNI', 'account_number' => '999', 'account_holder' => 'Boka', 'password' => 'password'])->assertSessionHasNoErrors();

    expect(BankAccount::query()->usable()->where('account_number', '999')->exists())->toBeFalse();
    Notification::assertSentTo([$super, $other], BankAccountChanged::class);

    $this->travel(25)->hours();
    expect(BankAccount::query()->usable()->where('account_number', '999')->exists())->toBeTrue();
});

// K30: refund ke rekening atas nama lain ditolak; tidak selesai tanpa penyetuju kedua dan bukti.
it('mengendalikan refund dengan nama rekening, dua orang, dan bukti', function () {
    $buyer = Helpers::buyer();
    $order = simpleOrder($buyer);
    $a = Helpers::staff('admin_transaksi');
    $b = Helpers::staff('admin_transaksi');
    $refunds = app(RefundService::class);

    expect(fn () => $refunds->request($order, $a, ['amount' => 1000, 'bank_name' => 'BRI', 'account_number' => '1', 'account_holder' => 'Orang Lain', 'reason' => 'x']))
        ->toThrow(BusinessRuleException::class);

    $refund = $refunds->request($order, $a, ['amount' => 1000, 'bank_name' => 'BRI', 'account_number' => '1', 'account_holder' => $buyer->name, 'reason' => 'x']);

    expect(fn () => $refunds->approve($refund, $a, UploadedFile::fake()->image('r.jpg')))->toThrow(BusinessRuleException::class);
    expect(fn () => $refunds->approve($refund, $b, null))->toThrow(BusinessRuleException::class);

    Storage::fake('local');
    $refunds->approve($refund, $b, UploadedFile::fake()->image('r.jpg'));
    expect($refund->fresh()->status)->toBe('selesai');
});

it('memasang header keamanan HTTP', function () {
    $this->get('/')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('menyaring HTML berbahaya pada isi konten', function () {
    expect(ContentController::clean('<p onclick="x()">Hai<script>alert(1)</script><a href="javascript:alert(1)">t</a></p>'))
        ->not->toContain('onclick')->not->toContain('<script')->not->toContain('javascript:');
});
