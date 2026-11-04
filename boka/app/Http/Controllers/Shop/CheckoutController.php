<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Cart\CartService;
use App\Domain\Checkout\CreateOrder;
use App\Domain\Checkout\PriceCalculator;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\BankAccount;
use App\Support\HashId;
use App\Support\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Checkout satu halaman (bagian 3.5). Rincian biaya selalu dihitung ulang di server.
 */
class CheckoutController extends Controller
{
    public function __construct(private CartService $cart, private PriceCalculator $calculator) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $lines = $this->cart->lines($this->cart->rawItems($user));

        if ($lines->isEmpty() || $lines->contains(fn ($l) => $l['issue'] !== null)) {
            return redirect('/keranjang');
        }

        $addresses = $user->addresses()->get();
        $default = $addresses->first();

        return Inertia::render('shop/checkout', [
            'addresses' => $addresses->map(fn ($a) => Present::address($a)),
            'banks' => BankAccount::query()->usable()->get()->map(fn ($b) => ['id' => $b->hashid, ...$b->only(['bank_name', 'account_number', 'account_holder'])]),
            'quote' => Present::quote($this->calculator->quote($user, $lines, $default)),
            'defaultAddressId' => $default?->hashid,
            'idempotencyKey' => (string) str()->uuid(),
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        $data = $this->validated($request, false);
        $user = $request->user();
        $address = Address::query()->where('user_id', $user->id)->find($data['address_id'] ?? null);

        $quote = $this->calculator->quote(
            $user,
            $this->cart->lines($this->cart->rawItems($user)),
            $address,
            $data['shipping'] ?? [],
            $data['voucher_code'] ?? null,
            $data['shipping_voucher_code'] ?? null,
        );

        return response()->json(Present::quote($quote));
    }

    public function store(Request $request, CreateOrder $create): RedirectResponse
    {
        $data = $this->validated($request, true);
        $order = $create->handle($request->user(), $data, $data['idempotency_key']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('checkout.created')]);

        return redirect('/pesanan/'.$order->code);
    }

    private function validated(Request $request, bool $strict): array
    {
        return self::decodeIds($request->validate([
            'address_id' => [$strict ? 'required' : 'nullable', 'string'],
            'shipping' => ['array'],
            'shipping.*' => ['string', 'max:80'],
            'bank_account_id' => [$strict ? 'required' : 'nullable', 'string'],
            'voucher_code' => ['nullable', 'string', 'max:32'],
            'shipping_voucher_code' => ['nullable', 'string', 'max:32'],
            'idempotency_key' => [$strict ? 'required' : 'nullable', 'string', 'max:64'],
        ]));
    }

    /** Alamat, rekening, dan titik asal datang sebagai HashID dari browser atau aplikasi mobile. */
    public static function decodeIds(array $data): array
    {
        $data['address_id'] = HashId::decode($data['address_id'] ?? null);
        $data['bank_account_id'] = HashId::decode($data['bank_account_id'] ?? null);
        $data['shipping'] = HashId::decodeKeys($data['shipping'] ?? []);

        return $data;
    }
}
