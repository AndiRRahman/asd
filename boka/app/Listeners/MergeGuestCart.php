<?php

namespace App\Listeners;

use App\Domain\Cart\CartService;
use App\Http\Controllers\Shop\CartController;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cookie;

/**
 * Keranjang Tamu di browser digabung ke keranjang akun saat login (bagian 3.4).
 */
class MergeGuestCart
{
    public function __construct(private CartService $cart) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $guest = CartController::guestCart(request());

        if ($guest !== []) {
            $this->cart->mergeGuest($user, $guest);
            Cookie::queue(Cookie::forget(CartController::COOKIE));
        }
    }
}
