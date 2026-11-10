<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Billing\Concerns\BillingEnum;
use App\Enums\Billing\Concerns\HasTranslatedLabel;

/**
 * What a shop asks support for (central `plan_change_requests.kind`).
 */
enum PlanChangeRequestKind: string implements BillingEnum
{
    use HasTranslatedLabel;

    case PlanChange = 'plan_change';
    case Addon = 'addon';

    public static function translationGroup(): string
    {
        return 'plan_change_request_kind';
    }
}
