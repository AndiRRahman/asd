<?php

namespace App\Providers;

use App\Domain\Promotion\PriceResolver;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Satu cache flash sale per permintaan.
        $this->app->scoped(PriceResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Super Admin memegang semua izin. Batasannya (tidak melihat password, tidak menghapus log audit)
        // dijaga karena fitur itu memang tidak ada di antarmuka.
        Gate::before(fn (User $user) => $user->hasRole('super_admin') ? true : null);

        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        // Batas umum aksi tulis (POST/PUT/PATCH/DELETE) di web, untuk menahan spam dan serangan otomatis.
        RateLimiter::for('writes', fn (Request $request) => $request->isMethodSafe()
            ? Limit::none()
            : Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('proof-upload', fn (Request $request) => Limit::perHour(10)->by($request->user()?->id ?: $request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
