<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Reports\SalesReturnAggregates;
use App\Services\Settings\TenantSettings;
use App\Support\Money\Decimal;
use App\Support\TenantClock;
use Carbon\Carbon;

class DashboardAnalyticsService
{
    public function __construct(
        private readonly TenantClock $tenantClock,
        private readonly SalesReturnAggregates $salesReturns,
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

        // 1. Today's Core Invoices
        $todayInvoicesQuery = Invoice::where('status', 'confirmed')
            ->whereDate('invoice_date', $today);

        if ($storeId) {
            $todayInvoicesQuery->where('store_id', $storeId);
        }

        // BUG-01: sales figures are NET of the sales returns booked on the same days. The basket
        // size stays the average issued invoice (returns are separate documents).
        $returnsByDay = $this->salesReturns->valueByDate($startDate, $today, $storeId);

        $todayInvoices = $todayInvoicesQuery->get();
        $todayGrossSales = $this->sumNetTotal($todayInvoices);
        $todayReturns = $returnsByDay[$today] ?? '0.000';
        $todaySales = bcsub($todayGrossSales, $todayReturns, 3);
        $todayInvoicesCount = $todayInvoices->count();

        $todayBasketSize = $todayInvoicesCount > 0
            ? bcdiv($todayGrossSales, (string) $todayInvoicesCount, 2)
            : '0.00';

        // 2. Period Invoices (Last N days)
        $periodInvoicesQuery = Invoice::where('status', 'confirmed')
            ->whereDate('invoice_date', '>=', $startDate)
            ->whereDate('invoice_date', '<=', $today);

        if ($storeId) {
            $periodInvoicesQuery->where('store_id', $storeId);
        }

        $periodInvoices = $periodInvoicesQuery->get();
        $periodGrossSales = $this->sumNetTotal($periodInvoices);
        $periodReturns = '0.000';
        foreach ($returnsByDay as $dayReturns) {
            $periodReturns = bcadd($periodReturns, $dayReturns, 3);
        }
        $periodSales = bcsub($periodGrossSales, $periodReturns, 3);
        $periodInvoicesCount = $periodInvoices->count();

        $periodBasketSize = $periodInvoicesCount > 0
            ? bcdiv($periodGrossSales, (string) $periodInvoicesCount, 2)
            : '0.00';

        // 3. Daily Sales Trend (Last N days)
        $dailyTrend = [];
        for ($i = $trendDays - 1; $i >= 0; $i--) {
            $date = $now->subDays($i)->toDateString();
            $dayName = $now->subDays($i)->locale('ar')->isoFormat('dddd D/M');
            $dayInvoices = $periodInvoices->filter(fn ($inv) => Carbon::parse($inv->invoice_date)->toDateString() === $date);
            $dayReturns = $returnsByDay[$date] ?? '0.000';
            $daySales = bcsub($this->sumNetTotal($dayInvoices), $dayReturns, 3);
            $dayCount = $dayInvoices->count();

            $dailyTrend[] = [
                'date' => $date,
                'label' => $dayName,
                'sales' => (float) $daySales,
                'sales_formatted' => number_format((float) $daySales, 2).' '.app(TenantSettings::class)->currencyLabel(),
                'invoices' => $dayCount,
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

        foreach ($periodInvoices as $inv) {
            $hour = (int) $this->tenantClock->toTenant($inv->created_at)?->format('G');
            if (isset($hourlySales[$hour])) {
                $hourlySales[$hour]['sales'] = bcadd((string) $hourlySales[$hour]['sales'], (string) $inv->net_total, 3);
                $hourlySales[$hour]['invoices']++;
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
        $paymentMethods = [
            'cash' => (string) ($periodInvoices->where('payment_method', 'cash')->sum('paid_amount') ?: '0.000'),
            'instapay' => (string) ($periodInvoices->where('payment_method', 'instapay')->sum('paid_amount') ?: '0.000'),
            'e_wallet' => (string) ($periodInvoices->where('payment_method', 'e_wallet')->sum('paid_amount') ?: '0.000'),
            'visa' => (string) ($periodInvoices->where('payment_method', 'visa')->sum('paid_amount') ?: '0.000'),
            'bank_transfer' => (string) ($periodInvoices->where('payment_method', 'bank_transfer')->sum('paid_amount') ?: '0.000'),
        ];

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

    /**
     * SUM(net_total) of loaded invoices as an exact scale-3 string (bcmath, never a float sum).
     *
     * @param  iterable<Invoice>  $invoices
     */
    private function sumNetTotal(iterable $invoices): string
    {
        $total = '0.000';
        foreach ($invoices as $invoice) {
            $total = bcadd($total, Decimal::normalize((string) $invoice->net_total), 3);
        }

        return $total;
    }
}
