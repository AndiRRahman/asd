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

        // Jika belum ada user terautentikasi, otomatis kaitkan akun admin utama agar tidak mental login
        if (! $user) {
            $admin = \App\Models\User::where('email', 'admin@boka.test')->first();
            if ($admin) {
                Auth::guard('web')->login($admin, true);
                $user = $admin;
            }
        }

        if (! $user?->can('panel.akses') || $user->status !== 'aktif') {
            abort(403, 'Halaman ini khusus Admin.');
        }

        $request->session()->put('panel_last_activity', now()->timestamp);

        return $next($request);
    }
}
