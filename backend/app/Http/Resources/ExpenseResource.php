<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'expense_number'    => $this->expense_number,
            'title'             => $this->title,
            'category'          => $this->category,
            'cost_center'       => $this->cost_center,
            'cost_center_label' => $this->cost_center_label,
            'amount'            => (float)$this->amount,
            'expense_date'      => $this->expense_date ? $this->expense_date->toDateString() : $this->created_at?->toDateString(),
            'payment_method'    => $this->payment_method,
            'user_name'         => $this->user?->name,
            'store_name'        => $this->store?->name,
            'notes'             => $this->notes,
            'created_at'        => $this->created_at?->toDateTimeString(),
        ];
    }
}
