<?php

declare(strict_types=1);

namespace App\Enums;

/** PRD-17: lifecycle of one shop data export. */
enum DataExportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Expired = 'expired';

    /** @return list<string> */
    public static function activeValues(): array
    {
        return [self::Pending->value, self::Processing->value];
    }
}
