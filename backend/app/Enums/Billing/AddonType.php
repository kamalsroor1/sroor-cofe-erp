<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Billing\Concerns\BillingEnum;
use App\Enums\Billing\Concerns\HasTranslatedLabel;

/**
 * Kind of catalog add-on in central `addons`: billed every cycle, a service
 * (onboarding…) issued as its own invoice line, or a credits pack (ENTI-1.10).
 *
 * `credits` (CTO 2026-10-09, Q12): billed every cycle like `recurring`, and grants
 * `addons.included_credits` per cycle into the append-only `tenant_credit_ledger`
 * (App\Services\Billing\CreditBalanceService). Schema only in Phase 1: no credits add-on
 * is seeded, shown or sold, and nothing consumes credits before Phase 3.
 */
enum AddonType: string implements BillingEnum
{
    use HasTranslatedLabel;

    case Recurring = 'recurring';
    case Service = 'service';
    case Credits = 'credits';

    public static function translationGroup(): string
    {
        return 'addon_type';
    }
}
