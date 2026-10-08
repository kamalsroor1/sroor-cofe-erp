<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * SETG-2 (CTO decision Q-S1): the tenant's wall clock.
 *
 * Storage is NOT changed: timestamps are still written and read in the application
 * timezone (config('app.timezone')) and DATE columns keep their stored values, so no
 * existing data shifts. The tenant timezone (TenantSettings::timezone(), default
 * Africa/Cairo) is used only for:
 *  - deciding which calendar day "today" is for reports, the dashboard, the daily
 *    journal and shift numbering;
 *  - turning a tenant-local day into a [start, end] range in the storage timezone,
 *    for filtering timestamp columns (opened_at, created_at…);
 *  - rendering stored timestamps on the tenant's clock.
 *
 * Tenant scope: call while tenancy is initialized. Stateless — every call re-reads the
 * setting through Setting's per-tenant cache, so it is safe in queued jobs and workers
 * that switch tenants.
 */
final class TenantClock
{
    public function __construct(
        private readonly TenantSettings $settings,
    ) {}

    /** The tenant's IANA timezone. */
    public function timezone(): string
    {
        return $this->settings->timezone();
    }

    /** The timezone timestamps are stored in (unchanged by SETG-2). */
    public function storageTimezone(): string
    {
        return (string) config('app.timezone');
    }

    /** Current instant on the tenant's clock. */
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    /** The tenant's current calendar date (Y-m-d). */
    public function today(): string
    {
        return $this->now()->toDateString();
    }

    /**
     * Tenant-local midnight of $date, expressed in the storage timezone.
     *
     * @param  string  $date  a tenant-local calendar date, Y-m-d
     */
    public function startOfDay(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, $this->timezone())
            ->startOfDay()
            ->setTimezone($this->storageTimezone());
    }

    /**
     * Last microsecond of the tenant-local $date, expressed in the storage timezone.
     *
     * @param  string  $date  a tenant-local calendar date, Y-m-d
     */
    public function endOfDay(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, $this->timezone())
            ->endOfDay()
            ->setTimezone($this->storageTimezone());
    }

    /**
     * Inclusive [start, end] of a tenant-local day in the storage timezone, ready for
     * whereBetween() on a timestamp column.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function dayBounds(string $date): array
    {
        return [$this->startOfDay($date), $this->endOfDay($date)];
    }

    /** A stored timestamp rendered on the tenant's clock (display only). */
    public function toTenant(?CarbonInterface $value): ?CarbonImmutable
    {
        return $value === null
            ? null
            : CarbonImmutable::instance($value)->setTimezone($this->timezone());
    }
}
