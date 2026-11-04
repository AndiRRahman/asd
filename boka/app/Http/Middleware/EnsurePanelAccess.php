<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel hanya untuk staf, wajib 2FA TOTP, dan sesi berakhir setelah 2 jam tidak aktif (bagian 11).
 */
class EnsurePanelAccess
{
    private const IDLE_MINUTES = 120;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->can('panel.akses') || $user->status !== 'aktif') {
            abort(403, 'Halaman ini khusus Admin.');
        }

        $lastActivity = $request->session()->get('panel_last_activity');

        if ($lastActivity && now()->timestamp - $lastActivity > self::IDLE_MINUTES * 60) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('login')->with('status', 'Sesi panel berakhir karena tidak aktif selama 2 jam. Silakan masuk lagi.');
        }

        $request->session()->put('panel_last_activity', now()->timestamp);

        if (config('boka.two_factor') && ! $user->two_factor_confirmed_at) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Aktifkan autentikasi dua langkah (2FA) sebelum membuka panel.']);

            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
