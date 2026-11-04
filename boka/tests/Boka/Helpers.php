<?php

namespace Tests\Boka;

use App\Domain\Catalog\ProductStatus;
use App\Domain\Catalog\SimilarityChecker;
use App\Models\Address;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Origin;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Pembuat data uji yang ringkas untuk kriteria penerimaan bagian 15.
 */
final class Helpers
{
    public static function origin(array $attributes = []): Origin
    {
        $origin = Origin::query()->create([
            'name' => 'Gudang '.Str::random(4), 'address' => 'Jl. Uji', 'district' => 'Tanete Riattang',
            'pickup_enabled' => true, 'pickup_hours' => '08.00-17.00', ...$attributes,
        ]);
        $origin->localRates()->create(['destination_district' => 'Tanete Riattang', 'fee' => 10_000]);

        return $origin;
    }

    public static function variant(int $price = 50_000, int $stock = 10, array $product = [], array $variant = []): ProductVariant
    {
        $origin = $product['origin_id'] ?? self::origin()->id;
        $category = Category::query()->firstOrCreate(['slug' => 'kopi'], ['name' => ['id' => 'Kopi'], 'code' => 'KOP']);
        $producer = Producer::query()->firstOrCreate(['slug' => 'kt-uji'], ['name' => 'KT Uji', 'type' => 'karang_taruna', 'village' => 'Uji', 'district' => 'Bengo', 'default_origin_id' => $origin]);
        $name = $product['name'] ?? 'Produk '.Str::random(5);
        unset($product['name']);

        $model = Product::query()->create([
            'producer_id' => $producer->id, 'category_id' => $category->id, 'origin_id' => $origin,
            'name' => is_array($name) ? $name : ['id' => $name], 'name_normalized' => SimilarityChecker::normalize(is_array($name) ? $name['id'] : $name),
            'slug' => Str::slug(is_array($name) ? $name['id'] : $name).'-'.Str::lower(Str::random(4)), 'status' => ProductStatus::Aktif,
            'similarity_resolved' => true, ...$product,
        ]);

        return $model->variants()->create([
            'sku' => 'SKU-'.Str::upper(Str::random(8)), 'options' => [], 'options_key' => '',
            'price' => $price, 'stock' => $stock, 'weight_gram' => 500, ...$variant,
        ])->load('product');
    }

    public static function buyer(): User
    {
        $user = User::factory()->create(['phone' => '0812'.random_int(10_000_000, 99_999_999)]);
        $user->assignRole('pembeli');

        return $user;
    }

    public static function address(User $user): Address
    {
        return $user->addresses()->create([
            'label' => 'Rumah', 'recipient' => $user->name, 'phone' => '081234567890',
            'destination_id' => 'bone:tanete-riattang', 'district' => 'Tanete Riattang',
            'destination_label' => 'Kec. Tanete Riattang', 'full_address' => 'Jl. Uji 1', 'is_default' => true,
        ]);
    }

    public static function bank(): BankAccount
    {
        return BankAccount::query()->create(['bank_name' => 'BRI', 'account_number' => '0123', 'account_holder' => 'Boka', 'active_from' => now()->subDay()]);
    }

    public static function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => encrypt('SECRET')])->save();
        $user->assignRole($role);

        return $user;
    }

    /** Pilihan kirim "lokal" untuk setiap titik asal di keranjang. */
    public static function localShipping(array $variants): array
    {
        return collect($variants)->mapWithKeys(fn (ProductVariant $v) => [$v->product->origin_id => 'lokal'])->all();
    }
}
