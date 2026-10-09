<?php

declare(strict_types=1);

namespace App\Observers\Billing;

use App\Models\Addon;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use App\Services\Entitlements\EntitlementFeatures;
use App\Services\Entitlements\TenantEntitlementService;
use App\Support\TenantCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Invalidates cached tenant entitlements (ENTI-2.2) whenever a CENTRAL row they are
 * derived from changes: Subscription, SubscriptionAddon, Plan, Addon, and the Tenant's
 * own plan_id / status / enabled_features (super-admin overrides).
 *
 * Always the explicit scope TenantCache::bumpFor($tenantId, 'entitlements'): these
 * events fire in central code (super-admin, billing), and the implicit bump() would move
 * the central version and leave the tenant's cache stale.
 *
 * The bump happens at once AND again after the surrounding transaction commits: the
 * second bump discards a value a concurrent request may have cached from the
 * not-yet-committed state in between. Mass query-builder updates fire no model events;
 * code doing those must call TenantEntitlementService::forget() itself.
 */
final class EntitlementsCacheObserver
{
    /** Tenant columns the entitlements are derived from. */
    private const TENANT_COLUMNS = ['plan_id', 'status', 'enabled_features'];

    public function saved(Model $model): void
    {
        if ($model instanceof Tenant && ! $model->wasRecentlyCreated && ! $model->wasChanged(self::TENANT_COLUMNS)) {
            return;
        }

        $this->bump($this->affectedTenantIds($model));
    }

    public function deleted(Model $model): void
    {
        $this->bump($this->affectedTenantIds($model));
    }

    /**
     * @return list<string>
     */
    private function affectedTenantIds(Model $model): array
    {
        $ids = match (true) {
            $model instanceof Tenant => [$model->getKey()],
            $model instanceof Subscription, $model instanceof SubscriptionAddon => [
                $model->getAttribute('tenant_id'),
                $model->getOriginal('tenant_id'),
            ],
            $model instanceof Plan => Tenant::query()->where('plan_id', $model->getKey())->pluck('id')->all(),
            $model instanceof Addon => SubscriptionAddon::query()->where('addon_id', $model->getKey())->distinct()->pluck('tenant_id')->all(),
            default => [],
        };

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): string => is_scalar($id) ? trim((string) $id) : '', $ids),
            static fn (string $id): bool => $id !== '',
        )));
    }

    /**
     * @param  list<string>  $tenantIds
     */
    private function bump(array $tenantIds): void
    {
        if ($tenantIds === []) {
            return;
        }

        $bumpAll = static function () use ($tenantIds): void {
            foreach ($tenantIds as $tenantId) {
                TenantCache::bumpFor($tenantId, TenantEntitlementService::CACHE_NAMESPACE);
            }

            // This process's Pennant memo (other processes flush on their next tenancy init).
            EntitlementFeatures::flush();
        };

        $bumpAll();

        // Runs right away when no transaction is open, otherwise after the commit.
        DB::connection((string) config('tenancy.database.central_connection'))->afterCommit($bumpAll);
    }
}
