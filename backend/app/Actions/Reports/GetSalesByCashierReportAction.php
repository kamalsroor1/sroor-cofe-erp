<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\ReportFilterDTO;
use App\Models\User;
use App\Services\Reports\ReportDateRange;
use App\Services\Reports\SalesReturnAggregates;
use App\Support\Money\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * SHOP-05: sales per cashier (the invoice's user_id) in the period and store. Net sales =
 * confirmed invoices' net_total minus the sales returns the cashier processed (returns.user_id,
 * the drawer the refund left), dated in the period. Three statements whatever the volume.
 */
final class GetSalesByCashierReportAction
{
    public function __construct(
        private readonly SalesReturnAggregates $salesReturns,
    ) {}

    /**
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, mixed>}
     */
    public function execute(ReportFilterDTO $dto): array
    {
        $invoices = DB::table('invoices')
            ->where('invoices.status', 'confirmed')
            ->whereNull('invoices.deleted_at')
            ->when($dto->store_id !== null, fn ($q) => $q->where('invoices.store_id', $dto->store_id));
        ReportDateRange::apply($invoices, 'invoices.invoice_date', $dto->from_date, $dto->to_date);

        $sales = $invoices
            ->groupBy('invoices.user_id')
            ->select('invoices.user_id')
            ->selectRaw('COUNT(*) as invoices_count')
            ->selectRaw('COALESCE(SUM(invoices.net_total), 0) as sales')
            ->selectRaw('COALESCE(SUM(invoices.discount_amount), 0) as discounts')
            ->selectRaw('COALESCE(SUM(invoices.paid_amount), 0) as collected')
            ->selectRaw('COALESCE(SUM(invoices.remaining_amount), 0) as credit')
            ->get()
            ->keyBy(fn ($row) => (int) $row->user_id);

        $returns = $this->salesReturns->lines($dto->from_date, $dto->to_date, $dto->store_id)
            ->groupBy('returns.user_id')
            ->select('returns.user_id')
            ->selectRaw('COUNT(DISTINCT returns.id) as returns_count')
            ->selectRaw('COALESCE(SUM(return_items.total_price), 0) as returns_value')
            ->get()
            ->keyBy(fn ($row) => (int) $row->user_id);

        $userIds = $sales->keys()->merge($returns->keys())->unique()->filter()->values();
        $names = User::withTrashed()->whereIn('id', $userIds)->pluck('name', 'id');

        $rows = [];
        foreach ($userIds->merge($sales->has(0) || $returns->has(0) ? [0] : [])->unique() as $userId) {
            $sale = $sales->get($userId);
            $return = $returns->get($userId);
            $count = (int) ($sale->invoices_count ?? 0);
            $salesValue = Decimal::normalize((string) ($sale->sales ?? '0'));
            $returnsValue = Decimal::normalize((string) ($return->returns_value ?? '0'));

            $rows[] = [
                'user_id' => $userId === 0 ? null : $userId,
                'name' => $userId === 0 ? __('reports.unknown_cashier') : (string) ($names[$userId] ?? __('reports.unknown_cashier')),
                'invoices_count' => $count,
                'sales' => $salesValue,
                'discounts' => Decimal::normalize((string) ($sale->discounts ?? '0')),
                'returns_count' => (int) ($return->returns_count ?? 0),
                'returns_value' => $returnsValue,
                'net_sales' => Decimal::sub($salesValue, $returnsValue),
                'collected' => Decimal::normalize((string) ($sale->collected ?? '0')),
                'credit' => Decimal::normalize((string) ($sale->credit ?? '0')),
                'avg_invoice' => $count > 0 ? Decimal::round(bcdiv($salesValue, (string) $count, 6)) : '0.000',
            ];
        }

        usort($rows, fn (array $a, array $b): int => Decimal::cmp($b['net_sales'], $a['net_sales']) ?: strcmp($a['name'], $b['name']));

        return ['rows' => $rows, 'totals' => $this->totals($rows)];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function totals(array $rows): array
    {
        $totals = ['invoices_count' => 0, 'returns_count' => 0];
        foreach (['sales', 'discounts', 'returns_value', 'net_sales', 'collected', 'credit'] as $key) {
            $totals[$key] = '0.000';
        }
        foreach ($rows as $row) {
            $totals['invoices_count'] += $row['invoices_count'];
            $totals['returns_count'] += $row['returns_count'];
            foreach (['sales', 'discounts', 'returns_value', 'net_sales', 'collected', 'credit'] as $key) {
                $totals[$key] = Decimal::add($totals[$key], $row[$key]);
            }
        }
        $totals['avg_invoice'] = $totals['invoices_count'] > 0
            ? Decimal::round(bcdiv($totals['sales'], (string) $totals['invoices_count'], 6))
            : '0.000';

        return $totals;
    }
}
