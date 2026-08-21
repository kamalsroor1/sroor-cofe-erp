<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('invoices.create') || $this->user()?->can('pos.access') ?? true;
    }

    public function rules(): array
    {
        return [
            'customer_id'             => ['required', 'integer', 'exists:customers,id'],
            'store_id'                => ['nullable', 'integer', 'exists:stores,id'],
            'invoice_date'            => ['nullable', 'date'],
            'payment_type'            => ['required', 'in:cash,credit,partial,bank_transfer'],
            'payment_method'          => ['nullable', 'string', 'in:cash,instapay,e_wallet,visa,bank_transfer'],
            'paid_amount'             => ['nullable', 'numeric', 'min:0'],
            'discount_type'           => ['nullable', 'in:fixed,percentage'],
            'discount_value'          => ['nullable', 'numeric', 'min:0'],
            'notes'                   => ['nullable', 'string', 'max:1000'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.item_id'         => ['required', 'integer', 'exists:items,id'],
            'items.*.quantity'        => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'      => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'additional_expenses'     => ['nullable', 'array'],
        ];
    }
}
