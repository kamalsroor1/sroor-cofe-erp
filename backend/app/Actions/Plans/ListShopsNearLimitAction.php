<?php

declare(strict_types=1);

namespace App\Actions\Plans;

use App\Actions\Billing\GetTenantSubscriptionAction;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Entitlements\TenantEntitlementService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Throwable;

/**
 * PLAN-14: shops that use at least $threshold percent of any plan limit (upsell list).
 *
 * Usage lives in each shop's own database, so there is no cross-tenant query: every candidate
 * shop is entered in turn (tenancy initialised by hand, the previous context restored in
 * `finally`) and the same usage the shop's billing screen shows is read
 * (GetTenantSubscriptionAction, limits = plan + add-ons). Cost control:
 *  - only shops that can still be sold to (trial / active / past_due), provisioned, on a plan
 *    with at least one finite limit (read from the cached entitlements, no tenant query);
 *  - at most SCAN_CAP shops per run, reported back as `capped`;
 *  - the whole result is cached SCAN_TTL seconds per threshold (`$refresh` recomputes it);
 *  - a shop whose database cannot be read is skipped and counted in `failed`.
 */
final class ListShopsNearLimitAction
{
    public const DEFAULT_THRESHOLD = 80;

    public const SCAN_CAP = 500;

    public const SCAN_TTL = 600;

    private const CACHE_KEY = 'plans:near-limit-shops:';

    public function __construct(
        private readonly TenantEntitlementService $entitlements,
        private readonly GetTenantSubscriptionAction $subscription,
    ) {}

    /**
     * @return array{rows: list<array<string, mixed>>, scanned: int, capped: bool, failed: int, generated_at: string}
     */
    public function execute(int $threshold = self::DEFAULT_THRESHOLD, bool $refresh = false): array
    {
        $key = self::CACHE_KEY.$threshold;

        if ($refresh) {
            Cache::forget($key);
        }

        /** @var array{rows: list<array<string, mixed>>, scanned: int, capped: bool, failed: int, generated_at: string} */
        return Cache::remember($key, self::SCAN_TTL, fn (): array => $this->scan($threshold));
    }

    /**
     * @return array{rows: list<array<string, mixed>>, scanned: int, capped: bool, failed: int, generated_at: string}
     */
    private function scan(int $threshold): array
    {
        $tenants = Tenant::query()
            ->provisioned()
            ->whereNotNull('plan_id')
            ->whereIn('status', [TenantStatus::Trial->value, TenantStatus::Active->value, TenantStatus::PastDue->value])
            ->orderBy('id')
            ->limit(self::SCAN_CAP + 1)
            ->get();

        $capped = $tenants->count() > self::SCAN_CAP;
        $plans = Plan::query()->pluck('name', 'id');
        $rows = [];
        $scanned = 0;
        $failed = 0;

        foreach ($tenants->take(self::SCAN_CAP) as $tenant) {
            if (! $tenant instanceof Tenant || ! $this->hasFiniteLimit($tenant)) {
                continue;
            }

            $scanned++;

            try {
                $usage = $this->usageOf($tenant);
            } catch (Throwable $e) {
                $failed++;
                Log::warning('near-limit scan skipped a shop', ['tenant' => $tenant->getKey(), 'error' => $e->getMessage()]);

                continue;
            }

            foreach ($usage as $row) {
                if ((int) $row['percent'] < $threshold) {
                    continue;
                }

                $rows[] = [
                    'tenant_id' => (string) $tenant->getKey(),
                    'tenant_name' => (string) $tenant->name,
                    'tenant_status' => (string) $tenant->status,
                    'plan_id' => (int) $tenant->plan_id,
                    'plan_name' => (string) ($plans[$tenant->plan_id] ?? ''),
                    'resource' => $row['resource'],
                    'label' => $row['label'],
                    'unit' => $row['unit'],
                    'used' => $row['used'],
                    'limit' => $row['limit'],
                    'percent' => $row['percent'],
                    'over_limit' => $row['over_limit'],
                ];
            }
        }

        usort($rows, static fn (array $a, array $b): int => [$b['percent'], $a['tenant_name']] <=> [$a['percent'], $b['tenant_name']]);

        return [
            'rows' => $rows,
            'scanned' => $scanned,
            'capped' => $capped,
            'failed' => $failed,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function hasFiniteLimit(Tenant $tenant): bool
    {
        foreach ($this->entitlements->entitlements($tenant)->limits as $limit) {
            if ($limit !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function usageOf(Tenant $tenant): array
    {
        $tenancy = tenancy();
        $previous = $tenancy->initialized ? $tenancy->tenant : null;

        try {
            // Inside the try: a bootstrapper that fails half-way still gets the context restored.
            $tenancy->initialize($tenant);

            /** @var list<array<string, mixed>> */
            return $this->subscription->execute($tenant)['usage'];
        } finally {
            if ($previous instanceof TenantContract) {
                if ($previous->getTenantKey() !== $tenant->getTenantKey()) {
                    $tenancy->initialize($previous);
                }
            } else {
                $tenancy->end();
            }
        }
    }
}
