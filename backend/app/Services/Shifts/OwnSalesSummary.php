<?php

declare(strict_types=1);

namespace App\Services\Shifts;

use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\ReturnDocument;
use App\Models\User;
use App\Services\Reports\InvoiceAggregates;
use App\Services\Reports\ReportDateRange;
use App\Support\Money\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * ROLE-01: what a user without `reports.view` (a cashier) may see of the money: only the
 * documents THEY booked, within the open shift of the store they work in (dashboard), or within
 * that shift / their business day (treasury summary). Never the drawer balance,
 * other users' sales or store-wide totals.
 *
 * Documents are not linked to a shift by id; ShiftService ties them to a shift by its store and
 * time window [opened_at, closed_at ?? now] on `created_at`. This class narrows that same window
 * to `user_id` = the caller. Without a shift a business date may be given (documents of that day,
 * on their document-date columns); with neither, every figure is zero.
 *
 * Shared by GetOwnShiftDashboardAction and GetOwnShiftTreasurySummaryAction. Money is returned
 * as scale-3 strings; sums run in SQL.
 */
final class OwnSalesSummary
{
    /**
     * The shift whose time window frames the caller's figures.
     *
     * ShiftService allows ONE open shift per store (a shared drawer): cashier B sells inside the
     * shift cashier A opened. So with a single store the window is the caller's own open shift
     * there if one exists, else the store's open shift (the figures stay filtered by
     * `user_id` = caller in totals() / recentInvoices()). Without a store ($storeId null, a
     * verified `X-Store-Id: all`) only the caller's own open shift, in any store, is used.
     */
    public function windowShiftFor(User $user, ?int $storeId): ?CashShift
    {
        $own = CashShift::query()
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->when($storeId !== null, fn (Builder $q) => $q->where('store_id', $storeId))
            ->latest('opened_at')
            ->latest('id')
            ->first();

        if ($own instanceof CashShift || $storeId === null) {
            return $own;
        }

        return CashShift::query()
            ->where('store_id', $storeId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->latest('id')
            ->first();
    }

    /**
     * The shift block shown to a user without `reports.view`: never a cash figure, and the
     * opener's name only when the caller opened it (`is_own`).
     *
     * @return array{id: int, shift_number: string, opened_at: string|null, is_own: bool, user_name?: string}
     */
    public function shiftBlock(User $user, CashShift $shift): array
    {
        $isOwn = $shift->user_id !== null && (int) $shift->user_id === (int) $user->id;

        $block = [
            'id' => (int) $shift->id,
            'shift_number' => (string) ($shift->shift_number ?? $shift->id),
            'opened_at' => $shift->opened_at?->toDateTimeString(),
            'is_own' => $isOwn,
        ];

        if ($isOwn) {
            $block['user_name'] = (string) $user->name;
        }

        return $block;
    }

    /**
     * @return array{invoices_count: int, gross_sales: string, cash_sales: string, credit_sales: string, partial_sales: string, paid_total: string, returns_value: string, returns_count: int, net_sales: string, expenses_total: string, expenses_count: int}
     */
    public function totals(User $user, int $storeId, ?CashShift $shift, ?string $businessDate = null): array
    {
        if (! $shift instanceof CashShift && ($businessDate === null || $businessDate === '')) {
            return $this->zeros();
        }

        $invoices = $this->scoped(Invoice::query()->where('status', 'confirmed'), $user, $storeId, $shift, $businessDate, 'invoice_date')
            ->toBase()
            ->select('payment_type')
            ->selectRaw('COUNT(*) as invoices_count')
            ->selectRaw('COALESCE(SUM(net_total), 0) as net_total_sum')
            ->selectRaw('COALESCE(SUM(paid_amount), 0) as paid_sum')
            ->groupBy('payment_type')
            ->get();

        $count = 0;
        $gross = '0.000';
        $paid = '0.000';
        $byType = ['cash' => '0.000', 'credit' => '0.000', 'partial' => '0.000'];
        foreach ($invoices as $row) {
            $net = Decimal::normalize(InvoiceAggregates::scalar($row->net_total_sum));
            $count += (int) $row->invoices_count;
            $gross = bcadd($gross, $net, 3);
            $paid = bcadd($paid, Decimal::normalize(InvoiceAggregates::scalar($row->paid_sum)), 3);
            $type = (string) $row->payment_type;
            if (array_key_exists($type, $byType)) {
                $byType[$type] = bcadd($byType[$type], $net, 3);
            }
        }

        $returns = $this->scoped(ReturnDocument::query()->where('return_type', 'sales_return'), $user, $storeId, $shift, $businessDate, 'return_date')
            ->toBase()
            ->selectRaw('COUNT(*) as returns_count')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as returns_sum')
            ->first(); // an aggregate without GROUP BY always returns one row
        $returnsValue = Decimal::normalize(InvoiceAggregates::scalar($returns->returns_sum));

        $expenses = $this->scoped(Expense::query(), $user, $storeId, $shift, $businessDate, 'expense_date')
            ->toBase()
            ->selectRaw('COUNT(*) as expenses_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as expenses_sum')
            ->first();

        return [
            'invoices_count' => $count,
            'gross_sales' => $gross,
            'cash_sales' => $byType['cash'],
            'credit_sales' => $byType['credit'],
            'partial_sales' => $byType['partial'],
            'paid_total' => $paid,
            'returns_value' => $returnsValue,
            'returns_count' => (int) $returns->returns_count,
            // BUG-01: sales are shown net of the returns of the same scope.
            'net_sales' => bcsub($gross, $returnsValue, 3),
            'expenses_total' => Decimal::normalize(InvoiceAggregates::scalar($expenses->expenses_sum)),
            'expenses_count' => (int) $expenses->expenses_count,
        ];
    }

    /**
     * The caller's latest non-cancelled invoices of their shift (empty without a shift).
     *
     * @return list<array<string, mixed>>
     */
    public function recentInvoices(User $user, int $storeId, ?CashShift $shift, int $limit = 8): array
    {
        if (! $shift instanceof CashShift) {
            return [];
        }

        return $this->scoped(Invoice::query()->with('customer:id,name')->where('status', '!=', 'cancelled'), $user, $storeId, $shift, null, 'invoice_date')
            ->latest('id')
            ->take($limit)
            ->get()
            ->map(fn (Invoice $inv): array => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'customer_name' => data_get($inv, 'customer.name') ?? __('pos.walk_in_customer'),
                'invoice_date' => $inv->invoice_date?->toDateString() ?? $inv->created_at?->toDateString(),
                'net_total' => Decimal::normalize((string) $inv->net_total),
                'paid_amount' => Decimal::normalize((string) $inv->paid_amount),
                'remaining_amount' => Decimal::normalize((string) $inv->remaining_amount),
                'payment_type' => $inv->payment_type,
                'status' => $inv->status,
            ])
            ->values()
            ->all();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function scoped(Builder $query, User $user, int $storeId, ?CashShift $shift, ?string $businessDate, string $dateColumn): Builder
    {
        $query->where('user_id', $user->id)->where('store_id', $storeId);

        if ($shift instanceof CashShift) {
            $query->where('created_at', '>=', $shift->opened_at);
            if ($shift->closed_at !== null) {
                $query->where('created_at', '<=', $shift->closed_at);
            }

            return $query;
        }

        ReportDateRange::apply($query, $dateColumn, $businessDate, $businessDate);

        return $query;
    }

    /**
     * Every figure zero (no shift / no business day in scope).
     *
     * @return array{invoices_count: int, gross_sales: string, cash_sales: string, credit_sales: string, partial_sales: string, paid_total: string, returns_value: string, returns_count: int, net_sales: string, expenses_total: string, expenses_count: int}
     */
    public function zeros(): array
    {
        return [
            'invoices_count' => 0,
            'gross_sales' => '0.000',
            'cash_sales' => '0.000',
            'credit_sales' => '0.000',
            'partial_sales' => '0.000',
            'paid_total' => '0.000',
            'returns_value' => '0.000',
            'returns_count' => 0,
            'net_sales' => '0.000',
            'expenses_total' => '0.000',
            'expenses_count' => 0,
        ];
    }
}
