<?php

namespace App\Domain\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSimilarityDecision;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Deteksi produk serupa berbasis aturan dan skor tetap, tanpa AI (bagian 4.2).
 *
 * skor = 0.60·nama + 0.15·kategori + 0.15·produsen + 0.10·ukuran
 */
class SimilarityChecker
{
    /**
     * Langkah 1: huruf kecil, buang tanda baca dan kata umum, seragamkan satuan, urutkan kata.
     */
    public static function normalize(string $name): string
    {
        $text = Str::lower($name);
        $text = preg_replace('/1\s*\/\s*4\s*kg/u', '250g', $text);
        $text = preg_replace('/1\s*\/\s*2\s*kg/u', '500g', $text);
        $text = preg_replace('/(\d+)\s*(gr|gram|grm|g)\b/u', '$1g', $text);
        $text = preg_replace('/(\d+)\s*(kg|kilo|kilogram)\b/u', '$1kg', $text);
        $text = preg_replace('/(\d+)\s*(ml|mililiter)\b/u', '$1ml', $text);
        $text = preg_replace('/(\d+)\s*(l|ltr|liter)\b/u', '$1l', $text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

        $stopwords = config('boka.similarity.stopwords');
        $words = array_filter(
            preg_split('/\s+/', trim($text)) ?: [],
            fn (string $word) => $word !== '' && ! in_array($word, $stopwords, true),
        );
        sort($words);

        return implode(' ', $words);
    }

    /** Koefisien Jaccard atas trigram karakter. */
    public static function trigramSimilarity(string $a, string $b): float
    {
        $ta = self::trigrams($a);
        $tb = self::trigrams($b);

        if ($ta === [] && $tb === []) {
            return 1.0;
        }

        $intersection = count(array_intersect_key($ta, $tb));
        $union = count($ta + $tb);

        return $union === 0 ? 0.0 : $intersection / $union;
    }

    /** @return array<string, true> */
    private static function trigrams(string $text): array
    {
        $padded = '  '.$text.' ';
        $grams = [];

        for ($i = 0; $i < mb_strlen($padded) - 2; $i++) {
            $grams[mb_substr($padded, $i, 3)] = true;
        }

        return $grams;
    }

    /** Berat atau isi dalam gram, dibaca dari nama ternormalisasi. */
    public static function sizeInGrams(string $normalized): ?int
    {
        if (preg_match('/(\d+)(g|kg|ml|l)\b/', $normalized, $m)) {
            return (int) $m[1] * (in_array($m[2], ['kg', 'l'], true) ? 1000 : 1);
        }

        return null;
    }

    /**
     * @param  array{name: string, category_id: ?int, producer_id: ?int, weight_gram?: ?int, exclude_id?: ?int, sku?: ?string, barcode?: ?string}  $input
     * @return Collection<int, array{product: Product, score: float, level: string}>
     */
    public function candidates(array $input): Collection
    {
        $normalized = self::normalize($input['name']);
        $excludeId = $input['exclude_id'] ?? null;

        $exact = $this->exactCodeMatches($input['sku'] ?? null, $input['barcode'] ?? null, $excludeId);

        // Langkah 2: kandidat dari indeks pencarian. Di sini memakai kata pertama yang cukup panjang,
        // termasuk produk Draf, Nonaktif, dan Arsip.
        $tokens = array_values(array_filter(explode(' ', $normalized), fn ($w) => mb_strlen($w) >= 3 && ! preg_match('/^\d/', $w)));
        $pool = Product::withTrashed()
            ->when($excludeId, fn ($q) => $q->whereKeyNot($excludeId))
            ->where(function ($q) use ($tokens) {
                foreach (array_slice($tokens, 0, 4) as $token) {
                    $q->orWhere('name_normalized', 'like', '%'.$token.'%');
                }
            })
            ->with(['category', 'variants'])
            ->limit(10)
            ->get();

        $decided = $excludeId
            ? ProductSimilarityDecision::query()->where('product_id', $excludeId)->where('decision', 'terpisah')->pluck('candidate_product_id')->all()
            : [];

        $size = $input['weight_gram'] ?? self::sizeInGrams($normalized);

        $scored = $pool
            ->reject(fn (Product $p) => in_array($p->id, $decided, true))
            ->map(function (Product $p) use ($normalized, $input, $size) {
                $score = 0.60 * self::trigramSimilarity($normalized, $p->name_normalized)
                    + 0.15 * $this->categoryScore($input['category_id'] ?? null, $p)
                    + 0.15 * (($input['producer_id'] ?? null) && $input['producer_id'] === $p->producer_id ? 1 : 0)
                    + 0.10 * $this->sizeScore($size, $p);

                return ['product' => $p, 'score' => round($score, 3), 'level' => $this->level($score)];
            });

        return $exact->map(fn (Product $p) => ['product' => $p, 'score' => 1.0, 'level' => 'duplikat_pasti'])
            ->concat($scored)
            ->unique(fn ($c) => $c['product']->id)
            ->filter(fn ($c) => $c['level'] !== 'tidak_serupa')
            ->sortByDesc('score')
            ->values();
    }

    public function level(float $score): string
    {
        return match (true) {
            $score >= config('boka.similarity.block') => 'kemungkinan_sama',
            $score >= config('boka.similarity.suggest') => 'mirip',
            default => 'tidak_serupa',
        };
    }

    /** Penyimpanan ditahan jika ada duplikat pasti atau skor ≥ 0,85. */
    public static function blocks(Collection $candidates): bool
    {
        return $candidates->contains(fn ($c) => in_array($c['level'], ['duplikat_pasti', 'kemungkinan_sama'], true));
    }

    /** @return Collection<int, Product> */
    private function exactCodeMatches(?string $sku, ?string $barcode, ?int $excludeId): Collection
    {
        if (blank($sku) && blank($barcode)) {
            return collect();
        }

        $productIds = ProductVariant::query()
            ->where(fn ($q) => $q->when($sku, fn ($q) => $q->orWhere('sku', $sku))->when($barcode, fn ($q) => $q->orWhere('barcode', $barcode)))
            ->when($excludeId, fn ($q) => $q->where('product_id', '!=', $excludeId))
            ->pluck('product_id');

        return Product::withTrashed()->whereIn('id', $productIds)->get();
    }

    private function categoryScore(?int $categoryId, Product $candidate): float
    {
        if (! $categoryId || ! $candidate->category_id) {
            return 0;
        }

        if ($categoryId === $candidate->category_id) {
            return 1;
        }

        $parentA = Category::query()->whereKey($categoryId)->value('parent_id');

        return $parentA && $parentA === $candidate->category?->parent_id ? 0.5 : 0;
    }

    private function sizeScore(?int $size, Product $candidate): float
    {
        $other = self::sizeInGrams($candidate->name_normalized) ?? $candidate->variants->first()?->weight_gram;

        if (! $size || ! $other) {
            return 0;
        }

        return abs($size - $other) / max($size, $other) <= 0.10 ? 1 : 0;
    }
}
