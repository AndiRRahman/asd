<?php

namespace App\Domain\Search;

use App\Domain\Recommendation\RecommendationService;
use App\Models\Category;
use App\Models\Producer;
use App\Models\Product;
use App\Models\SearchLog;
use App\Models\Setting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pencarian instan dan katalog berfilter (bagian 3.3 dan 7).
 * Memakai Meilisearch lewat Scout jika aktif; jika mati, beralih ke pencarian database.
 */
class SearchService
{
    public const SORTS = ['relevansi', 'terbaru', 'terlaris', 'harga_terendah', 'harga_tertinggi'];

    public function __construct(private RecommendationService $recommendations) {}

    public static function normalize(string $query): string
    {
        return Str::of($query)->lower()->squish()->limit(100, '')->toString();
    }

    /** Kotak pencarian per huruf (7.1). */
    public function suggest(string $query, ?int $userId, ?string $sessionId): array
    {
        $q = self::normalize($query);

        if ($q === '') {
            return [
                'mode' => 'empty',
                'history' => SearchLog::query()
                    ->when($userId, fn ($b) => $b->where('user_id', $userId), fn ($b) => $b->where('session_id', $sessionId))
                    ->latest('id')->limit(20)->pluck('query')->unique()->take(5)->values(),
                'popular' => $this->popularKeywords(7)->take(5)->values(),
            ];
        }

        if (mb_strlen($q) === 1) {
            return [
                'mode' => 'letter',
                'categories' => Category::query()->get()->filter(fn ($c) => Str::startsWith(Str::lower($c->name), $q))->take(5)->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug])->values(),
                'keywords' => $this->popularKeywords(30)->filter(fn ($k) => Str::startsWith($k, $q))->take(5)->values(),
            ];
        }

        $products = $this->productQuery($q)->with(['cover', 'producer'])->withMin('variants', 'price')->limit(6)->get();

        $result = [
            'mode' => 'results',
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->hashid,
                'name' => $p->name,
                'slug' => $p->slug,
                'price' => (int) $p->variants_min_price,
                'image' => $p->cover?->url(),
                'producer' => $p->producer?->name,
            ]),
            'categories' => Category::query()->where('name', 'like', '%'.$q.'%')->limit(3)->get()->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug]),
            'producers' => Producer::query()->where('name', 'like', '%'.$q.'%')->limit(2)->get()->map(fn ($p) => ['name' => $p->name, 'slug' => $p->slug, 'type' => $p->typeLabel()]),
            'keywords' => $this->popularKeywords(30)->filter(fn ($k) => Str::contains($k, $q) && $k !== $q)->take(4)->values(),
        ];

        if ($products->isEmpty()) {
            $result['mode'] = 'empty_results';
            $result['did_you_mean'] = $this->closestSpelling($q);
            $result['explore'] = $this->recommendations->exploreCategories()->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug]);
        }

        return $result;
    }

    /**
     * Katalog dengan filter: kategori, rentang harga, produsen, kecamatan asal, rating, stok; dan urutan.
     */
    public function catalog(array $filters, int $perPage = 24): LengthAwarePaginator
    {
        $q = self::normalize($filters['q'] ?? '');
        $query = $q !== '' ? $this->productQuery($q) : Product::query()->active();

        $query->with(['cover', 'producer', 'variants'])
            ->withMin('variants', 'price')
            ->when($filters['category'] ?? null, function (Builder $b, $slug) {
                $category = Category::query()->where('slug', $slug)->first();
                $b->whereIn('category_id', $category?->familyIds() ?? [0]);
            })
            ->when($filters['producer'] ?? null, fn (Builder $b, $slug) => $b->whereHas('producer', fn ($p) => $p->where('slug', $slug)))
            ->when($filters['district'] ?? null, fn (Builder $b, $d) => $b->whereHas('origin', fn ($o) => $o->where('district', $d)))
            ->when($filters['rating'] ?? null, fn (Builder $b, $r) => $b->where('rating_avg', '>=', (float) $r))
            ->when(isset($filters['min_price']) && $filters['min_price'] !== '', fn (Builder $b) => $b->whereHas('variants', fn ($v) => $v->where('price', '>=', (int) $filters['min_price'])))
            ->when(isset($filters['max_price']) && $filters['max_price'] !== '', fn (Builder $b) => $b->whereHas('variants', fn ($v) => $v->where('price', '<=', (int) $filters['max_price'])))
            ->when($filters['in_stock'] ?? false, fn (Builder $b) => $b->whereHas('variants', fn ($v) => $v->where('stock', '>', 0)));

        match ($filters['sort'] ?? 'relevansi') {
            'terbaru' => $query->latest('id'),
            'terlaris' => $query->orderByDesc('sold_count'),
            'harga_terendah' => $query->orderBy('variants_min_price'),
            'harga_tertinggi' => $query->orderByDesc('variants_min_price'),
            default => $q !== ''
                ? $query->orderByRaw('CASE WHEN name_normalized LIKE ? THEN 0 ELSE 1 END', [$q.'%'])->orderByDesc('sold_count')
                : $query->orderByDesc('is_featured')->orderByDesc('sold_count'),
        };

        return $query->paginate($perPage)->withQueryString();
    }

    /** Satu pencarian dicatat saat Enter, memilih saran, atau berhenti mengetik 1 detik (7.2). */
    public function log(string $query, int $results, ?int $userId, ?string $sessionId, ?int $clickedProductId = null): void
    {
        $q = self::normalize($query);

        if (mb_strlen($q) < 2) {
            return;
        }

        $categoryId = $clickedProductId
            ? Product::query()->whereKey($clickedProductId)->value('category_id')
            : $this->productQuery($q)->limit(1)->value('category_id');

        SearchLog::query()->create([
            'user_id' => $userId,
            'session_id' => $userId ? null : $sessionId,
            'query' => $q,
            'results_count' => $results,
            'clicked_product_id' => $clickedProductId,
            'category_id' => $categoryId,
        ]);
    }

    /** Pencarian mencocokkan semua bahasa sekaligus dan sinonim lokal. */
    public function productQuery(string $q): Builder
    {
        if (config('scout.driver') === 'meilisearch') {
            try {
                $ids = Product::search($q)->take(200)->keys()->all();

                return Product::query()->active()->whereIn('id', $ids);
            } catch (Throwable $e) {
                Log::warning('Meilisearch tidak tersedia, beralih ke database', ['error' => $e->getMessage()]);
            }
        }

        $query = Product::query()->active();

        foreach ($this->expandTerms($q) as $alternatives) {
            $query->where(function (Builder $b) use ($alternatives) {
                foreach ($alternatives as $term) {
                    $like = '%'.$term.'%';
                    $b->orWhere('name', 'like', $like)
                        ->orWhere('name_normalized', 'like', $like)
                        ->orWhereHas('tags', fn ($t) => $t->where('name', 'like', $like))
                        ->orWhereHas('producer', fn ($p) => $p->where('name', 'like', $like));
                }
            });
        }

        return $query;
    }

    /** @return list<list<string>> setiap kata beserta sinonimnya */
    private function expandTerms(string $q): array
    {
        $synonyms = Setting::get('synonyms') ?? [];

        return collect(explode(' ', $q))
            ->filter(fn ($w) => mb_strlen($w) >= 2)
            ->map(function ($word) use ($synonyms) {
                $group = collect($synonyms)->first(fn ($g) => in_array($word, $g, true));

                return $group ?? [$word];
            })
            ->values()
            ->all();
    }

    private function popularKeywords(int $days)
    {
        return SearchLog::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->where('results_count', '>', 0)
            ->selectRaw('query, count(*) as total')
            ->groupBy('query')
            ->orderByDesc('total')
            ->limit(30)
            ->pluck('query');
    }

    private function closestSpelling(string $q): ?string
    {
        $words = Product::query()->active()->pluck('name_normalized')
            ->flatMap(fn ($n) => explode(' ', $n))
            ->filter(fn ($w) => mb_strlen($w) >= 3 && ! is_numeric($w[0]))
            ->unique();

        $best = $words->map(fn ($w) => ['word' => $w, 'distance' => levenshtein($q, $w)])
            ->sortBy('distance')
            ->first();

        return $best && $best['distance'] <= max(2, intdiv(mb_strlen($q), 3)) ? $best['word'] : null;
    }
}
