<?php

namespace App\Providers;

use App\Policies\RolePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Role::class, RolePolicy::class);

        // FHIR R4 API rate limit: 60 requests per minute per Sanctum token.
        RateLimiter::for('fhir', function (Request $request): Limit {
            $tokenId = $request->user()?->currentAccessToken()?->id;
            $key = $tokenId ? "fhir-token:{$tokenId}" : 'fhir-ip:'.$request->ip();

            return Limit::perMinute(60)->by($key);
        });
    }
}
