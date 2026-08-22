<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'shift_number'             => $this->shift_number,
            'status'                   => $this->status,
            'store_id'                 => $this->store_id,
            'store_name'               => $this->store?->name,
            'user_id'                  => $this->user_id,
            'user_name'                => $this->user?->name,
            'opened_at'                => $this->opened_at?->toDateTimeString(),
            'closed_at'                => $this->closed_at?->toDateTimeString(),
            'opening_cash_balance'     => (float)$this->opening_cash_balance,
            'total_cash_sales'         => (float)($this->total_cash_sales ?? 0),
            'total_credit_sales'       => (float)($this->total_credit_sales ?? 0),
            'total_payments_collected' => (float)($this->total_payments_collected ?? 0),
            'total_expenses'           => (float)($this->total_expenses ?? 0),
            'total_refunds'            => (float)($this->total_refunds ?? 0),
            'expected_cash_balance'    => (float)($this->expected_cash_balance ?? 0),
            'actual_cash_balance'      => (float)($this->actual_cash_balance ?? 0),
            'cash_difference'          => (float)($this->cash_difference ?? 0),
            'notes'                    => $this->notes,
            'created_at'               => $this->created_at?->toDateTimeString(),
        ];
    }
}
