<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('returns.create') || $this->user()?->can('returns.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'return_type'         => ['required', 'string', 'in:sales_return,purchase_return'],
            'customer_id'         => ['nullable', 'required_if:return_type,sales_return', 'exists:customers,id'],
            'supplier_id'         => ['nullable', 'required_if:return_type,purchase_return', 'exists:suppliers,id'],
            'return_date'         => ['required', 'date'],
            'refund_amount'       => ['nullable', 'numeric', 'min:0'],
            'reason'              => ['nullable', 'string', 'max:500'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.item_id'     => ['required', 'exists:items,id'],
            'items.*.quantity'    => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
        ];
    }
}
