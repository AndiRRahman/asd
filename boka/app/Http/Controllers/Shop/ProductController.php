<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Cart\CartService;
use App\Domain\Catalog\ProductStatus;
use App\Domain\Recommendation\RecommendationService;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductDailyStat;
use App\Models\ProductRedirect;
use App\Models\Wishlist;
use App\Support\HashId;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function show(Request $request, RecommendationService $reco, CartService $cart, string $slug): Response|RedirectResponse
    {
        $product = Product::query()->where('slug', $slug)->first();

        if (! $product) {
            // Produk yang digabung dialihkan permanen, agar tautan WhatsApp lama tetap jalan (4.2).
            $redirect = ProductRedirect::query()->where('old_slug', $slug)->with('product')->first();
            abort_unless($redirect, 404);

            return redirect()->to(($request->segment(1) === 'en' ? '/en' : '').'/produk/'.$redirect->product->slug, 301);
        }

        if ($product->status !== ProductStatus::Aktif) {
            return Inertia::render('shop/product-unavailable', ['name' => $product->name])->toResponse($request)->setStatusCode(410);
        }

        ProductDailyStat::query()->firstOrCreate(['product_id' => $product->id, 'date' => now()->toDateString()]);
        DB::table('product_daily_stats')->where('product_id', $product->id)->where('date', now()->toDateString())->increment('views');

        $user = $request->user();
        $reviews = $product->reviews()->where('is_hidden', false)->with('user')->latest('id')->limit(10)->get();
        $distribution = $product->reviews()->where('is_hidden', false)->selectRaw('rating, count(*) as total')->groupBy('rating')->pluck('total', 'rating');

        return Inertia::render('shop/product', [
            'product' => Present::productDetail($product),
            'reviews' => $reviews->map(fn ($r) => Present::review($r)),
            'ratingDistribution' => $distribution,
            'related' => Present::productCards($reco->relatedToProduct($product)),
            'forYou' => Inertia::defer(fn () => Present::productCards($reco->relatedToSearches($user?->id, $request->session()->getId(), 4))),
            'wishlisted' => $user ? Wishlist::query()->where('user_id', $user->id)->where('product_id', $product->id)->exists() : false,
            // Titik 1 (4.4): pemilih jumlah = stok tersedia dikurangi jumlah yang sudah di keranjang.
            'inCart' => collect($cart->rawItems($user, CartController::guestCart($request)))->mapWithKeys(fn ($row) => [HashId::encode($row['variant_id']) => $row['qty']]),
            'shareUrl' => url('/produk/'.$product->slug),
        ]);
    }
}
