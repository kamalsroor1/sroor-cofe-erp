<?php

namespace App\Services;

use App\Services\Reports\InvoiceAggregates;
use App\Services\Reports\SalesReturnAggregates;
use App\Services\Settings\TenantSettings;
use App\Support\TenantClock;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class DashboardAnalyticsService
{
    public function __construct(
        private readonly TenantClock $tenantClock,
        private readonly SalesReturnAggregates $salesReturns,
        private readonly InvoiceAggregates $invoices,
    ) {}

    /**
     * Get Interactive Executive Dashboard Analytics
     */
    public function getAnalytics(?int $storeId = null, int $trendDays = 7): array
    {
        // SETG-2 ext: "today" is the tenant business day (timezone + cutoff); storage is unchanged.
        $now = $this->tenantClock->businessNow();
        $today = $now->toDateString();
        $startDate = $now->subDays($trendDays - 1)->toDateString();

        // PERF-01: grouped SQL instead of loading the invoices of the period as models.
        // BUG-01: sales figures are NET of the sales returns booked on the same days. The basket
        // size stays the average issued invoice (returns are separate documents).
        $returnsByDay = $this->salesReturns->valueByDate($startDate, $today, $storeId);
        $soldByDay = $this->invoices->byDate($startDate, $today, $storeId ?: null);

        // 1. Today's Core Invoices
        $todaySold = $this->invoices->totals($today, $today, $storeId ?: null);
        $todayGrossSales = $todaySold['net_total'];
        $todayReturns = $returnsByDay[$today] ?? '0.000';
        $todaySales = bcsub($todayGrossSales, $todayReturns, 3);
        $todayInvoicesCount = $todaySold['count'];

        $todayBasketSize = $todayInvoicesCount > 0
            ? bcdiv($todayGrossSales, (string) $todayInvoicesCount, 2)
            : '0.00';

        // 2. Period Invoices (Last N days)
        $periodGrossSales = '0.000';
        $periodInvoicesCount = 0;
        foreach ($soldByDay as $daySold) {
            $periodGrossSales = bcadd($periodGrossSales, $daySold['net_total'], 3);
            $periodInvoicesCount += $daySold['count'];
        }
        $periodReturns = '0.000';
        foreach ($returnsByDay as $dayReturns) {
            $periodReturns = bcadd($periodReturns, $dayReturns, 3);
        }
        $periodSales = bcsub($periodGrossSales, $periodReturns, 3);

        $periodBasketSize = $periodInvoicesCount > 0
            ? bcdiv($periodGrossSales, (string) $periodInvoicesCount, 2)
            : '0.00';

        // 3. Daily Sales Trend (Last N days)
        $dailyTrend = [];
        for ($i = $trendDays - 1; $i >= 0; $i--) {
            $date = $now->subDays($i)->toDateString();
            $dayName = $now->subDays($i)->locale('ar')->isoFormat('dddd D/M');
            $daySold = $soldByDay[$date] ?? ['count' => 0, 'net_total' => '0.000'];
            $dayReturns = $returnsByDay[$date] ?? '0.000';
            $daySales = bcsub($daySold['net_total'], $dayReturns, 3);

            $dailyTrend[] = [
                'date' => $date,
                'label' => $dayName,
                'sales' => (float) $daySales,
                'sales_formatted' => number_format((float) $daySales, 2).' '.app(TenantSettings::class)->currencyLabel(),
                'invoices' => $daySold['count'],
                'returns' => $dayReturns,
            ];
        }

        // 4. Peak Sales Hours (0 to 23)
        $hourlySales = [];
        for ($h = 0; $h < 24; $h++) {
            $hourLabel = Carbon::createFromTime($h, 0)->format('g A');
            $arabicHour = $h === 0 ? '12 ص' : ($h < 12 ? "{$h} ص" : ($h === 12 ? '12 م' : ($h - 12).' م'));
            $hourlySales[$h] = [
                'hour' => $h,
                'label' => $arabicHour,
                'sales' => '0.000',
                'invoices' => 0,
            ];
        }

        // The hour is the tenant-local hour of created_at. PERF-02: grouped in SQL per 15-minute
        // bucket of the storage clock (at most 96 rows a day instead of every invoice of the
        // window); each bucket lies in one tenant hour, so half-hour zones stay exact.
        $storageZone = $this->tenantClock->storageTimezone();
        foreach ($this->invoices->byStorageQuarterHour($startDate, $today, $storeId ?: null) as $bucket => $sold) {
            $hour = (int) $this->tenantClock->toTenant(CarbonImmutable::parse($bucket.':00', $storageZone))?->format('G');
            if (isset($hourlySales[$hour])) {
                $hourlySales[$hour]['sales'] = bcadd((string) $hourlySales[$hour]['sales'], $sold['net_total'], 3);
                $hourlySales[$hour]['invoices'] += $sold['count'];
            }
        }

        // Find peak hour & compute formatting
        $peakSales = collect($hourlySales)->max(fn ($item) => (float) $item['sales']) ?: 1.0;

        foreach ($hourlySales as &$hRow) {
            $hSalesFloat = (float) $hRow['sales'];
            $hRow['intensity'] = $peakSales > 0 ? min(100, max(0, round(($hSalesFloat / $peakSales) * 100))) : 0;
            $hRow['sales_formatted'] = number_format($hSalesFloat, 2).' '.app(TenantSettings::class)->currencyLabel();
        }
        unset($hRow);

        $peakHourData = collect($hourlySales)->sortByDesc(fn ($item) => (float) $item['sales'])->first();

        // 5. Payment Methods Distribution (Period)
        // PERF-01: SUM(paid_amount) per method in SQL, as exact scale-3 strings (the former
        // Collection::sum() added the decimals as floats: "830" / "426.6").
        $paidByMethod = $this->invoices->byColumn('payment_method', $startDate, $today, $storeId ?: null);
        $paymentMethods = [];
        foreach (['cash', 'instapay', 'e_wallet', 'visa', 'bank_transfer'] as $methodKey) {
            $paymentMethods[$methodKey] = $paidByMethod[$methodKey]['paid'] ?? '0.000';
        }

        $totalPaidMethods = '0.000';
        foreach ($paymentMethods as $mAmount) {
            $totalPaidMethods = bcadd($totalPaidMethods, $mAmount, 3);
        }

        $paymentDistribution = [];
        $methodLabels = [
            'cash' => __('pos.cash_drawer'),
            'instapay' => __('pos.instapay'),
            'e_wallet' => __('pos.e_wallet'),
            'visa' => __('pos.visa_card'),
            'bank_transfer' => __('pos.bank_transfer'),
        ];

        foreach ($paymentMethods as $mKey => $mAmount) {
            $pct = bccomp($totalPaidMethods, '0.000', 3) > 0
                ? bcmul(bcdiv($mAmount, $totalPaidMethods, 4), '100', 1)
                : '0.0';

            $paymentDistribution[] = [
                'key' => $mKey,
                'label' => $methodLabels[$mKey] ?? $mKey,
                'amount' => $mAmount,
                'percentage' => (float) $pct,
            ];
        }

        return [
            'today' => [
                'sales' => $todaySales,
                'gross_sales' => $todayGrossSales,
                'returns' => $todayReturns,
                'invoices_count' => $todayInvoicesCount,
                'basket_size' => $todayBasketSize,
            ],
            'period' => [
                'days' => $trendDays,
                'sales' => $periodSales,
                'gross_sales' => $periodGrossSales,
                'returns' => $periodReturns,
                'invoices_count' => $periodInvoicesCount,
                'basket_size' => $periodBasketSize,
            ],
            'daily_trend' => $dailyTrend,
            'hourly_sales' => array_values($hourlySales),
            'peak_hour' => $peakHourData,
            'payment_distribution' => $paymentDistribution,
        ];
    }
}
