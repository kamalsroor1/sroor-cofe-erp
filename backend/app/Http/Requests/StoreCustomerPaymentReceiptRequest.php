<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCustomerPaymentReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin')
            || $this->user()?->can('customers.manage')
            || $this->user()?->can('daily_journal.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'customer_id'    => ['required', 'integer', 'exists:customers,id'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'invoice_id'     => ['nullable', 'integer', 'exists:invoices,id'],
            'payment_date'   => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'in:cash,instapay,e_wallet,visa,bank_transfer,check,other'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ];
    }
}
