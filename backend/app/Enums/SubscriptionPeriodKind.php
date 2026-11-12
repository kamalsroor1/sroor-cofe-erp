<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * SELL-02: which period of a tenant is running out: the free trial (`tenants.trial_ends_at`)
 * or the paid subscription (`tenants.subscription_ends_at`). Stored as the first part of
 * `tenant_lifecycle_reminders.kind` and in the in-app notification data: never rename a value.
 */
enum SubscriptionPeriodKind: string
{
    case Trial = 'trial';
    case Subscription = 'subscription';
}
