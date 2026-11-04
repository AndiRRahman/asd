<?php

namespace App\Http\Middleware;

use App\Domain\Cart\CartService;
use App\Http\Controllers\Shop\CartController;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Teks antarmuka dikirim lewat props bersama (bagian 12.7), jadi komponen React tidak menulis teks langsung.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'name' => config('boka.store.name'),
            'appUrl' => rtrim(config('app.url'), '/'),
            'store' => config('boka.store'),
            'auth' => [
                // Hanya kolom yang dipakai tampilan; ID disamarkan, kolom internal tidak dikirim.
                'user' => $user ? [
                    'id' => $user->hashid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                ] : null,
                'isStaff' => (bool) $user?->can('panel.akses'),
                'permissions' => $user?->getAllPermissions()->pluck('name') ?? [],
                'isSuperAdmin' => (bool) $user?->hasRole('super_admin'),
            ],
            'locale' => $locale,
            'locales' => config('boka.locales'),
            'translations' => fn () => $this->translations($locale),
            'cartCount' => fn () => app(CartService::class)->count($user, CartController::guestCart($request)),
            'navCategories' => fn () => Cache::remember("nav-categories:{$locale}", 600, fn () => Category::query()
                ->whereNull('parent_id')->orderBy('sort_order')->get()
                ->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug, 'icon' => $c->icon])->all()),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /** @return array<string, string> */
    private function translations(string $locale): array
    {
        static $loaded = [];

        return $loaded[$locale] ??= json_decode((string) file_get_contents(lang_path("{$locale}.json")), true) ?? [];
    }
}
