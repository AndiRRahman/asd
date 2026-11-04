<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Recommendation\RecommendationService;
use App\Domain\Search\SearchService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\ContentController;
use App\Http\Controllers\Shop\HomeController;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Post;
use App\Models\Producer;
use App\Models\Product;
use App\Support\HashId;
use App\Support\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function categories(): JsonResponse
    {
        return response()->json(['data' => Category::query()->whereNull('parent_id')->with('children')->orderBy('sort_order')->get()->map(fn ($c) => [
            'id' => HashId::encode($c->id), 'name' => $c->name, 'slug' => $c->slug, 'icon' => $c->icon,
            'children' => $c->children->map(fn ($ch) => ['id' => HashId::encode($ch->id), 'name' => $ch->name, 'slug' => $ch->slug]),
        ])]);
    }

    public function products(Request $request, SearchService $search): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string'], 'producer' => ['nullable', 'string'],
            'district' => ['nullable', 'string'], 'rating' => ['nullable', 'numeric'], 'min_price' => ['nullable', 'integer'],
            'max_price' => ['nullable', 'integer'], 'in_stock' => ['nullable', 'boolean'], 'sort' => ['nullable', 'in:'.implode(',', SearchService::SORTS)],
        ]);
        $page = $search->catalog($filters, 20);

        return response()->json([
            'data' => Present::productCards($page->items()),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function product(string $slug): JsonResponse
    {
        $product = Product::query()->active()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => Present::productDetail($product)]);
    }

    public function producer(string $slug): JsonResponse
    {
        $producer = Producer::query()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => [
            ...$producer->only(['name', 'slug', 'type', 'village', 'district']),
            'story' => $producer->story,
            'products' => Present::productCards($producer->products()->active()->with(['cover', 'producer', 'variants'])->get()),
        ]]);
    }

    public function suggest(Request $request, SearchService $search): JsonResponse
    {
        return response()->json($search->suggest((string) $request->query('q', ''), $request->user('sanctum')?->id, $request->header('X-Session-Id')));
    }

    public function home(Request $request, RecommendationService $reco): JsonResponse
    {
        $user = $request->user('sanctum');

        return response()->json([
            'popular_categories' => $reco->popularCategories()->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug]),
            'explore_categories' => $reco->exploreCategories()->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug]),
            'related_to_searches' => Present::productCards($reco->relatedToSearches($user?->id, $request->header('X-Session-Id'))),
            'hidden_gems' => Present::productCards($reco->hiddenGems()),
            'best_sellers' => Present::productCards($reco->bestSellers()),
        ]);
    }

    public function related(string $slug, RecommendationService $reco): JsonResponse
    {
        return response()->json(['data' => Present::productCards($reco->relatedToProduct(Product::query()->active()->where('slug', $slug)->firstOrFail()))]);
    }

    public function banners(Request $request): JsonResponse
    {
        return response()->json(['data' => Banner::query()->live()->where('position', $request->query('position', 'hero'))->get()
            ->map(fn ($b) => $b->only(['id', 'title', 'subtitle', 'link', 'image_desktop', 'image_mobile']))]);
    }

    public function posts(): JsonResponse
    {
        return response()->json(['data' => Post::query()->published()->get()->map(fn ($p) => HomeController::postCard($p))]);
    }

    public function post(string $slug): JsonResponse
    {
        $post = Post::query()->published()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => [...HomeController::postCard($post), 'body' => ContentController::clean($post->body)]]);
    }
}
