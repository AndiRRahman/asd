<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Cart\CartService;
use App\Domain\Recommendation\RecommendationService;
use App\Http\Controllers\Controller;
use App\Support\HashId;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Keranjang Tamu disimpan di cookie browser (tidak dienkripsi, isinya hanya id varian dan jumlah;
 * server selalu memeriksa ulang). Keranjang Pembeli disimpan di database.
 */
class CartController extends Controller
{
    public const COOKIE = 'boka_cart';

    public function __construct(private CartService $cart) {}

    public static function guestCart(Request $request): array
    {
        if ($request->user()) {
            return [];
        }

        $decoded = json_decode((string) $request->cookie(self::COOKIE, '[]'), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function index(Request $request, RecommendationService $reco): Response
    {
        $lines = $this->cart->lines($this->cart->rawItems($request->user(), self::guestCart($request)));

        return Inertia::render('shop/cart', [
            'lines' => Present::cartLines($lines),
            'subtotal' => $lines->sum(fn ($l) => $l['unit_price'] * $l['qty']),
            'hasIssues' => $lines->contains(fn ($l) => $l['issue'] !== null),
            'packageCount' => $lines->pluck('variant.product.origin_id')->unique()->count(),
            'recommendations' => fn () => Present::productCards($reco->relatedToSearches($request->user()?->id, $request->session()->getId(), 4)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['variant_id' => ['required', 'string'], 'qty' => ['required', 'integer', 'min:1', 'max:9999']]);
        $guest = $this->cart->add($request->user(), self::guestCart($request), HashId::decodeOrFail($data['variant_id']), $data['qty']);

        return $this->respond($request, $guest, __('cart.added'));
    }

    public function update(Request $request, string $variant): RedirectResponse
    {
        $data = $request->validate(['qty' => ['required', 'integer', 'min:0', 'max:9999']]);

        return $this->respond($request, $this->cart->update($request->user(), self::guestCart($request), HashId::decodeOrFail($variant), $data['qty']));
    }

    public function destroy(Request $request, string $variant): RedirectResponse
    {
        return $this->respond($request, $this->cart->remove($request->user(), self::guestCart($request), HashId::decodeOrFail($variant)));
    }

    public function adjust(Request $request): RedirectResponse
    {
        return $this->respond($request, $this->cart->adjustToStock($request->user(), self::guestCart($request)), __('cart.adjusted'));
    }

    private function respond(Request $request, array $guest, ?string $message = null): RedirectResponse
    {
        if (! $request->user()) {
            Cookie::queue(self::COOKIE, json_encode($guest), 60 * 24 * 30, httpOnly: false);
        }

        if ($message) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
        }

        return back();
    }
}
