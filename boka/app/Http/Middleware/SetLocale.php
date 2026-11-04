<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman konten memakai bahasa dari URL (ADR-9): "/" Indonesia, "/en/..." Inggris.
 * Halaman transaksi (keranjang, checkout, akun, panel) memakai pilihan Pembeli atau cookie.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('boka.locales');

        $locale = match (true) {
            // Aplikasi mobile mengirim Accept-Language (bagian 10).
            $request->is('api/*') => $request->getPreferredLanguage($supported),
            $request->segment(1) === 'en' => 'en',
            (bool) $request->route()?->getAction('localized') => 'id',
            default => $request->user()?->locale ?? $request->cookie('locale'),
        };

        app()->setLocale(in_array($locale, $supported, true) ? $locale : 'id');

        return $next($request);
    }
}
