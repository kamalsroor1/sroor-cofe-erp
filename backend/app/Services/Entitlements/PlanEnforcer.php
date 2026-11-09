<?php

declare(strict_types=1);

namespace App\Services\Entitlements;

use App\Contracts\TenantFeatureManagerInterface;
use App\Enums\LimitResource;
use App\Exceptions\Entitlements\FeatureUnavailableException;
use App\Exceptions\Entitlements\PlanLimitExceededException;
use App\Models\Tenant;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * SEC-06 / BE-12 / PRD-01: the server-side enforcement point for plan features and limits.
 * Everything it knows comes from TenantFeatureManagerInterface (TenantEntitlementService),
 * for the tenant of the current request.
 *
 *  - assertFeature(): used by the `feature:<key>` route middleware (default deny).
 *  - assertWithinLimit(): used by the Create* Actions INSIDE their DB::transaction().
 *    It first takes a row lock on the tenant's oldest store row (a per-tenant mutex),
 *    so two concurrent creations are counted one after the other and cannot both slip
 *    under the limit. Existing data is never touched: a tenant already above a limit
 *    (downgrade) only loses the ability to create more.
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
            throw new HttpException(Response::HTTP_CONFLICT, (string) __('common.operation_failed'));
        }

        app()->terminating(static fn () => $lock->release());
    }
}
