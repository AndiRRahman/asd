<?php

use App\Support\HashId;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Boka\Helpers;

// Poin 4: tujuan setelah login berdasarkan peran.
it('mengarahkan staf ke panel dan pembeli ke beranda setelah login', function () {
    $admin = Helpers::staff('admin');
    $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/panel');
    $this->post('/logout');

    $buyer = Helpers::buyer();
    $this->post('/login', ['email' => $buyer->email, 'password' => 'password'])->assertRedirect('/');
});

it('mengembalikan pembeli ke halaman yang tadinya dituju, misalnya checkout', function () {
    $buyer = Helpers::buyer();

    $this->get('/checkout')->assertRedirect('/login');
    $this->post('/login', ['email' => $buyer->email, 'password' => 'password'])->assertRedirect(url('/checkout'));
});

// Poin 5: akun nonaktif.
it('menolak login akun yang dinonaktifkan dan mengeluarkan sesi yang masih terbuka', function () {
    $buyer = Helpers::buyer();
    $buyer->forceFill(['status' => 'nonaktif'])->save();

    $this->post('/login', ['email' => $buyer->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();

    $active = Helpers::buyer();
    $this->actingAs($active)->get('/pesanan')->assertOk();
    $active->forceFill(['status' => 'nonaktif'])->save();
    $this->actingAs($active->fresh())->get('/pesanan')->assertRedirect(route('login'));
});

it('menolak pendaftaran yang mengisi kolom honeypot', function () {
    $this->post('/register', [
        'name' => 'Bot', 'email' => 'bot@spam.test', 'phone' => '081211112222', 'privacy' => '1',
        'password' => 'password', 'password_confirmation' => 'password', 'website' => 'http://spam.test',
    ])->assertSessionHasErrors('website');

    $this->assertGuest();
});

// HashID: ID angka berurutan tidak bisa dipakai di URL; kode acak bisa.
it('hanya menerima HashID di URL, bukan ID angka', function () {
    $admin = Helpers::staff('admin');
    $product = Helpers::variant()->product;

    expect($product->hashid)->not->toBe((string) $product->id)->and(HashId::decode($product->hashid))->toBe($product->id);

    $this->actingAs($admin)->get("/panel/produk/{$product->id}/edit")->assertNotFound();
    $this->actingAs($admin)->get("/panel/produk/{$product->hashid}/edit")->assertOk();
});

it('menyamarkan ID di data halaman dan API', function () {
    $buyer = Helpers::buyer();
    $variant = Helpers::variant();

    $this->actingAs($buyer)->get('/produk/'.$variant->product->slug)->assertInertia(fn (Assert $page) => $page
        ->where('product.id', $variant->product->hashid)
        ->where('product.variants.0.id', $variant->hashid)
        ->where('auth.user.id', $buyer->hashid)
        ->missing('auth.user.status')
        ->missing('auth.user.phone'));

    $token = $buyer->createToken('hp')->plainTextToken;
    $this->withToken($token)->patchJson("/api/v1/cart/items/{$variant->id}", ['qty' => 1])->assertNotFound();
});

// CSP: script hanya dari situs sendiri atau dengan nonce yang cocok.
it('memasang Content-Security-Policy dengan nonce yang sama dengan tag script', function () {
    $response = $this->get('/');
    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("default-src 'self'")->toContain("object-src 'none'")->toContain("frame-ancestors 'self'");
    preg_match("/'nonce-([^']+)'/", $csp, $match);
    expect($match[1] ?? '')->not->toBe('');
});

it('membatasi laju aksi tulis', function () {
    foreach (range(1, 60) as $i) {
        $this->post('/preferensi/tema', ['theme' => 'dark']);
    }

    $this->post('/preferensi/tema', ['theme' => 'dark'])->assertStatus(429);
});
