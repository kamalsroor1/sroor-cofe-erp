<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * SETG-2 (CTO decision Q-S1) + SETG-2 ext (Settings Q6): the tenant's wall clock and
 * business day.
 *
 * Storage is NOT changed: timestamps are still written and read in the application
 * timezone (config('app.timezone')) and DATE columns keep their stored values, so no
 * existing data shifts. The tenant timezone (TenantSettings::timezone(), default
 * Africa/Cairo) and the business-day cutoff (TenantSettings::businessDayCutoff(), default
 * 00:00) decide:
 *  - the BUSINESS DATE stamped on every new document (invoice_date, payment_date,
 *    return_date, purchase_date, transfer_date, shift numbers…) — businessDate();
 *  - which day "today" is for reports, the dashboard, the daily journal and shifts;
 *  - the [start, end) of a business day in the storage timezone, for filtering timestamp
 *    columns (opened_at, created_at…) — businessDayRange();
 *  - rendering stored timestamps on the tenant's clock.
 *
 * Business day of date D = [D + cutoff, D+1 + cutoff) on the tenant clock. With cutoff
 * 03:00 a sale at 01:30 belongs to the previous day; with 00:00 it is the calendar day.
 *
 * Every write path must take its business date from here: see
 * Tests\Unit\Architecture\TenantClockWriteSideArchitectureTest.
 *
 * Tenant scope: call while tenancy is initialized. Stateless — every call re-reads the
 * settings through Setting's per-tenant cache, so it is safe in queued jobs and workers
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

    /** Business-day cutoff, 'HH:MM' on the tenant clock. */
    public function businessDayCutoff(): string
    {
        return $this->settings->businessDayCutoff();
    }

    /** Current instant on the tenant's clock (real wall time — use for timestamps/display). */
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    /**
     * The business date (Y-m-d) an instant belongs to. Defaults to now.
     *
     * Stamp this on every new document instead of now()/today()/now()->toDateString().
     */
    public function businessDate(?CarbonInterface $at = null): string
    {
        return $this->businessMoment($at)->toDateString();
    }

    /**
     * "Now" moved onto its business date: the tenant wall time, one calendar day earlier
     * while the clock is before the cutoff. Use it for date presets (this month, yesterday,
     * last 7 days…) so they follow the business day; never for stored timestamps.
     */
    public function businessNow(): CarbonImmutable
    {
        return $this->businessMoment(null);
    }

    /**
     * The tenant's current business day (Y-m-d). Equals the calendar date when the cutoff
     * is 00:00 (the default).
     */
    public function today(): string
    {
        return $this->businessDate();
    }

    /**
     * Half-open [start, end) of the business day $date, expressed in the storage timezone:
     * start = $date + cutoff, end = $date + 1 day + cutoff, both on the tenant clock.
     *
     * Filter timestamp columns with `>= start` and `< end`.
     *
     * @param  string  $date  a business date, Y-m-d
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function businessDayRange(string $date): array
    {
        $day = CarbonImmutable::parse($date, $this->timezone())->startOfDay();
        [$hours, $minutes] = $this->cutoffParts();

        $start = $day->setTime($hours, $minutes);
        $end = $day->addDay()->setTime($hours, $minutes);

        return [
            $start->setTimezone($this->storageTimezone()),
            $end->setTimezone($this->storageTimezone()),
        ];
    }

    /**
     * First instant of the business day $date, in the storage timezone.
     *
     * @param  string  $date  a business date, Y-m-d
     */
    public function startOfDay(string $date): CarbonImmutable
    {
        return $this->businessDayRange($date)[0];
    }

    /**
     * Last microsecond of the business day $date, in the storage timezone.
     *
     * @param  string  $date  a business date, Y-m-d
     */
    public function endOfDay(string $date): CarbonImmutable
    {
        return $this->businessDayRange($date)[1]->subMicrosecond();
    }

    /**
     * Inclusive [start, end] of a business day in the storage timezone, ready for
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

    private function businessMoment(?CarbonInterface $at): CarbonImmutable
    {
        $local = $at === null
            ? $this->now()
            : CarbonImmutable::instance($at)->setTimezone($this->timezone());

        [$hours, $minutes] = $this->cutoffParts();
        $minuteOfDay = ((int) $local->format('G')) * 60 + (int) $local->format('i');

        return $minuteOfDay < $hours * 60 + $minutes ? $local->subDay() : $local;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function cutoffParts(): array
    {
        [$hours, $minutes] = explode(':', $this->businessDayCutoff());

        return [(int) $hours, (int) $minutes];
    }
}
