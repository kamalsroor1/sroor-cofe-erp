<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Billing\Concerns\BillingEnum;
use App\Enums\Billing\Concerns\HasTranslatedLabel;

/**
 * #232 spec D: the state of an invoice's temporary-access window at one instant (derived, never
 * stored). `none` = the invoice never got a window; `active` = running (full access on the CURRENT
 * plan); `expired` = ended unsettled (access back to the previous state, review still pending);
 * `verified` / `rejected` / `voided` = settled by that decision.
 */
enum TemporaryAccessState: string implements BillingEnum
{
    use HasTranslatedLabel;

    case None = 'none';
    case Active = 'active';
    case Expired = 'expired';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Voided = 'voided';

    public static function translationGroup(): string
    {
        return 'temporary_access_state';
    }

    public static function fromOutcome(TemporaryAccessOutcome $outcome): self
    {
        return match ($outcome) {
            TemporaryAccessOutcome::Verified => self::Verified,
            TemporaryAccessOutcome::Rejected => self::Rejected,
            TemporaryAccessOutcome::Voided => self::Voided,
        };
    }
}
