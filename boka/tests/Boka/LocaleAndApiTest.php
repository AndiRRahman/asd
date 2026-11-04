<?php

use App\Domain\Cart\CartService;
use App\Mcp\Servers\TokoServer;
use App\Mcp\Tools\ListPendingVerifications;
use App\Mcp\Tools\SetTrackingNumber;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\Boka\Helpers;

// K17: dengan cookie tema gelap, HTML awal sudah memuat kelas dark; tanpa cookie, terang.
it('merender kelas dark dari cookie di HTML pertama', function () {
    $this->get('/')->assertDontSee('class="dark"', false);
    $this->withUnencryptedCookie('appearance', 'dark')->get('/')->assertSee('class="dark"', false);
});

// K18: /en merender antarmuka Inggris; produk tanpa terjemahan tampil dalam bahasa Indonesia.
it('merender halaman /en dalam bahasa Inggris dengan cadangan bahasa Indonesia', function () {
    $variant = Helpers::variant(product: ['name' => 'Kopi Tanpa Terjemahan']);

    $this->get('/en/produk/'.$variant->product->slug)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en')
            ->where('translations', fn ($t) => $t['nav.home'] === 'Home')
            ->where('product.name', 'Kopi Tanpa Terjemahan'));

    $this->get('/produk/'.$variant->product->slug)->assertInertia(fn (Assert $page) => $page->where('locale', 'id')->where('translations', fn ($t) => $t['nav.home'] === 'Beranda'));
});

it('punya kunci terjemahan yang sama di id dan en, dan setiap kunci di kode ada terjemahannya', function () {
    $id = json_decode(file_get_contents(lang_path('id.json')), true);
    $en = json_decode(file_get_contents(lang_path('en.json')), true);

    expect(array_keys($id))->toEqual(array_keys($en));

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('js')));
    $missing = [];

    foreach ($files as $file) {
        if (! str_ends_with($file->getFilename(), '.tsx')) {
            continue;
        }

        preg_match_all("/\\bt\\(\\s*'([a-z_]+\\.[a-z_0-9.]+)'/", file_get_contents($file->getPathname()), $matches);

        foreach ($matches[1] as $key) {
            if (! isset($id[$key])) {
                $missing[] = $key;
            }
        }
    }

    expect(array_unique($missing))->toBe([]);
});

// K13: data untuk meta Open Graph (judul, foto, harga) dikirim di respons awal halaman produk.
it('mengirim data Open Graph produk di respons awal', function () {
    $variant = Helpers::variant(price: 45_000);
    $variant->product->images()->create(['path' => 'produk/x.svg', 'width' => 800, 'height' => 800, 'is_cover' => true]);

    $this->get('/produk/'.$variant->product->slug)->assertInertia(fn (Assert $page) => $page
        ->component('shop/product')
        ->where('product.price', 45_000)
        ->where('product.image.url', fn ($url) => str_contains($url, 'produk/x.svg')));
});

// Format galat API v1 (bagian 4.4): HTTP 409 insufficient_stock dengan daftar item.
it('mengembalikan 409 insufficient_stock dengan format seragam di API', function () {
    $buyer = Helpers::buyer();
    $variant = Helpers::variant(stock: 3);
    $token = $buyer->createToken('hp')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/cart/items', ['variant_id' => $variant->hashid, 'qty' => 5])
        ->assertStatus(409)
        ->assertJsonPath('code', 'insufficient_stock')
        ->assertJsonPath('items.0.available', 3);
});

// K14 lewat API: Idempotency-Key wajib, dan kunci sama menghasilkan satu pesanan.
it('mewajibkan Idempotency-Key dan idempoten di POST /orders', function () {
    $buyer = Helpers::buyer();
    $variant = Helpers::variant();
    $token = $buyer->createToken('hp')->plainTextToken;
    app(CartService::class)->add($buyer, [], $variant->id, 1);
    $payload = ['address_id' => Helpers::address($buyer)->hashid, 'shipping' => [$variant->product->origin->hashid => 'lokal'], 'bank_account_id' => Helpers::bank()->hashid];

    $this->withToken($token)->postJson('/api/v1/orders', $payload)->assertStatus(400);

    $first = $this->withToken($token)->withHeader('Idempotency-Key', 'abc-123')->postJson('/api/v1/orders', $payload)->assertCreated();
    $second = $this->withToken($token)->withHeader('Idempotency-Key', 'abc-123')->postJson('/api/v1/orders', $payload)->assertOk();

    expect($second->json('data.code'))->toBe($first->json('data.code'));
});

it('mendaftar dan masuk lewat API dengan token per perangkat', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Andi', 'email' => 'andi@contoh.id', 'phone' => '081299990000', 'password' => 'password', 'password_confirmation' => 'password', 'privacy' => true,
    ])->assertCreated()->assertJsonStructure(['token', 'user' => ['id', 'email_verified']]);

    $this->postJson('/api/v1/auth/login', ['email' => 'andi@contoh.id', 'password' => 'password', 'device_name' => 'android'])
        ->assertOk()->assertJsonStructure(['token']);
});

it('melayani katalog publik di API dalam bahasa dari Accept-Language', function () {
    Helpers::variant(product: ['name' => ['id' => 'Gula Aren', 'en' => 'Palm Sugar']]);

    $this->getJson('/api/v1/products', ['Accept-Language' => 'en'])->assertOk()->assertJsonPath('data.0.name', 'Palm Sugar');
    $this->getJson('/api/v1/products', ['Accept-Language' => 'id'])->assertOk()->assertJsonPath('data.0.name', 'Gula Aren');
});

// Bagian 14: tool MCP memakai izin yang sama dengan panel dan tercatat di log audit.
it('menjalankan tool MCP dengan izin panel', function () {
    $admin = Helpers::staff('admin');

    TokoServer::actingAs($admin)->tool(ListPendingVerifications::class, ['min_age_hours' => 0])
        ->assertOk()->assertSee('total');

    TokoServer::actingAs(Helpers::buyer())->tool(ListPendingVerifications::class, [])
        ->assertHasErrors();

    TokoServer::actingAs($admin)->tool(SetTrackingNumber::class, ['code' => 'BN-000000-XXXXX', 'tracking_number' => 'JNE1'])
        ->assertHasErrors();

    expect(Activity::query()->where('description', 'Panggilan tool MCP')->count())->toBe(2);
});
