<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Billing\Concerns\BillingEnum;
use App\Enums\Billing\Concerns\HasTranslatedLabel;

/**
 * Lifecycle of a plan-change / add-on request (central `plan_change_requests.status`).
 *
 * PLAN-10 review: `scheduled` = a downgrade to a permanent free plan. It is never invoiced; the
 * daily lifecycle sweep applies it at the end of the paid term (MoveTenantToFreePlanAction, then
 * `completed`), and the shop can cancel it until then.
 */
enum PlanChangeRequestStatus: string implements BillingEnum
{
    use HasTranslatedLabel;

    case Pending = 'pending';
    case Invoiced = 'invoiced';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Scheduled = 'scheduled';

    /**
     * Statuses of a request still waiting on something (one per tenant and kind).
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Pending, self::Scheduled];
    }

    /** The shop may withdraw it. */
    public function isCancellable(): bool
    {
        return in_array($this, self::open(), true);
    }

    public static function translationGroup(): string
    {
        return 'plan_change_request_status';
    }
}
