<?php

declare(strict_types=1);

namespace App\Exceptions\Billing;

use DomainException;

/**
 * A subscription add-on line could not be saved because its tenant could not be derived
 * safely from its parent subscription (W1 hardening of ENTI-1.5). Nothing is written.
 * The message is translated (lang/{ar,en}/billing.php -> subscription_addon.*); reason()
 * is a stable code for callers and API error payloads.
 */
final class SubscriptionAddonException extends DomainException
{
    private function __construct(private readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public function reason(): string
    {
        return $this->reason;
    }

    /** The line has no subscription_id, or it points to a subscription that does not exist. */
    public static function subscriptionMissing(): self
    {
        return self::make('subscription_missing');
    }

    /** The line carries a tenant_id that is not its parent subscription's tenant. */
    public static function tenantMismatch(): self
    {
        return self::make('tenant_mismatch');
    }

    private static function make(string $reason): self
    {
        return new self($reason, (string) __('billing.subscription_addon.'.$reason));
    }
}
