<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // §7: rate-limit OTP and token endpoints per IP and per token.
        $perIpAndToken = fn (int $n) => fn (Request $r) => [
            Limit::perMinute($n)->by('ip:'.$r->ip()),
            Limit::perMinute($n)->by('token:'.hash('sha256', (string) $r->route('token'))),
        ];
        RateLimiter::for('vendor-otp', $perIpAndToken(10));
        RateLimiter::for('vendor-token', $perIpAndToken(30));
        // Autosave runs every 10 s plus on blur; allow headroom while still capping abuse.
        RateLimiter::for('vendor-draft', $perIpAndToken(60));
    }
}
