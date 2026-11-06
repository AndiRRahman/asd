<?php

namespace App\Domain\Catalog;

use App\Domain\BusinessRuleException;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

/**
 * Syarat tayang Draf ke Aktif dan pembuatan SKU (bagian 4.1).
 */
class ProductPublisher
{
    /** @return list<string> syarat yang belum terpenuhi */
    public function unmetRequirements(Product $product): array
    {
        $product->loadMissing(['images', 'variants']);
        $missing = [];

        if ($product->images->isEmpty()) {
            $missing[] = 'Tambahkan minimal 1 foto.';
        }

        foreach (['producer_id' => 'produsen'] as $field => $label) {
            if (! $product->{$field}) {
                $missing[] = "Pilih {$label}.";
            }
        }

        if (! $product->variants->contains(fn (ProductVariant $v) => $v->price > 0 && $v->weight_gram > 0)) {
            $missing[] = 'Isi minimal satu varian dengan harga dan berat lebih dari 0.';
        }

        if ($product->variants->contains(fn (ProductVariant $v) => $v->compare_price !== null && $v->compare_price <= $v->price)) {
            $missing[] = 'Harga coret harus lebih besar dari harga jual.';
        }

        if (! $product->similarity_resolved) {
            $missing[] = 'Putuskan dulu pemeriksaan produk serupa.';
        }

        return $missing;
    }

    public function changeStatus(Product $product, ProductStatus $status): void
    {
        if ($status === ProductStatus::Aktif && ($missing = $this->unmetRequirements($product)) !== []) {
            throw new BusinessRuleException('Produk belum bisa diaktifkan: '.implode(' ', $missing));
        }

        $product->update(['status' => $status]);
    }

    /** Pola: kode kategori, nomor urut produk, kode varian. Contoh KOP-0042-250G. */
    public static function generateSku(Product $product, ?array $options): string
    {
        $prefix = $product->category?->code ?? 'BKA';
        $suffix = collect($options ?? [])->values()->map(fn ($v) => Str::upper(Str::slug((string) $v, '')))->implode('-') ?: 'STD';
        $base = sprintf('%s-%04d-%s', $prefix, $product->id, Str::limit($suffix, 12, ''));
        $sku = $base;
        $i = 2;

        while (ProductVariant::query()->where('sku', $sku)->exists()) {
            $sku = $base.'-'.$i++;
        }

        return $sku;
    }

    /** Kunci unik kombinasi opsi dalam satu produk. */
    public static function optionsKey(?array $options): string
    {
        $options = $options ?? [];
        ksort($options);

        return Str::lower(collect($options)->map(fn ($v, $k) => $k.'='.trim((string) $v))->implode('|'));
    }
}
