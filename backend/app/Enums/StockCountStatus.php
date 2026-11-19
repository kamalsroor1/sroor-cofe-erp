<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * SHOP-02: lifecycle of a stocktake session (`stock_counts.status`).
 *
 *  - draft:     created, snapshot taken, nothing counted yet;
 *  - counting:  at least one counted quantity saved;
 *  - posted:    differences applied to stock (immutable);
 *  - cancelled: discarded without touching stock (immutable).
 *
 * Never rename or remove a value: it is stored.
 */
enum StockCountStatus: string
{
    case Draft = 'draft';
    case Counting = 'counting';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return $this === self::Draft || $this === self::Counting;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}
