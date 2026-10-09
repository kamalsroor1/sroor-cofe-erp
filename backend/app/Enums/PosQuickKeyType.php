<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * POSB-6: what a POS quick key opens.
 */
enum PosQuickKeyType: string
{
    case Item = 'item';
    case Category = 'category';
}
