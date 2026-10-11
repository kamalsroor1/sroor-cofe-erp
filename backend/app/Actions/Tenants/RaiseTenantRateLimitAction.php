<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\DTOs\Central\RaiseTenantRateLimitDTO;
use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\TenantRateLimitOverride;
use App\Services\CentralAuditLogger;
use App\Support\TenantRateLimitOverrides;
use Illuminate\Support\Facades\DB;

/**
 * A platform operator temporarily raises one tenant's rate limits, e.g. while a shop
 * installs several devices behind one NAT address (IDEN-4.6 ext, CTO W1 Q1).
 *
 * One CENTRAL transaction, tenant row locked (serializes concurrent raises for the tenant):
 *  - any still-active override of the tenant is revoked (`revoked_at`), so exactly one applies;
 *  - the new override is stored with a mandatory expiry (now + duration);
 *  - `tenant_rate_limit_raised` is audited (values, expiry, reason, operator).
 * After commit the limiter cache is flushed (TenantRateLimitOverrides::flush()).
 *
 * The limiters use max(config, override): an override never lowers a limit.
 */
final class RaiseTenantRateLimitAction
{
    public function __construct(
        private readonly CentralAuditLogger $audit,
    ) {}

    public function execute(RaiseTenantRateLimitDTO $dto, CentralUser $causer): TenantRateLimitOverride
    {
        $connection = DB::connection((new TenantRateLimitOverride)->getConnectionName());

        return $connection->transaction(function () use ($dto, $causer, $connection): TenantRateLimitOverride {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->whereKey($dto->tenantId)->lockForUpdate()->firstOrFail();
            $tenantId = (string) $tenant->getKey();
            $now = now();

            $superseded = TenantRateLimitOverride::query()
                ->where('tenant_id', $tenantId)
                ->active()
                ->update(['revoked_at' => $now, 'updated_at' => $now]);

            $override = new TenantRateLimitOverride;
            $override->fill(array_merge($dto->limits, [
                'tenant_id' => $tenantId,
                'reason' => $dto->reason,
                'central_user_id' => (int) $causer->getKey(),
                'expires_at' => $now->copy()->addMinutes($dto->durationMinutes),
            ]));
            $override->save();

            $this->audit->record(
                CentralAuditEvent::TenantRateLimitRaised,
                array_merge($dto->limits, [
                    'override_id' => (int) $override->getKey(),
                    'duration_minutes' => $dto->durationMinutes,
                    'expires_at' => $override->expires_at->toIso8601String(),
                    'reason' => $dto->reason,
                    'superseded' => $superseded,
                ]),
                $causer,
                $tenant,
            );

            $connection->afterCommit(static fn () => TenantRateLimitOverrides::flush());

            return $override;
        });
    }
}
