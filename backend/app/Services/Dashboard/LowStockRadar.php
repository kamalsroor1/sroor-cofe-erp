<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Item;
use App\Services\Settings\TenantSettings;
use App\Support\Money\Decimal;

/**
 * The dashboard's low-stock block (SETG-10: an item's own minimum, else the tenant default
 * threshold). Shared by the store dashboard (GetDashboardOverviewAction) and the cashier's
 * own-shift dashboard (ROLE-01, only for a user with `items.view`).
 */
final class LowStockRadar
{
    public function __construct(private readonly TenantSettings $tenantSettings) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function items(int $limit = 8): array
    {
        $lowStockThreshold = $this->tenantSettings->lowStockDefaultThreshold();

        return $this->tenantSettings->whereLowStock(Item::where('is_active', true))
            ->orderBy('current_stock', 'asc')
            ->take($limit)
            ->get(['id', 'name', 'code', 'category', 'current_stock', 'min_stock_level', 'unit'])
            ->map(fn ($it) => [
                'id' => $it->id,
                'name' => $it->name,
                'code' => $it->code,
                'category' => $it->category,
                'current_stock' => Decimal::normalize((string) $it->current_stock),
                'min_stock' => Decimal::normalize((string) (bccomp((string) ($it->min_stock_level ?? '0'), '0', 3) > 0 ? $it->min_stock_level : $lowStockThreshold)),
                'unit' => $it->unit ?? __('inventory.unit_weight_short'),
            ])
            ->values()
            ->all();
    }

    public function count(): int
    {
        return $this->tenantSettings->whereLowStock(Item::where('is_active', true))->count();
    }
}
