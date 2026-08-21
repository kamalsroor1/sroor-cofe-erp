<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'shift_number'         => $this->shift_number ?? $this->id,
            'user_name'            => $this->user?->name ?? __('common.unspecified'),
            'store_id'             => $this->store_id,
            'status'               => $this->status,
            'opening_cash_balance' => (string)$this->opening_cash_balance,
            'actual_cash_balance'  => (string)($this->actual_cash_balance ?? '0.000'),
            'expected_cash_balance'=> (string)($this->expected_cash_balance ?? '0.000'),
            'cash_difference'      => (string)($this->cash_difference ?? '0.000'),
            'opened_at'            => $this->opened_at?->toIso8601String() ?? (string)$this->opened_at,
            'closed_at'            => $this->closed_at?->toIso8601String() ?? (string)$this->closed_at,
        ];
    }
}
