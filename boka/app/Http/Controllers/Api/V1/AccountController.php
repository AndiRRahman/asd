<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\AnonymizeUser;
use App\Domain\Shipping\RajaOngkirClient;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use App\Support\HashId;
use App\Support\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => AuthController::user($request->user())]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'string', 'regex:/^(\+62|0)8[0-9]{7,12}$/', Rule::unique('users')->ignore($user)],
            'locale' => ['sometimes', Rule::in(config('boka.locales'))],
            'theme' => ['sometimes', 'in:light,dark'],
        ]);
        $user->update($data);

        return $this->me($request);
    }

    /** Hapus akun menganonimkan identitas; pesanan tetap sebagai catatan keuangan (UU PDP). */
    public function destroy(Request $request, AnonymizeUser $anonymize): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password:sanctum']]);
        $anonymize->handle($request->user());

        return response()->json(['message' => 'Akun dihapus.']);
    }

    public function addresses(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->addresses()->get()->map(fn ($a) => Present::address($a))]);
    }

    public function storeAddress(Request $request): JsonResponse
    {
        $address = $request->user()->addresses()->create($this->validateAddress($request));

        return response()->json(['data' => Present::address($address)], 201);
    }

    public function updateAddress(Request $request, string $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail(HashId::decodeOrFail($id));
        $address->update($this->validateAddress($request, partial: true));

        return response()->json(['data' => Present::address($address)]);
    }

    public function destroyAddress(Request $request, string $id): JsonResponse
    {
        $request->user()->addresses()->findOrFail(HashId::decodeOrFail($id))->delete();

        return response()->json(status: 204);
    }

    public function destinations(Request $request, RajaOngkirClient $client): JsonResponse
    {
        return response()->json(['data' => $client->searchDestinations((string) $request->query('q', ''))]);
    }

    public function wishlist(Request $request): JsonResponse
    {
        return response()->json(['data' => Present::productCards(Product::query()->whereIn('id', $request->user()->wishlists()->pluck('product_id'))->with(['cover', 'producer', 'variants'])->get())]);
    }

    public function addWishlist(Request $request, Product $product): JsonResponse
    {
        Wishlist::query()->firstOrCreate(['user_id' => $request->user()->id, 'product_id' => $product->id]);

        return response()->json(['message' => 'Ditambahkan ke wishlist.'], 201);
    }

    public function removeWishlist(Request $request, Product $product): JsonResponse
    {
        Wishlist::query()->where('user_id', $request->user()->id)->where('product_id', $product->id)->delete();

        return response()->json(status: 204);
    }

    public function notifications(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->notifications()->limit(50)->get(['id', 'data', 'read_at', 'created_at'])]);
    }

    public function readNotifications(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'Semua notifikasi ditandai sudah dibaca.']);
    }

    private function validateAddress(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'label' => [$required, 'string', 'max:50'], 'recipient' => [$required, 'string', 'max:120'],
            'phone' => [$required, 'string', 'regex:/^(\+62|0)8[0-9]{7,12}$/'], 'destination_id' => [$required, 'string', 'max:60'],
            'district' => [$required, 'string', 'max:80'], 'destination_label' => [$required, 'string', 'max:200'],
            'full_address' => [$required, 'string', 'max:500'], 'postal_code' => ['nullable', 'string', 'max:10'], 'is_default' => ['nullable', 'boolean'],
        ]);
    }
}
