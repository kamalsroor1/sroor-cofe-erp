<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Single source of truth for which `stock_movements.movement_type` values add to or remove from
 * stock. The movements ledger, its A4 print and its CSV export all read from here, so a new type
 * (e.g. the PRD-06 `opening_balance_in`) is counted identically everywhere.
 */
final class StockMovementTypes
{
    /** @var list<string> */
    public const INBOUND = [
        'purchase_in', 'stock_deposit_in', 'opening_balance_in', 'stock_adjustment_in',
        'cancellation_in', 'transfer_in', 'sales_return_in', 'purchase_restore_in',
    ];

    /** @var list<string> */
    public const OUTBOUND = [
        'sales_out', 'waste_out', 'stock_adjustment_out',
        'transfer_out', 'purchase_cancel_out', 'purchase_return_out',
    ];

    /** @var list<string> */
    public const ADJUSTMENTS = ['stock_adjustment_in', 'stock_adjustment_out', 'stock_deposit_in'];

    public static function isInbound(string $type): bool
    {
        return in_array($type, self::INBOUND, true);
    }

    public static function isOutbound(string $type): bool
    {
        return in_array($type, self::OUTBOUND, true);
    }
}
