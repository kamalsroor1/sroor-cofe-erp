<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a tenant was created (central `tenants.signup_source`, PLAN-10).
 *
 *  - SelfSignup: the shop owner filled the public signup form (today approved from the console,
 *    SIGN-12 makes it automatic). When such a trial ends unpaid, the tenant falls back to the
 *    platform's `trial_fallback_plan_id` (a permanent free plan) instead of going read-only.
 *  - Console: an operator created the tenant by hand (POST /super-admin/tenants). Its trial keeps
 *    the grace -> read-only path.
 *
 * The column defaults to `console`; a NULL read (an unrefreshed model) is treated as console.
 * Never rename a value: it is stored.
 */
enum TenantSignupSource: string
{
    case SelfSignup = 'self_signup';
    case Console = 'console';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
