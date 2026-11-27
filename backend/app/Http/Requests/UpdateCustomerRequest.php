<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ValidPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('customers.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50', new ValidPhoneNumber],
            'address' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            // SHOP-03: null / empty = no limit; omitted on create = the shop default (customers_default_credit_limit).
            'credit_limit' => ['sometimes', 'nullable', 'numeric', 'min:0', 'decimal:0,3', 'max:999999999.999'],
            'price_tier' => ['sometimes', 'nullable', 'in:retail,wholesale'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
