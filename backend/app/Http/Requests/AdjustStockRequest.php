<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('items.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'store_id'      => ['required', 'integer', 'exists:stores,id'],
            'movement_type' => ['required', 'string', 'in:stock_adjustment_in,stock_adjustment_out,stock_deposit_in,waste_out'],
            'quantity'      => ['required', 'numeric', 'min:0.001'],
            'unit_cost'     => ['nullable', 'numeric', 'min:0'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ];
    }
}
