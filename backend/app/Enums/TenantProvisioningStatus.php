<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Provisioning state of a tenant workspace (central `tenants.provisioning_status`), OPS-2.
 *
 *   pending ──(ProvisionTenantJob picks it up)──▶ running ──▶ ready
 *                                                    └──(final attempt failed)──▶ failed
 *   failed ──(super-admin retry)──▶ pending
 *   pending/running with no activity for the unique window (lost dispatch, dead worker)
 *          ──(super-admin retry)──▶ pending
 *
 * Only `ready` tenants are served (ResolveApiTenancy answers 503 otherwise). The column
 * defaults to `ready`, so every tenant that existed before OPS-2 keeps working.
 * A failed tenant keeps its central row (CTO default Q1): it is retried, never deleted.
 *
 * Never rename or remove a value: it is stored in `tenants.provisioning_status`.
 */
enum TenantProvisioningStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Ready = 'ready';
    case Failed = 'failed';

    public function isReady(): bool
    {
        return $this === self::Ready;
    }

    /**
     * A failed provisioning may be retried; so may a pending/running one that is STALE (no
     * activity for longer than the job's unique window, Tenant::provisioningIsStale(): its
     * dispatch was lost or its worker died). Anything else is 409.
     */
    public function canRetry(bool $stale = false): bool
    {
        return $this === self::Failed || ($stale && $this->isInProgress());
    }

    /** Still being prepared: the client may simply try again later. */
    public function isInProgress(): bool
    {
        return $this === self::Pending || $this === self::Running;
    }

    public function label(?string $locale = null): string
    {
        return (string) __('provisioning.statuses.'.$this->value, [], $locale);
    }
}
