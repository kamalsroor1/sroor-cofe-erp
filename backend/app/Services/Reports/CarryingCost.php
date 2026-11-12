<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Item;
use App\Support\Money\Decimal;
use InvalidArgumentException;

/**
 * BUG-02: the ONE rule for the unit cost stock is carried (valued) at.
 *
 * The item's weighted average cost (`items.weighted_avg_cost`, one average per item across every
 * branch: owner decision), or its last purchase cost (`items.cost_price`) while no positive
 * average exists yet (zero / NULL). It is the cost the sales are costed at (InvoiceService), so the
 * stock valuation, the expected stock profit, the branch balances and the purchase average all
 * agree with the cost of goods sold. `cost_price` alone is the LAST purchase's landed cost, and
 * multiplying the whole stock by it revalued every unit on each purchase at a new price.
 *
 * Two forms of the same rule, keep them in step:
 *  - of() / forItem(): PHP, bcmath on decimal strings, scale 3;
 *  - sql(): a plain per-row CASE expression (no aggregate, no subquery), valid in a grouped or
 *    non-grouped statement on MySQL 8.4, MariaDB 11.8 and sqlite.
 */
final class CarryingCost
{
    /**
     * The carrying unit cost from the two stored figures: the average when > 0, else the last
     * purchase cost, else 0. Always a scale-3 decimal string.
     */
    public static function of(mixed $weightedAvgCost, mixed $costPrice): string
    {
        $average = self::decimal($weightedAvgCost);

        return bccomp($average, '0', 3) > 0 ? $average : self::decimal($costPrice);
    }

    public static function forItem(Item $item): string
    {
        return self::of($item->getAttribute('weighted_avg_cost'), $item->getAttribute('cost_price'));
    }

    /**
     * The same rule as a SQL expression on the given items table (or alias). The table name is
     * a code constant, never request input; it is still restricted to an identifier.
     */
    public static function sql(string $table = 'items'): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) !== 1) {
            throw new InvalidArgumentException('Invalid table identifier.');
        }

        return "(CASE WHEN {$table}.weighted_avg_cost > 0 THEN {$table}.weighted_avg_cost ELSE COALESCE({$table}.cost_price, 0) END)";
    }

    private static function decimal(mixed $value): string
    {
        return is_numeric($value) ? Decimal::normalize((string) $value) : '0.000';
    }
}
