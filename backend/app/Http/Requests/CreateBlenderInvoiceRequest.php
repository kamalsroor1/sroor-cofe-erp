<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBlenderInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('invoices.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'blend_name'              => ['required', 'string', 'max:255'],
            'customer_id'             => ['required', 'exists:customers,id'],
            'components'              => ['required', 'array', 'min:1'],
            'components.*.item_id'    => ['required', 'exists:items,id'],
            'components.*.grams'      => ['required', 'numeric', 'min:1'],
            'components.*.unit_price' => ['required', 'numeric', 'min:0'],
            'notes'                   => ['nullable', 'string', 'max:500'],
        ];
    }
}
