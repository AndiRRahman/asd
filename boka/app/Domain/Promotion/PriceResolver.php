<?php

namespace App\Domain\Promotion;

use App\Models\FlashSaleItem;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Harga aktif = harga flash sale jika berlaku, lalu harga diskon produk, lalu harga normal (bagian 6).
 * Diskon produk disimpan sebagai harga jual di bawah harga coret.
 */
class PriceResolver
{
    /** @var Collection<int, FlashSaleItem>|null */
    private ?Collection $flashItems = null;

    /**
     * @return array{price: int, compare_price: ?int, flash_sale_item: ?FlashSaleItem, flash_ends_at: ?string}
     */
    public function resolve(ProductVariant $variant): array
    {
        $flash = $this->activeFlashItems()->get($variant->id);

        if ($flash && $flash->sale_price < $variant->price) {
            return [
                'price' => (int) $flash->sale_price,
                'compare_price' => (int) ($variant->compare_price ?? $variant->price),
                'flash_sale_item' => $flash,
                'flash_ends_at' => $flash->flashSale->ends_at->toIso8601String(),
            ];
        }

        return [
            'price' => (int) $variant->price,
            'compare_price' => $variant->compare_price ? (int) $variant->compare_price : null,
            'flash_sale_item' => null,
            'flash_ends_at' => null,
        ];
    }

    public function price(ProductVariant $variant): int
    {
        return $this->resolve($variant)['price'];
    }

    /** @return Collection<int, FlashSaleItem> diindeks per variant_id */
    public function activeFlashItems(): Collection
    {
        return $this->flashItems ??= FlashSaleItem::query()
            ->whereColumn('sold', '<', 'quota')
            ->whereHas('flashSale', fn ($q) => $q->where('starts_at', '<=', now())->where('ends_at', '>', now()))
            ->with('flashSale')
            ->get()
            ->keyBy('variant_id');
    }

    public function forget(): void
    {
        $this->flashItems = null;
    }
}
