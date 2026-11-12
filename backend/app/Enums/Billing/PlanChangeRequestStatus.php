<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Billing\Concerns\BillingEnum;
use App\Enums\Billing\Concerns\HasTranslatedLabel;

/**
 * Lifecycle of a plan-change / add-on request (central `plan_change_requests.status`).
 */
enum PlanChangeRequestStatus: string implements BillingEnum
{
    use HasTranslatedLabel;

    case Pending = 'pending';
    case Invoiced = 'invoiced';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public static function translationGroup(): string
    {
        return 'plan_change_request_status';
    }
}
