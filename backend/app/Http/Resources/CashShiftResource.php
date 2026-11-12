<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CashShift;
use App\Support\Money\Decimal;
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

    private bool $hideCash = false;

    /**
     * ROLE-01: a reader without `reports.view` looking at a shift they did not open sees no cash
     * figure of the shared drawer (every CASH_FIELDS key is null). Off by default.
     */
    public function withoutCashFigures(bool $hide = true): static
    {
        $this->hideCash = $hide;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'shift_number' => $this->shift_number,
            'status' => $this->status,
            'store_id' => $this->store_id,
            'store_name' => $this->store?->name,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'opened_at' => $this->opened_at?->toDateTimeString(),
            'closed_at' => $this->closed_at?->toDateTimeString(),
            'opening_cash_balance' => Decimal::normalize((string) $this->opening_cash_balance),
            'total_cash_sales' => Decimal::normalize((string) ($this->total_cash_sales ?? 0)),
            'total_credit_sales' => Decimal::normalize((string) ($this->total_credit_sales ?? 0)),
            'total_payments_collected' => Decimal::normalize((string) ($this->total_payments_collected ?? 0)),
            'total_expenses' => Decimal::normalize((string) ($this->total_expenses ?? 0)),
            'total_refunds' => Decimal::normalize((string) ($this->total_refunds ?? 0)),
            'expected_cash_balance' => Decimal::normalize((string) ($this->expected_cash_balance ?? 0)),
            'actual_cash_balance' => Decimal::normalize((string) ($this->actual_cash_balance ?? 0)),
            'cash_difference' => Decimal::normalize((string) ($this->cash_difference ?? 0)),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];

        if ($this->hideCash) {
            foreach (self::CASH_FIELDS as $field) {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
