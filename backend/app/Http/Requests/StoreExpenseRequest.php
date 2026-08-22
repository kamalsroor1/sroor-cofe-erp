<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('expenses.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'title'          => ['required', 'string', 'max:255'],
            'category'       => ['required', 'string', 'max:100'],
            'cost_center'    => ['required', 'string', 'max:50'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'expense_date'   => ['required', 'date'],
            'payment_method' => ['required', 'string', 'in:cash,instapay,e_wallet,visa,bank_transfer,check'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ];
    }
}
