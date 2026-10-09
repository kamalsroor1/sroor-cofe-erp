<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Plan;

/**
 * A countable resource a plan limits (ENTI-2.2).
 *
 * The value is the stable key used everywhere a limit travels: EntitlementsDTO::$limits,
 * `addons.bundled_limits` ({"stores": 1, "users": 1}), the `limit` of
 * PlanLimitExceededException and the `subscription.limit_resources.*` labels.
 * planColumn() is the matching nullable column on central `plans` (NULL = unlimited).
 *
 * Never rename or remove a value: add-on rows store these keys.
 */
enum LimitResource: string
{
    case Users = 'users';
    case Stores = 'stores';
    case Warehouses = 'warehouses';
    case Vans = 'vans';
    case Items = 'items';
    case InvoicesMonth = 'invoices_month';
    case StorageMb = 'storage_mb';

    /** The `plans` column holding this limit (one of Plan::LIMIT_COLUMNS). */
    public function planColumn(): string
    {
        return match ($this) {
            self::Users => 'max_users',
            self::Stores => 'max_stores',
            self::Warehouses => 'max_warehouses',
            self::Vans => 'max_vans',
            self::Items => 'max_items',
            self::InvoicesMonth => 'max_invoices_per_month',
            self::StorageMb => 'max_storage_mb',
        };
    }

    /** The plan's own value for this limit; null = unlimited. */
    public function fromPlan(Plan $plan): ?int
    {
        $value = $plan->getAttribute($this->planColumn());

        return $value === null ? null : (int) $value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
