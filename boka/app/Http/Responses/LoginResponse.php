<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

/**
 * Tujuan setelah login: staf ke panel Admin, Pembeli ke beranda toko.
 * Halaman yang tadinya dituju (misalnya checkout) tetap dihormati selama sesuai perannya.
 */
class LoginResponse implements LoginResponseContract, TwoFactorLoginResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        return redirect()->to(self::destination($request));
    }

    public static function destination(Request $request): string
    {
        $intended = (string) $request->session()->pull('url.intended', '');
        $path = '/'.ltrim((string) parse_url($intended, PHP_URL_PATH), '/');

        if ($request->user()?->can('panel.akses')) {
            return Str::startsWith($path, '/panel') ? $intended : '/panel';
        }

        return $intended !== '' && ! Str::startsWith($path, '/panel') ? $intended : '/';
    }
}
