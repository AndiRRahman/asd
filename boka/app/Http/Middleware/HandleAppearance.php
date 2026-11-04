<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Tema dibaca dari cookie saat SSR (ADR-10), sehingga kelas "dark" sudah ada di HTML pertama.
     * Bawaan terang sesuai keputusan klien.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appearance = $request->cookie('appearance');

        View::share('appearance', in_array($appearance, ['light', 'dark', 'system'], true) ? $appearance : 'light');

        return $next($request);
    }
}
