<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Cart\CartService;
use App\Domain\Checkout\CreateOrder;
use App\Domain\Checkout\PriceCalculator;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\BankAccount;
use App\Models\User;
use App\Support\HashId;
use App\Support\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Checkout satu halaman (bagian 3.5). Rincian biaya selalu dihitung ulang di server.
 * Mendukung pesan dengan akun Pembeli maupun pesan langsung tanpa login (Tamu).
 */
class CheckoutController extends Controller
{
    public function __construct(private CartService $cart, private PriceCalculator $calculator) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $lines = $this->cart->lines($this->cart->rawItems($user, CartController::guestCart($request)));

        if ($lines->isEmpty() || $lines->contains(fn ($l) => $l['issue'] !== null)) {
            return redirect('/keranjang');
        }

        $addresses = $user ? $user->addresses()->get() : collect();
        $default = $addresses->first();

        return Inertia::render('shop/checkout', [
            'addresses' => $addresses->map(fn ($a) => Present::address($a)),
            'banks' => BankAccount::query()->usable()->get()->map(fn ($b) => ['id' => $b->hashid, ...$b->only(['bank_name', 'account_number', 'account_holder'])]),
            'quote' => Present::quote($this->calculator->quote($user, $lines, $default)),
            'defaultAddressId' => $default?->hashid,
            'idempotencyKey' => (string) str()->uuid(),
            'isGuest' => $user === null,
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        $data = $this->validated($request, false);
        $user = $request->user();
        $address = null;

        if (! empty($data['address_id'])) {
            $address = Address::query()->when($user, fn ($q) => $q->where('user_id', $user->id))->find($data['address_id']);
        } elseif (! empty($data['destination_id'])) {
            $address = new Address([
                'destination_id' => $data['destination_id'],
                'district' => $data['district'] ?? '',
                'destination_label' => $data['destination_label'] ?? '',
                'recipient' => $data['guest_name'] ?? 'Tamu',
                'phone' => $data['guest_phone'] ?? '',
                'full_address' => $data['guest_address'] ?? '',
            ]);
        }

        $lines = $this->cart->lines($this->cart->rawItems($user, CartController::guestCart($request)));

        $quote = $this->calculator->quote(
            $user,
            $lines,
            $address,
            $data['shipping'] ?? [],
            $data['voucher_code'] ?? null,
            $data['shipping_voucher_code'] ?? null,
        );

        return response()->json(Present::quote($quote));
    }

    public function store(Request $request, CreateOrder $create): RedirectResponse
    {
        $user = $request->user();
        $isGuest = $user === null;

        $rules = [
            'shipping' => ['array'],
            'shipping.*' => ['string', 'max:80'],
            'bank_account_id' => ['required', 'string'],
            'voucher_code' => ['nullable', 'string', 'max:32'],
            'shipping_voucher_code' => ['nullable', 'string', 'max:32'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];

        if ($isGuest) {
            $rules['guest_name'] = ['required', 'string', 'max:120'];
            $rules['guest_phone'] = ['required', 'string', 'regex:/^(\+62|0)8[0-9]{7,12}$/'];
            $rules['guest_email'] = ['nullable', 'email', 'max:120'];
            $rules['destination_id'] = ['required', 'string', 'max:60'];
            $rules['district'] = ['required', 'string', 'max:80'];
            $rules['destination_label'] = ['nullable', 'string', 'max:200'];
            $rules['guest_address'] = ['required', 'string', 'max:500'];
            $rules['postal_code'] = ['nullable', 'string', 'max:10'];
        } else {
            $rules['address_id'] = ['required', 'string'];
        }

        $data = self::decodeIds($request->validate($rules));

        if ($isGuest) {
            $email = ! empty($data['guest_email'])
                ? $data['guest_email']
                : 'tamu-'.strtolower(str()->random(8)).'@boka.test';

            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $data['guest_name'],
                    'phone' => $data['guest_phone'],
                    'password' => bcrypt(str()->random(24)),
                    'email_verified_at' => now(),
                ]
            );

            if (! $user->hasRole('pembeli')) {
                $user->assignRole('pembeli');
            }

            $address = $user->addresses()->create([
                'label' => 'Alamat Pengiriman',
                'recipient' => $data['guest_name'],
                'phone' => $data['guest_phone'],
                'destination_id' => $data['destination_id'],
                'district' => $data['district'],
                'destination_label' => $data['destination_label'] ?? $data['district'],
                'full_address' => $data['guest_address'],
                'postal_code' => $data['postal_code'] ?? null,
                'is_default' => true,
            ]);

            $data['address_id'] = $address->id;

            // Pindahkan item keranjang tamu ke keranjang akun pengguna
            $guestItems = CartController::guestCart($request);
            if (! empty($guestItems)) {
                $this->cart->mergeGuest($user, $guestItems);
                Cookie::queue(Cookie::forget(CartController::COOKIE));
            }

            auth()->login($user);
        }

        $order = $create->handle($user, $data, $data['idempotency_key']);
        $request->session()->push('guest_orders', $order->code);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('checkout.created')]);

        return redirect('/pesanan/'.$order->code);
    }

    private function validated(Request $request, bool $strict): array
    {
        return self::decodeIds($request->validate([
            'address_id' => ['nullable', 'string'],
            'destination_id' => ['nullable', 'string', 'max:60'],
            'district' => ['nullable', 'string', 'max:80'],
            'destination_label' => ['nullable', 'string', 'max:200'],
            'guest_name' => ['nullable', 'string', 'max:120'],
            'guest_phone' => ['nullable', 'string', 'max:20'],
            'guest_address' => ['nullable', 'string', 'max:500'],
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
