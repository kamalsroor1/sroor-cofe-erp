<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSupplierPaymentVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        // SEC-1 (M5): paying a supplier is a suppliers.manage operation (admin passes through
        // Gate::before). daily_journal.view is a read permission and no longer authorizes it.
        return (bool) $this->user()?->can('suppliers.manage');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'purchase_id' => ['nullable', 'integer', 'exists:purchases,id'],
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'in:cash,instapay,e_wallet,visa,bank_transfer,check,other'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
