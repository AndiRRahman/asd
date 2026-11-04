<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Shipping\RajaOngkirClient;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use App\Support\HashId;
use App\Support\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Buku alamat, wishlist, unduh data pribadi (UU PDP), dan preferensi bahasa/tema.
 */
class AccountController extends Controller
{
    public function addresses(Request $request): Response
    {
        return Inertia::render('shop/account/addresses', ['addresses' => $request->user()->addresses()->get()->map(fn ($a) => Present::address($a))]);
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validateAddress($request);
        $address = $user->addresses()->create([...$data, 'is_default' => $data['is_default'] ?? ! $user->addresses()->exists()]);
        $this->syncDefault($address);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('address.saved')]);

        return back();
    }

    public function updateAddress(Request $request, string $id): RedirectResponse
    {
        $address = $request->user()->addresses()->findOrFail(HashId::decodeOrFail($id));
        $address->update($this->validateAddress($request));
        $this->syncDefault($address);

        return back();
    }

    public function destroyAddress(Request $request, string $id): RedirectResponse
    {
        $request->user()->addresses()->findOrFail(HashId::decodeOrFail($id))->delete();

        return back();
    }

    /** Kecamatan dipilih dari daftar tujuan penyedia ongkir, bukan diketik bebas (bagian 3.2). */
    public function destinations(Request $request, RajaOngkirClient $client): JsonResponse
    {
        $q = (string) $request->validate(['q' => ['nullable', 'string', 'max:60']])['q'];

        return response()->json($client->searchDestinations($q));
    }

    public function wishlist(Request $request): Response
    {
        $products = Product::query()
            ->whereIn('id', $request->user()->wishlists()->pluck('product_id'))
            ->with(['cover', 'producer', 'variants'])
            ->get();

        return Inertia::render('shop/account/wishlist', ['products' => Present::productCards($products)]);
    }

    public function toggleWishlist(Request $request, Product $product): RedirectResponse
    {
        $existing = Wishlist::query()->where('user_id', $request->user()->id)->where('product_id', $product->id)->first();
        $existing ? $existing->delete() : Wishlist::query()->create(['user_id' => $request->user()->id, 'product_id' => $product->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __($existing ? 'wishlist.removed' : 'wishlist.added')]);

        return back();
    }

    /** Pembeli bisa mengunduh datanya (UU PDP). */
    public function exportData(Request $request): JsonResponse
    {
        $user = $request->user()->load(['addresses', 'orders.items']);

        return response()->json([
            'profil' => $user->only(['name', 'email', 'phone', 'locale', 'theme', 'created_at']),
            'alamat' => $user->addresses,
            'pesanan' => $user->orders->map(fn ($o) => Present::orderSummary($o)),
        ])->header('Content-Disposition', 'attachment; filename="data-saya-boka.json"');
    }

    public function locale(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', Rule::in(config('boka.locales'))], 'redirect' => ['nullable', 'string', 'max:300']]);
        $request->user()?->forceFill(['locale' => $data['locale']])->save();
        cookie()->queue('locale', $data['locale'], 60 * 24 * 365, httpOnly: false);

        $path = '/'.ltrim(preg_replace('#^/en(/|$)#', '/', parse_url($data['redirect'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
        $query = parse_url($data['redirect'] ?? '', PHP_URL_QUERY);
        $target = ($data['locale'] === 'en' && $this->isLocalized($path) ? '/en'.rtrim($path, '/') : $path).($query ? '?'.$query : '');

        return redirect($target ?: '/');
    }

    public function theme(Request $request): RedirectResponse
    {
        $data = $request->validate(['theme' => ['required', Rule::in(['light', 'dark'])]]);
        $request->user()?->forceFill(['theme' => $data['theme']])->save();

        return back();
    }

    private function isLocalized(string $path): bool
    {
        return $path === '/' || preg_match('#^/(produk|kategori|produsen|kabar|halaman|rekening-resmi)#', $path) === 1;
    }

    private function validateAddress(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'recipient' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^(\+62|0)8[0-9]{7,12}$/'],
            'destination_id' => ['required', 'string', 'max:60'],
            'district' => ['required', 'string', 'max:80'],
            'destination_label' => ['required', 'string', 'max:200'],
            'full_address' => ['required', 'string', 'max:500'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    private function syncDefault($address): void
    {
        if ($address->is_default) {
            $address->user->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
        }
    }
}
