<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Recommendation\RecommendationService;
use App\Domain\Search\SearchService;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Origin;
use App\Models\Producer;
use App\Support\Present;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request, SearchService $search, RecommendationService $reco, ?string $category = null): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'producer' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:60'],
            'rating' => ['nullable', 'numeric', 'between:1,5'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'in_stock' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:'.implode(',', SearchService::SORTS)],
        ]);

        if ($category) {
            $filters['category'] = $category;
        }

        $results = $search->catalog($filters);
        $currentCategory = isset($filters['category']) ? Category::query()->where('slug', $filters['category'])->with(['children', 'parent'])->first() : null;

        if (filled($filters['q'] ?? null)) {
            $request->session()->put('searched', true);
        }

        return Inertia::render('shop/catalog', [
            'filters' => $filters,
            'products' => [
                'data' => Present::productCards($results->items()),
                'total' => $results->total(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
            ],
            'category' => $currentCategory ? [
                'name' => $currentCategory->name,
                'slug' => $currentCategory->slug,
                'parent' => $currentCategory->parent ? ['name' => $currentCategory->parent->name, 'slug' => $currentCategory->parent->slug] : null,
                'children' => $currentCategory->children->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug]),
            ] : null,
            'facets' => [
                'categories' => Category::query()->whereNull('parent_id')->orderBy('sort_order')->get()->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug]),
                'producers' => Producer::query()->orderBy('name')->get(['name', 'slug']),
                'districts' => Origin::query()->where('is_active', true)->distinct()->orderBy('district')->pluck('district'),
            ],
            // Hasil kosong: blok "Jelajahi juga" dan permata tersembunyi (bagian 7).
            'explore' => $results->total() === 0 ? $reco->exploreCategories()->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug, 'icon' => $c->icon]) : [],
            'hiddenGems' => fn () => Present::productCards($reco->hiddenGems(4)),
        ]);
    }

    public function producer(string $slug): Response
    {
        $producer = Producer::query()->where('slug', $slug)->firstOrFail();

        return Inertia::render('shop/producer', [
            'producer' => [
                'name' => $producer->name, 'slug' => $producer->slug, 'type' => $producer->type,
                'village' => $producer->village, 'district' => $producer->district, 'story' => $producer->story,
                'photo' => $producer->photo, 'contact' => $producer->contact,
            ],
            'products' => Present::productCards($producer->products()->active()->with(['cover', 'producer', 'variants'])->get()),
        ]);
    }
}
