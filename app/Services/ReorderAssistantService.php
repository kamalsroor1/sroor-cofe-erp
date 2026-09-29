<?php

namespace App\Services;

use App\Models\Item;
use App\Models\InvoiceItem;
use App\Models\Supplier;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReorderAssistantService
{
    /**
     * Calculate smart reorder suggestions and stock depletion forecast
     */
    public function getReorderSuggestions(?int $storeId = null, int $analysisDays = 14, int $targetCoverDays = 15): array
    {
        $startDate = now()->subDays($analysisDays)->toDateString();
        $today = now()->toDateString();

        // 1. Get all active items
        $items = Item::active()->get();

        // 2. Aggregate sales per item in the analysis period
        $salesQuery = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.status', 'confirmed')
            ->whereDate('invoices.invoice_date', '>=', $startDate)
            ->whereDate('invoices.invoice_date', '<=', $today);

        if ($storeId) {
            $salesQuery->where('invoices.store_id', $storeId);
        }

        $salesPerItem = $salesQuery
            ->select([
                'invoice_items.item_id',
                DB::raw('SUM(invoice_items.quantity) as total_quantity_sold'),
            ])
            ->groupBy('invoice_items.item_id')
            ->get()
            ->keyBy('item_id');

        $suggestions = [];
        $criticalCount = 0;
        $warningCount = 0;
        $safeCount = 0;

        foreach ($items as $item) {
            $salesRecord = $salesPerItem->get($item->id);
            $qtySold = $salesRecord ? (string)$salesRecord->total_quantity_sold : '0.000';

            // Daily consumption
            $dailyConsumption = bcdiv($qtySold, (string)$analysisDays, 3);

            $currentStock = (string)($item->current_stock ?: '0.000');
            $unitCost = $item->effectiveCost();

            // Days of stock remaining
            if (bccomp($dailyConsumption, '0.000', 3) > 0) {
                $daysRemaining = (int)floor((float)bcdiv($currentStock, $dailyConsumption, 3));
            } else {
                $daysRemaining = bccomp($currentStock, '0.000', 3) > 0 ? 999 : 0;
            }

            // Target stock needed for $targetCoverDays
            $targetStockNeeded = bcmul($dailyConsumption, (string)$targetCoverDays, 3);
            $suggestedQty = '0.000';

            if (bccomp($targetStockNeeded, $currentStock, 3) > 0) {
                $suggestedQty = bcsub($targetStockNeeded, $currentStock, 3);
                // Round up to nearest unit if fractional is minimal
                $suggestedQty = number_format((float)$suggestedQty, 3, '.', '');
            }

            // Urgency Status
            if (bccomp($currentStock, '0.000', 3) <= 0 || (bccomp($dailyConsumption, '0.000', 3) > 0 && $daysRemaining <= 3)) {
                $urgency = 'critical'; // الأحمر: نفد أو ينفد خلال 3 أيام
                $criticalCount++;
            } elseif (bccomp($dailyConsumption, '0.000', 3) > 0 && $daysRemaining <= 7) {
                $urgency = 'warning'; // الأصفر: ينفد خلال أسبوع
                $warningCount++;
            } else {
                $urgency = 'safe'; // الأخضر: رصيد آمن
                $safeCount++;
            }

            $estimatedCost = bcmul($suggestedQty, $unitCost, 3);

            $suggestions[] = [
                'id'                 => $item->id,
                'name'               => $item->name,
                'code'               => $item->code,
                'unit'               => $item->unit,
                'current_stock'      => $currentStock,
                'unit_cost'          => $unitCost,
                'analysis_sales'     => $qtySold,
                'daily_consumption'  => $dailyConsumption,
                'days_remaining'     => $daysRemaining,
                'urgency'            => $urgency,
                'target_stock'       => $targetStockNeeded,
                'suggested_quantity' => $suggestedQty,
                'estimated_cost'     => $estimatedCost,
            ];
        }

        // Sort by urgency: critical first, then warning, then lowest days remaining
        usort($suggestions, function ($a, $b) {
            $urgencyWeight = ['critical' => 1, 'warning' => 2, 'safe' => 3];
            $weightA = $urgencyWeight[$a['urgency']] ?? 3;
            $weightB = $urgencyWeight[$b['urgency']] ?? 3;

            if ($weightA !== $weightB) {
                return $weightA <=> $weightB;
            }
            return $a['days_remaining'] <=> $b['days_remaining'];
        });

        return [
            'suggestions'        => $suggestions,
            'critical_count'     => $criticalCount,
            'warning_count'      => $warningCount,
            'safe_count'         => $safeCount,
            'analysis_days'      => $analysisDays,
            'target_cover_days'  => $targetCoverDays,
        ];
    }
}
