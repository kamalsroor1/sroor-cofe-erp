<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('purchases.create') || $this->user()?->can('purchases.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'supplier_id'                             => ['required', 'exists:suppliers,id'],
            'purchase_date'                           => ['required', 'date'],
            'supplier_invoice_ref'                    => ['nullable', 'string', 'max:100'],
            'paid_amount'                             => ['nullable', 'numeric', 'min:0'],
            'discount_amount'                         => ['nullable', 'numeric', 'min:0'],
            'notes'                                   => ['nullable', 'string', 'max:500'],
            'items'                                   => ['required', 'array', 'min:1'],
            'items.*.item_id'                         => ['required', 'exists:items,id'],
            'items.*.quantity'                        => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_cost'                       => ['required', 'numeric', 'min:0'],
            'additional_expenses'                     => ['nullable', 'array'],
            'additional_expenses.*.title'             => ['nullable', 'string', 'max:150'],
            'additional_expenses.*.amount'            => ['nullable', 'numeric', 'min:0'],
            'additional_expenses.*.allocation_method' => ['nullable', 'string', 'in:by_quantity,by_value,equal'],
            'additional_expenses.*.paid_by'           => ['nullable', 'string', 'in:supplier_account,treasury_cash'],
        ];
    }
}
