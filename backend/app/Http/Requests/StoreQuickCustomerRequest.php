<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreQuickCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin')
            || $this->user()?->can('pos.access')
            || $this->user()?->can('customers.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:20'],
            'price_tier' => ['nullable', 'in:retail,wholesale'],
            'address'    => ['nullable', 'string', 'max:255'],
        ];
    }
}
