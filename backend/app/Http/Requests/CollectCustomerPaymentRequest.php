<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CollectCustomerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('customers.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'in:cash,instapay,wallet,bank,visa,e_wallet,bank_transfer'],
            'payment_date'   => ['required', 'date'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ];
    }
}
