<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('customers.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:50'],
            'address'         => ['nullable', 'string', 'max:255'],
            'tax_number'      => ['nullable', 'string', 'max:50'],
            'opening_balance' => ['nullable', 'numeric'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ];
    }
}
