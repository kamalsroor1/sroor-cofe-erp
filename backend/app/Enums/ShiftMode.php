<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * SHIFT-01 (owner decision 2026-11-21): how a branch runs its cash shifts
 * (`store_pos_settings.shift_mode`, copied onto every `cash_shifts.shift_mode` when it opens).
 *
 *  - per_cashier (default): every cashier opens their own shift (their own drawer); at most one
 *    open shift per (branch, user). A sale, voucher, expense or return belongs to the open shift
 *    of the user who wrote it.
 *  - shared: one open shift per branch, shared by all its cashiers (the SHOP-06 behaviour).
 *
 * Never rename a value: it is stored.
 */
enum ShiftMode: string
{
    case PerCashier = 'per_cashier';
    case Shared = 'shared';

    public const DEFAULT = self::PerCashier;

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function isPerCashier(): bool
    {
        return $this === self::PerCashier;
    }
}
