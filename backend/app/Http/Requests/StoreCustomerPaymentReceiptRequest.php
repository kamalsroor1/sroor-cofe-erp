<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCustomerPaymentReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        // SEC-1 (M5): customers.manage, or pos.access (cashiers collect customer debts at the
        // counter). Admin passes through Gate::before. daily_journal.view (a read permission)
        // no longer authorizes a receipt.
        $user = $this->user();

        return $user !== null && ($user->can('customers.manage') || $user->can('pos.access'));
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'in:cash,instapay,e_wallet,visa,bank_transfer,check,other'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
