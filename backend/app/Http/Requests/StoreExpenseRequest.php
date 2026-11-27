<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\DocumentDate;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('expenses.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        // MGT-153: accept the idempotency key from the body or the standard Idempotency-Key header.
        if (! $this->filled('client_uuid') && $this->hasHeader('Idempotency-Key')) {
            $key = trim((string) $this->header('Idempotency-Key'));
            if ($key !== '') {
                $this->merge(['client_uuid' => $key]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['nullable', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'cost_center' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date', new DocumentDate($this->user())],
            'payment_method' => ['required', 'string', 'in:cash,instapay,e_wallet,visa,bank_transfer,check'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
