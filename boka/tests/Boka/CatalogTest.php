<?php

use App\Domain\Catalog\SimilarityChecker;
use App\Domain\Recommendation\RecommendationService;
use App\Models\Product;
use App\Models\ProductSimilarityDecision;
use App\Models\ProductVariant;
use Tests\Boka\Helpers;

it('menormalkan nama sesuai langkah 1', function () {
    expect(SimilarityChecker::normalize('Kopi Bubuk Robusta ASLI Bone 250 gr'))->toBe('250g bubuk kopi robusta')
        ->and(SimilarityChecker::normalize('Robusta Kopi Bubuk 250gram'))->toBe('250g bubuk kopi robusta')
        ->and(SimilarityChecker::normalize('Kopi 1/4 kg'))->toBe('250g kopi');
});

// K20: produk serupa terdeteksi dengan skor ≥ 0,85 dan pasangan yang diputuskan terpisah tidak diperingatkan lagi.
it('mendeteksi produk serupa dan menghormati keputusan terpisah', function () {
    $existing = Helpers::variant(product: ['name' => 'Kopi Bubuk Robusta 250 gr'])->product;
    $checker = app(SimilarityChecker::class);
    $input = ['name' => 'Robusta Kopi Bubuk 250gram', 'category_id' => $existing->category_id, 'producer_id' => $existing->producer_id];

    $candidates = $checker->candidates($input);
    expect($candidates->first()['product']->id)->toBe($existing->id)
        ->and($candidates->first()['score'])->toBeGreaterThanOrEqual(0.85)
        ->and(SimilarityChecker::blocks($candidates))->toBeTrue();

    $other = Helpers::variant(product: ['name' => 'Robusta Kopi Bubuk 250gram'])->product;
    ProductSimilarityDecision::query()->create(['product_id' => $other->id, 'candidate_product_id' => $existing->id, 'score' => 1, 'decision' => 'terpisah', 'user_id' => Helpers::staff('admin')->id]);

    expect($checker->candidates([...$input, 'exclude_id' => $other->id])->pluck('product.id'))->not->toContain($existing->id);
});

// K19: duplikat pasti (SKU sama) tidak tersimpan.
it('menolak produk baru dengan SKU yang sudah dipakai', function () {
    $existing = Helpers::variant(variant: ['sku' => 'KOP-0001-250G']);
    $admin = Helpers::staff('admin');

    $this->actingAs($admin)->post('/panel/produk', [
        'name' => ['id' => 'Produk lain sama sekali'], 'category_id' => $existing->product->category_id,
        'producer_id' => $existing->product->producer_id, 'origin_id' => $existing->product->origin_id,
        'variants' => [['sku' => 'KOP-0001-250G', 'price' => 10_000, 'weight_gram' => 100]],
    ])->assertSessionHasErrors('variants.0.sku');

    expect(Product::query()->count())->toBe(1);
});

it('menyimpan produk baru sebagai Draf dan menolak aktivasi sebelum syarat tayang terpenuhi', function () {
    $base = Helpers::variant();
    $admin = Helpers::staff('admin');

    $this->actingAs($admin)->post('/panel/produk', [
        'name' => ['id' => 'Dodol Ketan Hitam'], 'category_id' => $base->product->category_id,
        'producer_id' => $base->product->producer_id, 'origin_id' => $base->product->origin_id,
        'variants' => [['price' => 15_000, 'weight_gram' => 250, 'stock' => 4]],
    ])->assertSessionHasNoErrors();

    $product = Product::query()->latest('id')->first();
    expect($product->status->value)->toBe('draf')
        ->and($product->variants->first()->sku)->toStartWith('KOP-')
        ->and($product->variants->first()->stock)->toBe(4);

    $this->actingAs($admin)->post("/panel/produk/{$product->hashid}/status", ['status' => 'aktif'])->assertSessionHasErrors('business');
    expect($product->fresh()->status->value)->toBe('draf');
});

// K11: blok "jarang dicari" tidak menampilkan produk habis.
it('tidak menampilkan produk habis di permata tersembunyi', function () {
    $empty = Helpers::variant(stock: 0)->product;
    $full = Helpers::variant(stock: 5)->product;

    $ids = app(RecommendationService::class)->hiddenGems(10)->pluck('id');

    expect($ids)->toContain($full->id)->not->toContain($empty->id);
});

it('mengalihkan tautan produk yang sudah digabung dengan HTTP 301', function () {
    $main = Helpers::variant()->product;
    $dup = ProductVariant::query()->create(['product_id' => Helpers::variant(product: ['origin_id' => $main->origin_id])->product_id, 'sku' => 'X-2', 'options' => ['Rasa' => 'Pedas'], 'options_key' => 'rasa=pedas', 'price' => 1000, 'weight_gram' => 10])->product;
    $oldSlug = $dup->slug;

    $this->actingAs(Helpers::staff('admin'))->post("/panel/produk/{$dup->hashid}/gabung", ['target_id' => $main->hashid])->assertSessionHasNoErrors();

    $this->get("/produk/{$oldSlug}")->assertStatus(301)->assertRedirect("/produk/{$main->slug}");
    expect($dup->fresh()->status->value)->toBe('arsip');
});
