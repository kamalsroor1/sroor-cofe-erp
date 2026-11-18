<?php

declare(strict_types=1);

namespace App\Actions\POS;

use App\Contracts\TenantFeatureManagerInterface;
use App\Enums\LimitResource;
use App\Models\Tenant;
use App\Services\Entitlements\MonthlyInvoiceCounter;
use App\Services\Entitlements\PlanEnforcer;

/**
 * PLAN-13: where the shop stands against its monthly sales-invoice cap, for the cashier screen.
 *
 * The /billing usage payload is for billing managers only; a cashier has to be told BEFORE the
 * sale that the month's cap is reached (the checkout itself still answers 403 limit_reached).
 * `limit` is null when the plan has no cap (or the tenant is not enforced): nothing to show.
 * Counting is the same MonthlyInvoiceCounter the checkout enforces with.
 */
final class GetPosInvoiceLimitStatusAction
{
    public function __construct(
        private readonly TenantFeatureManagerInterface $features,
        private readonly PlanEnforcer $enforcer,
        private readonly MonthlyInvoiceCounter $counter,
    ) {}

    /**
     * @return array{limit: int|null, used: int, remaining: int|null, percent: int, reached: bool, resets_at: string|null}
     */
    public function execute(Tenant $tenant): array
    {
        $limit = $this->enforcer->isEnforcedForCurrentTenant()
            ? $this->features->limit($tenant, LimitResource::InvoicesMonth)
            : null;

        if ($limit === null) {
            return ['limit' => null, 'used' => 0, 'remaining' => null, 'percent' => 0, 'reached' => false, 'resets_at' => null];
        }

        $used = $this->counter->count();

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'percent' => $limit === 0 ? 100 : intdiv($used * 100 + $limit - 1, $limit),
            'reached' => $used >= $limit,
            'resets_at' => $this->counter->resetsAt()->toIso8601String(),
        ];
    }
}
