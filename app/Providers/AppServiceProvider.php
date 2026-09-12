<?php

namespace App\Providers;

use App\Models\AudioConversion;
use App\Policies\AudioConversionPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        // Ownership of a conversion is decided in exactly one place.
        Gate::policy(AudioConversion::class, AudioConversionPolicy::class);

        $this->registerRateLimiters();
    }

    /**
     * Per-endpoint buckets so a busy poller cannot starve a login attempt.
     * The limits are read per request, so they can be tuned without a deploy.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute((int) config('audio.rate_limits.login'))
                ->by('login:'.strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        // Logged-in traffic is limited per user, with the IP as a second key so
        // one client cannot exhaust another's budget on a shared account.
        $forUser = fn (string $bucket) => function (Request $request) use ($bucket) {
            return Limit::perMinute((int) config("audio.rate_limits.{$bucket}"))
                ->by($bucket.':'.$request->user()?->id.'|'.$request->ip());
        };

        RateLimiter::for('upload', fn (Request $request) => Limit::perMinute(
            (int) config('audio.rate_limits.conversion') * 3
        )->by('upload:'.$request->user()?->id.'|'.$request->ip()));

        RateLimiter::for('conversion', $forUser('conversion'));
        RateLimiter::for('status', $forUser('status'));
        RateLimiter::for('download', $forUser('download'));
    }
}


