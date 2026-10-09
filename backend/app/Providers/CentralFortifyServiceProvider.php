<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Central\TwoFactor\TwoFactorChallengeStore;
use App\Support\RateLimitKey;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider as TwoFactorAuthenticationProviderContract;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

/**
 * laravel/fortify, headless, for platform operators only (IDEN-1.12).
 *
 * Fortify is excluded from auto-discovery and Laravel\Fortify\FortifyServiceProvider is
 * deliberately NOT registered: it would load Fortify's session routes, passkey config and
 * a global StatefulGuard binding. This provider takes only what the central 2FA flow needs:
 *
 *  - the TOTP provider (Fortify's TwoFactorAuthenticationProvider on pragmarx/google2fa,
 *    with its replay cache) used by Fortify's Enable/Confirm/GenerateNewRecoveryCodes
 *    actions and by App\Actions\Central\TwoFactor\*;
 *  - the `central_users` password broker pinned to the central connection;
 *  - the rate limiters of the central 2FA and password-reset endpoints.
 */
final class CentralFortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();

        $this->app->singleton(TwoFactorAuthenticationProviderContract::class, static fn ($app): TwoFactorAuthenticationProvider => new TwoFactorAuthenticationProvider(
            $app->make(Google2FA::class),
            $app->make(Repository::class),
        ));
    }

    public function boot(): void
    {
        // config/auth.php is loaded before config/tenancy.php, so the broker's connection is
        // pinned here: reset tokens must never land in a tenant database.
        config([
            'auth.passwords.central_users.connection' => config('tenancy.database.central_connection', config('database.default')),
        ]);

        $this->registerRateLimiters();
    }

    private function registerRateLimiters(): void
    {
        // Public challenge (keyed by challenge id) and authenticated 2FA endpoints (keyed by
        // the bearer token's operator). Both share a per-IP ceiling.
        RateLimiter::for('central-two-factor', function (Request $request): array {
            // The public challenge route has no operator yet; never touch the default
            // (session) guard there.
            $subject = $request->routeIs('central.auth.two-factor-challenge')
                ? 'challenge:'.hash('sha256', RateLimitKey::identifier($request->input('challenge_id')))
                : 'user:'.(string) $request->user()?->getAuthIdentifier();

            $limits = [
                Limit::perMinute($this->limit('central.rate_limits.two_factor_per_ip_per_minute', 10))
                    ->by('central-2fa-ip|'.$request->ip()),
                Limit::perMinute($this->limit('central.rate_limits.two_factor_per_subject_per_minute', 5))
                    ->by('central-2fa-subject|'.$subject),
            ];

            // Long window per OPERATOR across the challenge, step-up and 2FA management endpoints:
            // a new challenge (fresh login) or another IP must not reset the brute-force budget.
            $operatorId = $this->twoFactorOperatorId($request);
            if ($operatorId !== null) {
                $limits[] = Limit::perHour($this->limit('central.rate_limits.two_factor_per_user_per_hour', 20))
                    ->by('central-2fa-user-hour|'.$operatorId);
            }

            return $limits;
        });

        // Forgot / reset password: per IP, plus an hourly per-email cap that ignores the IP.
        RateLimiter::for('central-password-reset', function (Request $request): array {
            $email = RateLimitKey::identifier($request->input('email'));

            return [
                Limit::perMinute($this->limit('central.rate_limits.password_reset_per_ip_per_minute', 5))
                    ->by('central-pwreset-ip|'.$request->ip()),
                Limit::perHour($this->limit('central.rate_limits.password_reset_per_email_per_hour', 5))
                    ->by('central-pwreset-email|'.$email),
            ];
        });
    }

    /**
     * The operator a 2FA request belongs to: the bearer token's operator on authenticated
     * routes, the challenge's operator (read without consuming it) on the public challenge route.
     */
    private function twoFactorOperatorId(Request $request): ?string
    {
        if ($request->routeIs('central.auth.two-factor-challenge')) {
            $challengeId = $request->input('challenge_id');
            $userId = is_string($challengeId) ? app(TwoFactorChallengeStore::class)->peekUserId($challengeId) : null;

            return $userId === null ? null : (string) $userId;
        }

        $id = $request->user()?->getAuthIdentifier();

        return $id === null ? null : (string) $id;
    }

    /** A positive limit from config (a misconfigured 0 or negative never disables a limiter). */
    private function limit(string $key, int $default): int
    {
        $value = (int) config($key, $default);

        return $value > 0 ? $value : $default;
    }
}
