<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Product;
use App\Support\Present;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $products = Product::query()
            ->active()
            ->with(['cover', 'producer', 'variants'])
            ->latest('id')
            ->limit(16)
            ->get();

        return Inertia::render('shop/home', [
            'banners' => Banner::query()->live()->where('position', 'hero')->get()->map(fn (Banner $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'subtitle' => $b->subtitle,
                'link' => $b->link,
                'image_desktop' => $b->image_desktop,
                'image_mobile' => $b->image_mobile,
            ]),
            'products' => Present::productCards($products),
        ]);
    }
}
