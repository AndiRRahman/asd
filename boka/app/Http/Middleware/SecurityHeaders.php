<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan HTTP (bagian 11). Content-Security-Policy hanya mengizinkan script dari situs ini atau
 * yang membawa nonce acak per permintaan, sehingga script sisipan (XSS) tidak dijalankan browser.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Nonce dibuat sebelum halaman dirender agar tag <script> Vite dan inline ikut membawanya.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        // Halaman boleh dibingkai oleh situs sendiri saja (pratinjau PDF bukti transfer di panel).
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->remove('X-Powered-By');
        header_remove('X-Powered-By');

        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Saat `npm run dev`, script datang dari server Vite; CSP dipasang hanya untuk build biasa.
        if (! Vite::isRunningHot() && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: blob:",
                "font-src 'self' data:",
                "connect-src 'self'",
                "frame-src 'self'",
                "frame-ancestors 'self'",
                "form-action 'self'",
                "base-uri 'self'",
                "object-src 'none'",
            ]));
        }

        return $response;
    }
}
