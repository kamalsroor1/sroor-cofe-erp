<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Progress of an asynchronous archive request (central `tenants.archive_state`, OPS-9).
 *
 *   DELETE /super-admin/tenants/{id} → queued → (ArchiveTenantJob) running
 *     → success: the column is cleared and the tenant is `archived`;
 *     → failure: failed + `tenants.archive_error_code`, the status is untouched.
 *
 * null = no archive request in flight (never requested, finished, or the tenant was
 * archived). Never rename or remove a value: it is stored on `tenants`.
 */
enum TenantArchiveState: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Failed = 'failed';

    public function isInProgress(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }

    public function translationKey(): string
    {
        return 'subscription.archive_states.'.$this->value;
    }

    public function label(?string $locale = null): string
    {
        return (string) __($this->translationKey(), [], $locale);
    }
}
