<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

/**
 * Why a tenant provisioning failed (central `tenants.provisioning_error_code`, string(64)), OPS-2.
 *
 * Machine-readable only: the raw exception goes to the log / failed_jobs, never to the
 * central row or an API response. Permanent codes stop the job at once (no retry: the
 * same input fails the same way); the others are retried with backoff.
 *
 * Never rename or remove a value: it is stored on the tenant row.
 */
enum ProvisioningErrorCode: string
{
    /** A database with the tenant's name already exists and was not created by us (or is not empty). */
    case DatabaseExists = 'database_exists';

    /** The tenant database name is not a plain identifier we are willing to quote. */
    case InvalidDatabaseName = 'invalid_database_name';

    /** CREATE DATABASE failed or the database could not be reached. */
    case DatabaseCreateFailed = 'database_create_failed';

    /** Creating / granting the per-tenant MySQL user failed. */
    case DatabaseUserFailed = 'database_user_failed';

    /** tenants:migrate failed. */
    case MigrationFailed = 'migration_failed';

    /** Seeding the permission matrix, main store or first admin failed. */
    case SeedFailed = 'seed_failed';

    /** The job ran out of time (worker timeout) or attempts without a specific error. */
    case Timeout = 'timeout';

    case Unexpected = 'unexpected';

    public function isPermanent(): bool
    {
        return $this === self::DatabaseExists || $this === self::InvalidDatabaseName;
    }

    public function label(?string $locale = null): string
    {
        return (string) __('provisioning.errors.'.$this->value, [], $locale);
    }
}
