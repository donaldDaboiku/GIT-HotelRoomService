<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by('login:'.$request->ip()));
        RateLimiter::for('guest-session', fn (Request $request) => Limit::perMinute(20)->by('guest-session:'.$request->ip()));
        RateLimiter::for('guest-submit', function (Request $request) {
            $token = (string) $request->header('X-Guest-Token', 'none');

            return Limit::perMinute(10)->by('guest-submit:'.sha1($token).'|'.$request->ip());
        });
        RateLimiter::for('guest-read', fn (Request $request) => Limit::perMinute(120)->by('guest-read:'.$request->ip()));
    }
}
