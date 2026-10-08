<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who is asking for a tenant status change (IDEN-3.1).
 *
 * The state machine in TenantStatus decides which actor may make which move:
 *  - System: the daily lifecycle sweep (IDEN-3.6), time-driven moves only;
 *  - SuperAdmin: a platform operator (central guard), manual moves with a reason;
 *  - Billing: a verified payment (ENTI-3.4 ActivateSubscriptionAction). The ONLY
 *    actor that can make a tenant `active`.
 *
 * Stored as the string value in lifecycle events (IDEN-3.2). Never rename a value.
 */
enum TenantLifecycleActor: string
{
    case System = 'system';
    case SuperAdmin = 'super_admin';
    case Billing = 'billing';

    public function translationKey(): string
    {
        return 'subscription.actors.'.$this->value;
    }

    public function label(?string $locale = null): string
    {
        return (string) __($this->translationKey(), [], $locale);
    }
}
