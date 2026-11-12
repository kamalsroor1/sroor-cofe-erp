<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Tenant;
use Carbon\CarbonImmutable;

/**
 * SELL-06: why a tenant is read-only, sent in the 423 `subscription.read_only` details so the
 * SPA (and the message) can tell the owner what happened and what to do next.
 */
enum ReadOnlyReason: string
{
    /** The trial (and its grace days) is over and the shop never paid. */
    case TrialEnded = 'trial_ended';

    /** Never paid and never had a trial (e.g. created by an operator with trial_days = 0). */
    case PaymentPending = 'payment_pending';

    /** The paid period ended (past_due grace over). */
    case SubscriptionEnded = 'subscription_ended';

    /**
     * Read-only while a trial or a paid term is still running: an operator decided it. The
     * owner is not told a trial/subscription ended; the next step is to contact support.
     */
    case Operator = 'operator';

    /** Derived from the central row at $now: subscription_ends_at is the PAID end, trial_ends_at the trial end. */
    public static function forTenant(Tenant $tenant, CarbonImmutable $now): self
    {
        $paidEnd = $tenant->subscription_ends_at;
        if ($paidEnd !== null) {
            return $paidEnd->isAfter($now) ? self::Operator : self::SubscriptionEnded;
        }

        $trialEnd = $tenant->trial_ends_at;
        if ($trialEnd !== null) {
            return $trialEnd->isAfter($now) ? self::Operator : self::TrialEnded;
        }

        return self::PaymentPending;
    }

    /** True when the owner can fix it by paying (an operator decision needs support instead). */
    public function isPayable(): bool
    {
        return $this !== self::Operator;
    }
}
