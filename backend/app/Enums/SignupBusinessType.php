<?php

declare(strict_types=1);

namespace App\Enums;

/** PRD-05: the fixed list of business types offered on the signup form. */
enum SignupBusinessType: string
{
    case Retail = 'retail';
    case Wholesale = 'wholesale';
    case Weighted = 'weighted';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
