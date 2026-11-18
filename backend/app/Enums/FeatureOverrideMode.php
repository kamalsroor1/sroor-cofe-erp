<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * PLAN-12: how a per-shop feature override acts on what the plan / add-ons give.
 * Stored in `tenant_feature_overrides.mode`; never rename a value.
 */
enum FeatureOverrideMode: string
{
    /** The feature is on for this shop even when plan and add-ons do not include it. */
    case Grant = 'grant';
    /** The feature is off for this shop even when plan or add-ons include it. */
    case Revoke = 'revoke';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
