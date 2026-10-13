<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

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
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        /*
         * Stamp every activity row with the request origin (IP address and
         * user agent) so the audit trail doubles as a forensic record of
         * where each action came from. This fires for model events logged
         * through the LogsActivity trait, manual activity() calls (auth
         * events) and anything logged in the future.
         */
        Activity::creating(function (Activity $activity): void {
            $activity->properties = collect($activity->properties)->merge([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        /*
         * Named rate limiters, referenced per-route as `throttle:<name>`
         * (never as a blanket group) so any single route can be given its
         * own limit later. Throttled requests receive a standard 429.
         */
        RateLimiter::for('auth', function (Request $request): Limit {
            // Login and registration: strict per-IP cap to blunt brute
            // force, credential stuffing and signup spam from one host.
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('session', function (Request $request): Limit {
            // Token refresh and logout: generous per-IP cap — a user with
            // several open tabs stays far below this.
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request): Limit {
            // Password reset links: tiny per-IP cap to stop email bombing
            // and reset-token guessing.
            return Limit::perMinute(6)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request): Limit {
            // Safety net for the authenticated API, keyed per user so one
            // busy admin can never starve another (fall back to the IP for
            // token-less requests).
            return Limit::perMinute(300)->by(
                $request->user()?->getAuthIdentifier() ?? 'ip:'.$request->ip(),
            );
        });
    }
}
