<?php

namespace Modules\Integrator\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Modules\Integrator\Models\ApiKey;

class IntegratorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Named limiter available for route-level `throttle:integrator` usage.
        // The middleware already enforces the per-client limit; this limiter exists
        // so the same policy can be applied to any future key-authenticated route.
        RateLimiter::for('integrator', function (Request $request) {
            /** @var ApiKey|null $key */
            $key = $request->attributes->get('integrator.api_key');

            if (! $key) {
                return Limit::perMinute(30)->by($request->ip());
            }

            return Limit::perMinute(max(1, (int) ($key->client->rate_limit_per_minute ?? 120)))
                ->by('integrator:'.$key->getKey());
        });
    }
}
