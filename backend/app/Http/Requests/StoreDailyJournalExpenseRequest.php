<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDailyJournalExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('daily_journal.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'title'          => ['required', 'string', 'max:255'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'cost_center'    => ['required', 'string', 'max:50'],
            'payment_method' => ['required', 'string', 'in:cash,instapay,wallet,bank,visa,e_wallet,bank_transfer'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ];
    }
}
