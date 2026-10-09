<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

/**
 * Why a tenant was suspended (or cancelled), CTO W1 Q2 (2026-10-09).
 *
 * Stored as the string value in `tenants.suspension_reason` and
 * `tenant_lifecycle_events.reason` (string(30)). Never rename or remove a value.
 *
 * `violation` is special: such a tenant is NEVER archived automatically
 * (TenantLifecyclePolicy stops the retention clock); only a super-admin archives it.
 */
enum TenantSuspensionReason: string
{
    case NonPayment = 'non_payment';
    case Violation = 'violation';
    case CustomerRequest = 'customer_request';
    case Other = 'other';

    public function blocksAutomaticArchive(): bool
    {
        return $this === self::Violation;
    }

    public function translationKey(): string
    {
        return 'subscription.suspension_reasons.'.$this->value;
    }

    public function label(?string $locale = null): string
    {
        return (string) __($this->translationKey(), [], $locale);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
