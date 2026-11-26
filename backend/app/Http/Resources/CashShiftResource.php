<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CashShift;
use App\Support\Money\Decimal;
use App\Support\TenantClock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashShift
 */
class CashShiftResource extends JsonResource
{
    /**
     * The drawer / cash figures of the shift. ROLE-01: nulled (keys kept) by withoutCashFigures().
     */
    public const CASH_FIELDS = [
        'opening_cash_balance', 'total_cash_sales', 'total_credit_sales', 'total_payments_collected',
        'total_expenses', 'total_refunds', 'expected_cash_balance', 'actual_cash_balance', 'cash_difference',
    ];

    /**
     * DEC-SHIFT blind count: the figures a cashier must not know before counting the drawer.
     */
    public const BLIND_COUNT_FIELDS = ['expected_cash_balance', 'actual_cash_balance', 'cash_difference'];

    private bool $hideCash = false;

    private bool $blindCount = false;

    /**
     * ROLE-01: a reader without `reports.view` looking at a shift they did not open sees no cash
     * figure of the shared drawer (every CASH_FIELDS key is null). Off by default.
     */
    public function withoutCashFigures(bool $hide = true): static
    {
        $this->hideCash = $hide;

        return $this;
    }

    /**
     * DEC-SHIFT blind count: an OPEN shift read by a user without `reports.view` shows no expected
     * cash (nor actual / difference, which an open shift does not have yet): BLIND_COUNT_FIELDS are
     * null (keys kept). Off by default.
     */
    public function withBlindCount(bool $blind = true): static
    {
        $this->blindCount = $blind;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $clock = app(TenantClock::class);

        $data = [
            'id' => $this->id,
            'shift_number' => $this->shift_number,
            'status' => $this->status,
            // SHIFT-01: per_cashier | shared, the branch mode the shift was opened in.
            'shift_mode' => $this->shift_mode,
            'store_id' => $this->store_id,
            'store_name' => $this->store?->name,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'opened_at' => $clock->toTenant($this->opened_at)?->toDateTimeString(),
            'closed_at' => $clock->toTenant($this->closed_at)?->toDateTimeString(),
            'opening_cash_balance' => Decimal::normalize((string) $this->opening_cash_balance),
            'total_cash_sales' => Decimal::normalize((string) ($this->total_cash_sales ?? 0)),
            'total_credit_sales' => Decimal::normalize((string) ($this->total_credit_sales ?? 0)),
            'total_payments_collected' => Decimal::normalize((string) ($this->total_payments_collected ?? 0)),
            'total_expenses' => Decimal::normalize((string) ($this->total_expenses ?? 0)),
            'total_supplier_paid' => Decimal::normalize((string) ($this->total_supplier_paid ?? 0)),
            'total_refunds' => Decimal::normalize((string) ($this->total_refunds ?? 0)),
            'expected_cash_balance' => Decimal::normalize((string) ($this->expected_cash_balance ?? 0)),
            'actual_cash_balance' => Decimal::normalize((string) ($this->actual_cash_balance ?? 0)),
            'cash_difference' => Decimal::normalize((string) ($this->cash_difference ?? 0)),
            'notes' => $this->notes,
            'created_at' => $clock->toTenant($this->created_at)?->toDateTimeString(),
        ];

        if ($this->hideCash) {
            foreach (self::CASH_FIELDS as $field) {
                $data[$field] = null;
            }
        }

        if ($this->blindCount) {
            foreach (self::BLIND_COUNT_FIELDS as $field) {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
