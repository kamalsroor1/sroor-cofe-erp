<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Support\Money\Decimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PERF-01: the invoice side of every sales / profit report and of the dashboard, as grouped
 * SQL aggregates (never a model per invoice).
 *
 * Counted invoices: status 'confirmed', not soft-deleted (exactly what Invoice::where('status',
 * 'confirmed') loaded before). The period filters `invoices.invoice_date` on the column itself
 * with a half-open range [from, to + 1 day), the same boundary SalesReturnAggregates uses for
 * returns.return_date, so an invoice and its return on a period edge always land consistently,
 * and the (store_id, status, invoice_date) / (status, invoice_date) indexes are usable (a
 * DATE() / whereDate() wrapper is not). Results are scale-3 strings (Decimal::normalize).
 */
final class InvoiceAggregates
{
    /**
     * Base query over the counted invoices of a period. No select, no grouping.
     */
    public function confirmed(?string $fromDate, ?string $toDate, ?int $storeId = null): Builder
    {
        $query = DB::table('invoices')
            ->where('invoices.status', 'confirmed')
            ->whereNull('invoices.deleted_at');

        ReportDateRange::apply($query, 'invoices.invoice_date', $fromDate, $toDate);

        if ($storeId !== null) {
            $query->where('invoices.store_id', $storeId);
        }

        return $query;
    }

    /**
     * @return array{count: int, net_total: string, paid: string, remaining: string, cost: string}
     */
    public function totals(?string $fromDate, ?string $toDate, ?int $storeId = null): array
    {
        $row = $this->withSums($this->confirmed($fromDate, $toDate, $storeId))->first();

        return $this->shape($row);
    }

    /**
     * Per store_id (every store, NULL store under key 0; the caller decides which rows it shows).
     *
     * @return array<int, array{count: int, net_total: string, paid: string, remaining: string, cost: string}>
     */
    public function byStore(?string $fromDate, ?string $toDate): array
    {
        $rows = $this->withSums($this->confirmed($fromDate, $toDate))
            ->addSelect('invoices.store_id')
            ->groupBy('invoices.store_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->store_id] = $this->shape($row);
        }

        return $out;
    }

    /**
     * Per invoice day ('Y-m-d').
     *
     * @return array<string, array{count: int, net_total: string, paid: string, remaining: string, cost: string}>
     */
    public function byDate(?string $fromDate, ?string $toDate, ?int $storeId = null): array
    {
        $rows = $this->withSums($this->confirmed($fromDate, $toDate, $storeId))
            ->addSelect('invoices.invoice_date')
            ->groupBy('invoices.invoice_date')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $day = substr((string) $row->invoice_date, 0, 10);
            $shaped = $this->shape($row);
            if (isset($out[$day])) {
                // sqlite may keep several 'Y-m-d H:i:s' spellings of one day.
                $shaped = [
                    'count' => $out[$day]['count'] + $shaped['count'],
                    'net_total' => bcadd($out[$day]['net_total'], $shaped['net_total'], 3),
                    'paid' => bcadd($out[$day]['paid'], $shaped['paid'], 3),
                    'remaining' => bcadd($out[$day]['remaining'], $shaped['remaining'], 3),
                    'cost' => bcadd($out[$day]['cost'], $shaped['cost'], 3),
                ];
            }
            $out[$day] = $shaped;
        }

        return $out;
    }

    /**
     * Per value of one invoice column (payment_type, payment_method).
     *
     * @return array<string, array{count: int, net_total: string, paid: string, remaining: string, cost: string}>
     */
    public function byColumn(string $column, ?string $fromDate, ?string $toDate, ?int $storeId = null): array
    {
        if (! in_array($column, ['payment_type', 'payment_method'], true)) {
            throw new \InvalidArgumentException("Unsupported grouping column [{$column}].");
        }

        $rows = $this->withSums($this->confirmed($fromDate, $toDate, $storeId))
            ->addSelect("invoices.{$column} as group_key")
            ->groupBy("invoices.{$column}")
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->group_key] = $this->shape($row);
        }

        return $out;
    }

    /**
     * PERF-02: per 15-minute bucket of created_at on the STORAGE wall clock ('Y-m-d H:i', the
     * minute being 00/15/30/45), for the dashboard's peak-hours chart. Invoices without
     * created_at are left out.
     *
     * Why 15 minutes: every UTC offset in use (including :30 / :45 zones and DST shifts) is a
     * multiple of 15 minutes and changes on a 15-minute boundary, so all instants of one bucket
     * fall in the SAME tenant-local hour; the caller converts each bucket start to the tenant
     * clock exactly as it would convert each invoice. At most 96 rows per day of the window.
     *
     * @return array<string, array{count: int, net_total: string}>
     */
    public function byStorageQuarterHour(?string $fromDate, ?string $toDate, ?int $storeId = null): array
    {
        $query = $this->confirmed($fromDate, $toDate, $storeId)->whereNotNull('invoices.created_at');

        // confirmed() builds on the default connection (DB::table), so this is its driver.
        $bucket = match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "CONCAT(DATE_FORMAT(invoices.created_at, '%Y-%m-%d %H:'), LPAD(FLOOR(MINUTE(invoices.created_at) / 15) * 15, 2, '0'))",
            'sqlite' => "strftime('%Y-%m-%d %H:', invoices.created_at) || printf('%02d', (CAST(strftime('%M', invoices.created_at) AS INTEGER) / 15) * 15)",
            default => throw new \RuntimeException('InvoiceAggregates::byStorageQuarterHour() supports mysql, mariadb and sqlite.'),
        };

        $rows = $query
            ->selectRaw($bucket.' as bucket')
            ->selectRaw('COUNT(*) as invoices_count')
            ->selectRaw('COALESCE(SUM(invoices.net_total), 0) as net_total_sum')
            ->groupByRaw($bucket)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->bucket] = [
                'count' => (int) $row->invoices_count,
                'net_total' => Decimal::normalize(self::scalar($row->net_total_sum)),
            ];
        }

        return $out;
    }

    private function withSums(Builder $query): Builder
    {
        return $query
            ->selectRaw('COUNT(*) as invoices_count')
            ->selectRaw('COALESCE(SUM(invoices.net_total), 0) as net_total_sum')
            ->selectRaw('COALESCE(SUM(invoices.paid_amount), 0) as paid_sum')
            ->selectRaw('COALESCE(SUM(invoices.remaining_amount), 0) as remaining_sum')
            ->selectRaw('COALESCE(SUM(invoices.total_cost), 0) as cost_sum');
    }

    /**
     * @return array{count: int, net_total: string, paid: string, remaining: string, cost: string}
     */
    private function shape(?object $row): array
    {
        return [
            'count' => (int) ($row->invoices_count ?? 0),
            'net_total' => Decimal::normalize(self::scalar($row->net_total_sum ?? null)),
            'paid' => Decimal::normalize(self::scalar($row->paid_sum ?? null)),
            'remaining' => Decimal::normalize(self::scalar($row->remaining_sum ?? null)),
            'cost' => Decimal::normalize(self::scalar($row->cost_sum ?? null)),
        ];
    }

    public static function scalar(mixed $value): string
    {
        return is_numeric($value) ? (string) $value : '0';
    }
}
