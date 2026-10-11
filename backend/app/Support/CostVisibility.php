<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * SEC-1 (M6): who may see purchase cost and cost-derived figures (cost_price, stock valuation,
 * cost fallbacks of the minimum selling price). One rule for every resource: the
 * `items.view_cost` permission (the tenant `admin` role passes through Gate::before).
 * Cashiers sell with the selling price only and never receive the cost.
 */
final class CostVisibility
{
    public const PERMISSION = 'items.view_cost';

    public static function allows(mixed $user): bool
    {
        return $user instanceof User && $user->can(self::PERMISSION);
    }
}
