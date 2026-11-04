<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Promotion\PriceResolver;
use App\Domain\Recommendation\RecommendationService;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Post;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Review;
use App\Support\Present;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, RecommendationService $reco, PriceResolver $prices): Response
    {
        $flashItems = $prices->activeFlashItems();
        $flashProducts = Product::query()->active()
            ->whereHas('variants', fn ($q) => $q->whereIn('id', $flashItems->keys()))
            ->with(['cover', 'producer', 'variants'])->get();

        $user = $request->user();
        $sessionId = $request->session()->getId();
        $related = $user || $request->session()->has('searched') ? $reco->relatedToSearches($user?->id, $sessionId, 8) : collect();

        return Inertia::render('shop/home', [
            'banners' => Banner::query()->live()->where('position', 'hero')->get()->map(fn (Banner $b) => [
                'id' => $b->id, 'title' => $b->title, 'subtitle' => $b->subtitle, 'link' => $b->link,
                'image_desktop' => $b->image_desktop, 'image_mobile' => $b->image_mobile,
            ]),
            'popularCategories' => $reco->popularCategories()->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug, 'icon' => $c->icon]),
            'exploreCategories' => $reco->exploreCategories()->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug, 'icon' => $c->icon]),
            'flashSale' => $flashItems->isEmpty() ? null : [
                'ends_at' => $flashItems->first()->flashSale->ends_at->toIso8601String(),
                'name' => $flashItems->first()->flashSale->name,
                'products' => Present::productCards($flashProducts),
            ],
            'discounted' => Present::productCards(Product::query()->active()
                ->whereHas('variants', fn ($q) => $q->whereNotNull('compare_price')->whereColumn('compare_price', '>', 'price'))
                ->with(['cover', 'producer', 'variants'])->limit(8)->get()),
            'bestSellers' => Present::productCards($reco->bestSellers(8)),
            'newest' => Present::productCards($reco->newest(8)),
            'related' => Present::productCards($related),
            'hiddenGems' => Present::productCards($reco->hiddenGems(4)),
            'producers' => Producer::query()->withCount(['products' => fn ($q) => $q->active()])->orderByDesc('products_count')->limit(6)->get()
                ->map(fn (Producer $p) => ['name' => $p->name, 'slug' => $p->slug, 'type' => $p->type, 'village' => $p->village, 'district' => $p->district, 'story' => $p->story, 'products_count' => $p->products_count]),
            'posts' => Post::query()->published()->limit(3)->get()->map(fn (Post $p) => self::postCard($p)),
            'reviews' => Review::query()->where('is_hidden', false)->whereNotNull('body')->with(['user', 'product'])->latest('id')->limit(6)->get()->map(fn ($r) => Present::review($r)),
        ]);
    }

    public static function postCard(Post $post): array
    {
        return [
            'title' => $post->title, 'slug' => $post->slug, 'type' => $post->type, 'excerpt' => $post->excerpt,
            'cover' => $post->cover, 'location' => $post->location, 'published_at' => $post->published_at?->toIso8601String(),
        ];
    }
}
