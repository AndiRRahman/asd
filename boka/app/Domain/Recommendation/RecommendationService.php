<?php

namespace App\Domain\Recommendation;

use App\Domain\Order\OrderStatus;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SearchLog;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Empat blok rekomendasi berbasis aturan dan statistik, tanpa AI (bagian 7.3).
 * Bobot pencarian meluruh mingguan: w = 0.5^(umur hari / 7).
 */
class RecommendationService
{
    /** @return Collection<int, float> bobot per category_id dalam 30 hari terakhir */
    public function categoryWeights(): Collection
    {
        // Cache hanya menyimpan array biasa; Laravel 13 menolak unserialize objek dari cache.
        return collect(Cache::remember('reco:category-weights:'.now()->toDateString(), now()->addDay(), function () {
            $halfLife = config('boka.recommendation.half_life_days');

            return SearchLog::query()
                ->whereNotNull('category_id')
                ->where('created_at', '>=', now()->subDays(config('boka.recommendation.window_days')))
                ->get(['category_id', 'created_at'])
                ->groupBy('category_id')
                ->map(fn ($logs) => $logs->sum(fn ($log) => 0.5 ** ($log->created_at->diffInDays(now()) / $halfLife)))
                ->sortDesc()
                ->all();
        }));
    }

    /** Kategori paling sering dicari: 8 tertinggi. Tanpa data pencarian, memakai penjualan. */
    public function popularCategories(): EloquentCollection
    {
        $ids = $this->categoryWeights()->keys()->take(config('boka.recommendation.popular_categories'))->all();

        if (count($ids) < 4) {
            $ids = array_values(array_unique([...$ids, ...Product::query()->active()
                ->selectRaw('category_id, sum(sold_count) as sold')
                ->groupBy('category_id')->orderByDesc('sold')
                ->pluck('category_id')->all()]));
        }

        $ids = array_slice($ids, 0, config('boka.recommendation.popular_categories'));

        return Category::query()->whereIn('id', $ids)->get()->sortBy(fn ($c) => array_search($c->id, $ids))->values();
    }

    /** Jelajahi juga: kategori di 30% terbawah yang punya stok, 4 diacak per hari. */
    public function exploreCategories(): EloquentCollection
    {
        $weights = $this->categoryWeights();
        $stocked = Category::query()
            ->whereHas('products', fn ($p) => $p->active()->whereHas('variants', fn ($v) => $v->where('stock', '>', 0)))
            ->get()
            ->sortBy(fn ($c) => $weights[$c->id] ?? 0)
            ->values();

        $bottom = $stocked->take(max(config('boka.recommendation.explore_categories'), (int) ceil($stocked->count() * 0.3)));

        return $bottom->shuffle(crc32(now()->toDateString()))->take(config('boka.recommendation.explore_categories'))->values();
    }

    /**
     * Terkait pencarian Anda:
     * skor(p) = 3·kategori + 2·tag + 1·produsen + 0.5·ln(1 + terjual30)
     */
    public function relatedToSearches(?int $userId, ?string $sessionId, int $limit = 12): EloquentCollection
    {
        $logs = SearchLog::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId), fn ($q) => $q->where('session_id', $sessionId ?? '-'))
            ->latest('id')->limit(10)->get();

        if ($logs->isEmpty()) {
            return $this->bestSellers($limit);
        }

        // Skor pribadi disimpan 15 menit per pengguna sebagai daftar ID berurutan.
        $ids = Cache::remember("reco:related:{$userId}:{$sessionId}", now()->addMinutes(15), function () use ($logs, $userId, $limit) {
            $halfLife = config('boka.recommendation.half_life_days');
            $weights = config('boka.recommendation.weights');
            $categoryW = [];
            $producerW = [];
            $tagW = [];

            $clicked = Product::query()->with('tags')->whereIn('id', $logs->pluck('clicked_product_id')->filter())->get()->keyBy('id');

            foreach ($logs as $log) {
                $w = 0.5 ** ($log->created_at->diffInDays(now()) / $halfLife);

                if ($log->category_id) {
                    $categoryW[$log->category_id] = ($categoryW[$log->category_id] ?? 0) + $w;
                }

                if ($product = $clicked[$log->clicked_product_id] ?? null) {
                    $producerW[$product->producer_id] = ($producerW[$product->producer_id] ?? 0) + $w;

                    foreach ($product->tags as $tag) {
                        $tagW[$tag->id] = ($tagW[$tag->id] ?? 0) + $w;
                    }
                }
            }

            $purchased = $userId ? OrderItem::query()
                ->whereHas('order', fn ($o) => $o->where('user_id', $userId)->whereNotIn('status', [OrderStatus::Kedaluwarsa, OrderStatus::Dibatalkan])->where('created_at', '>=', now()->subDays(30)))
                ->pluck('product_id')->all() : [];

            $sold30 = $this->sold30();

            return $this->baseQuery()
                ->with('tags')
                ->whereNotIn('id', $purchased)
                ->get()
                ->map(function (Product $p) use ($categoryW, $producerW, $tagW, $weights, $sold30) {
                    $p->reco_score = $weights['category'] * ($categoryW[$p->category_id] ?? 0)
                        + $weights['tag'] * $p->tags->sum(fn ($t) => $tagW[$t->id] ?? 0)
                        + $weights['producer'] * ($producerW[$p->producer_id] ?? 0)
                        + $weights['sold'] * log(1 + ($sold30[$p->id] ?? 0));

                    return $p;
                })
                ->sortByDesc('reco_score')
                ->take($limit)
                ->pluck('id')
                ->all();
        });

        return $this->baseQuery()->whereIn('id', $ids)->get()->sortBy(fn ($p) => array_search($p->id, $ids))->values();
    }

    /** Permata tersembunyi: produk berstok dengan tampilan paling sedikit, rating minimal 3,5 jika sudah diulas. */
    public function hiddenGems(int $limit = 8): EloquentCollection
    {
        $views = DB::table('product_daily_stats')
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->selectRaw('product_id, sum(views) as v')->groupBy('product_id')->pluck('v', 'product_id');

        return $this->baseQuery()
            ->whereHas('variants', fn ($v) => $v->where('stock', '>', 0))
            ->where(fn ($q) => $q->where('rating_count', 0)->orWhere('rating_avg', '>=', 3.5))
            ->get()
            ->sortBy(fn ($p) => $views[$p->id] ?? 0)
            ->take($limit * 2)
            ->shuffle(crc32('gems'.now()->toDateString()))
            ->take($limit)
            ->values();
    }

    public function bestSellers(int $limit = 12): EloquentCollection
    {
        return $this->baseQuery()->orderByDesc('sold_count')->limit($limit)->get();
    }

    public function newest(int $limit = 12): EloquentCollection
    {
        return $this->baseQuery()->latest('id')->limit($limit)->get();
    }

    public function relatedToProduct(Product $product, int $limit = 8): EloquentCollection
    {
        return $this->baseQuery()
            ->whereKeyNot($product->id)
            ->where(fn ($q) => $q->where('category_id', $product->category_id)->orWhere('producer_id', $product->producer_id))
            ->orderByRaw('CASE WHEN category_id = ? THEN 0 ELSE 1 END', [$product->category_id])
            ->orderByDesc('sold_count')
            ->limit($limit)
            ->get();
    }

    private function baseQuery()
    {
        return Product::query()->active()->with(['cover', 'producer', 'variants']);
    }

    /** @return array<int, int> */
    private function sold30(): array
    {
        return OrderItem::query()
            ->whereHas('order', fn ($o) => $o->whereIn('status', [OrderStatus::Diproses, OrderStatus::Dikirim, OrderStatus::Selesai])->where('created_at', '>=', now()->subDays(30)))
            ->selectRaw('product_id, sum(qty) as total')->groupBy('product_id')->pluck('total', 'product_id')->all();
    }
}
