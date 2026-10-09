<?php

declare(strict_types=1);

namespace App\Actions\Central\Data;

use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Password;

/**
 * Typed readers of config/central.php for the operator auth flow (IDEN-1.12).
 *
 *  - fullAbility():  `central:*`, every central route (AuthenticateCentral default);
 *  - setupAbility(): `central:2fa-setup`, ONLY the 2FA enable / confirm / recovery-codes
 *    routes (AuthenticateCentral:setup);
 *  - stepUpTtlMinutes(): how long a 2FA proof on a token satisfies RequireRecentTwoFactor;
 *  - passwordBroker(): the `central_users` broker (Fortify's config('fortify.passwords')).
 */
final class CentralAuthSettings
{
    public static function fullAbility(): string
    {
        return (string) config('central.token_ability', 'central:*');
    }

    public static function setupAbility(): string
    {
        return (string) config('central.two_factor_setup_ability', 'central:2fa-setup');
    }

    public static function stepUpTtlMinutes(): int
    {
        $minutes = (int) config('central.step_up_ttl_minutes', 15);

        return $minutes > 0 ? $minutes : 15;
    }

    public static function passwordBroker(): PasswordBroker
    {
        return Password::broker((string) config('fortify.passwords', 'central_users'));
    }
}
