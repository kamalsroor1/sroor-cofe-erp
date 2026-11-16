<?php

declare(strict_types=1);

namespace App\Services\Entitlements;

use App\Contracts\TenantFeatureManagerInterface;
use App\Enums\LimitResource;
use App\Exceptions\Entitlements\FeatureUnavailableException;
use App\Exceptions\Entitlements\PlanLimitBusyException;
use App\Exceptions\Entitlements\PlanLimitExceededException;
use App\Models\Tenant;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * SEC-06 / BE-12 / PRD-01: the server-side enforcement point for plan features and limits.
 * Everything it knows comes from TenantFeatureManagerInterface (TenantEntitlementService),
 * for the tenant of the current request.
 *
 *  - assertFeature(): used by the `feature:<key>` route middleware (default deny).
 *  - withinStorageLimit(): wraps the tenant upload paths (storage_mb), see the method.
 *  - assertWithinLimit(): used by the Create* Actions INSIDE their DB::transaction()
 *    (and by InvoiceService for invoices_month).
 *    It first takes a row lock on the tenant's oldest store row (a per-tenant mutex),
 *    so two concurrent creations are counted one after the other and cannot both slip
 *    under the limit. Existing data is never touched: a tenant already above a limit
 *    (downgrade) only loses the ability to create more.
 *    Throughput note (invoices_month): on a plan WITH a monthly invoice cap the lock is held
 *    to the end of every sale's transaction, so that tenant's sales are serialised one after
 *    the other. Accepted for the trial tier (low volume, 300/month); an unlimited plan returns
 *    before the lock and its sales run concurrently as before.
 *
 * Nothing is enforced when no tenant is initialised (console, central code), and for a
 * legacy tenant with NO plan unless config('entitlements.enforce_planless') is on: such a
 * tenant predates plans and would otherwise lose every module at once (grandfathered;
 * new tenants cannot be provisioned without a plan).
 */
final class PlanEnforcer
{
    public function __construct(private readonly TenantFeatureManagerInterface $features) {}

    /**
     * @throws FeatureUnavailableException
     */
    public function assertFeature(string $featureKey): void
    {
        $tenant = $this->currentTenant();

        if ($tenant === null) {
            throw FeatureUnavailableException::for($featureKey);
        }

        if (! $this->isEnforced($tenant)) {
            return;
        }

        if (! $this->features->isFeatureEnabled($tenant, $featureKey)) {
            throw FeatureUnavailableException::for($featureKey);
        }
    }

    /**
     * Non-throwing form of assertFeature(): may the current tenant use $featureKey? Used to hide
     * fields of a response (e.g. profit figures) instead of refusing the whole request.
     * True when nothing is enforced (grandfathered planless tenant); false outside tenancy.
     */
    public function hasFeature(string $featureKey): bool
    {
        $tenant = $this->currentTenant();

        if ($tenant === null) {
            return false;
        }

        return ! $this->isEnforced($tenant) || $this->features->isFeatureEnabled($tenant, $featureKey);
    }

    /**
     * Refuse when creating $adding more records of $resource would pass the effective
     * limit (plan + add-ons; null = unlimited). Call inside DB::transaction().
     *
     * @param  Closure(): int  $currentCount  live count of the existing records, evaluated after the lock
     *
     * @throws PlanLimitExceededException
     */
    public function assertWithinLimit(LimitResource $resource, Closure $currentCount, int $adding = 1): void
    {
        $tenant = $this->currentTenant();

        if ($tenant === null || ! $this->isEnforced($tenant)) {
            return;
        }

        $max = $this->features->limit($tenant, $resource);

        if ($max === null) {
            return;
        }

        $this->lockTenantMutex($tenant);

        if ($currentCount() + $adding > $max) {
            throw PlanLimitExceededException::for($resource->value, $max);
        }
    }

    /**
     * PLAN-ENF: run $write (which stores a file) only when the tenant's storage after it stays
     * within the effective `storage_mb` limit: used bytes + delta <= limit x 1 MiB, else
     * PlanLimitExceededException (403, limit = storage_mb). The delta is the net growth of the
     * write: the incoming size minus what it replaces (a logo that replaces the previous one).
     * A write that does not grow the usage (delta <= 0) is always allowed, even above the limit
     * after a downgrade.
     *
     * File writes never run inside a DB transaction, so the check and the write are serialised
     * per tenant by a cache lock instead of the stores-row mutex (two concurrent uploads cannot
     * both fit in the last free megabyte). $deltaBytes is a closure so that whatever it reads
     * (e.g. the media a logo replaces) is read under that lock: it runs exactly once, before
     * $write, on every path. Nothing is locked when the limit is not enforced (no tenant,
     * planless tenant, unlimited plan).
     *
     * @template TResult
     *
     * @param  Closure(): int  $deltaBytes  net growth in bytes, evaluated once, under the lock when enforced
     * @param  Closure(): int  $usedBytes  current usage in bytes, evaluated under the lock
     * @param  Closure(): TResult  $write
     * @return TResult
     *
     * @throws PlanLimitExceededException
     * @throws PlanLimitBusyException
     */
    public function withinStorageLimit(Closure $deltaBytes, Closure $usedBytes, Closure $write): mixed
    {
        $tenant = $this->currentTenant();
        $max = $tenant === null || ! $this->isEnforced($tenant)
            ? null
            : $this->features->limit($tenant, LimitResource::StorageMb);

        if ($tenant === null || $max === null) {
            $deltaBytes();

            return $write();
        }

        $lock = Cache::lock('plan-storage-mutex:'.$tenant->getTenantKey(), 30);

        try {
            $lock->block(10);
        } catch (LockTimeoutException) {
            throw new PlanLimitBusyException;
        }

        try {
            $delta = $deltaBytes();

            if ($delta > 0 && $usedBytes() + $delta > $max * TenantStorageUsage::BYTES_PER_MB) {
                throw PlanLimitExceededException::for(LimitResource::StorageMb->value, $max);
            }

            return $write();
        } finally {
            $lock->release();
        }
    }

    /**
     * Is plan gating in force for the current request's tenant? Same rule assertFeature()
     * applies (false outside tenancy; false for a grandfathered planless tenant). The SPA
     * mirrors it through GET /system/context.
     */
    public function isEnforcedForCurrentTenant(): bool
    {
        $tenant = $this->currentTenant();

        return $tenant !== null && $this->isEnforced($tenant);
    }

    private function currentTenant(): ?Tenant
    {
        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return null;
        }

        $tenant = tenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    private function isEnforced(Tenant $tenant): bool
    {
        if ($this->features->entitlements($tenant)->planId !== null) {
            return true;
        }

        return (bool) config('entitlements.enforce_planless', false);
    }

    /**
     * Serialise limit checks of one tenant on the tenant database (the connection of the
     * surrounding transaction): lock the oldest store row. A workspace normally has one; if
     * none exists yet, fall back to a tenant-scoped cache lock held until the end of the
     * request (released by the cache TTL / process end), waiting a few seconds.
     */
    private function lockTenantMutex(Tenant $tenant): void
    {
        $locked = DB::table('stores')->orderBy('id')->lockForUpdate()->value('id');

        if ($locked !== null) {
            return;
        }

        $lock = Cache::lock('plan-limit-mutex:'.$tenant->getTenantKey(), 10);

        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            throw new PlanLimitBusyException;
        }

        app()->terminating(static fn () => $lock->release());
    }
}
