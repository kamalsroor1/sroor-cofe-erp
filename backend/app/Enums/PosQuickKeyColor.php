<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * POSB-6: fixed colour palette of POS quick keys. The client maps each name to its own
 * light/dark theme tokens; free hex values are never accepted.
 */
enum PosQuickKeyColor: string
{
    case Slate = 'slate';
    case Sky = 'sky';
    case Emerald = 'emerald';
    case Amber = 'amber';
    case Rose = 'rose';
    case Violet = 'violet';
    case Indigo = 'indigo';
    case Teal = 'teal';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
