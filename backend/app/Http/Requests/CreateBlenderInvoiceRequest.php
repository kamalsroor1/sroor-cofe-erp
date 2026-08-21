<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBlenderInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('invoices.create') || $this->user()?->can('pos.access') ?? false;
    }

    public function rules(): array
    {
        return [
            'blend_name'              => ['required', 'string', 'max:255'],
            'customer_id'             => ['required', 'integer', 'exists:customers,id'],
            'store_id'                => ['nullable', 'integer', 'exists:stores,id'],
            'target_weight_grams'     => ['nullable', 'numeric', 'min:1'],
            'roast_type'              => ['nullable', 'string', 'max:50'],
            'grind_level'             => ['nullable', 'string', 'max:50'],
            'cardamom_grams'          => ['nullable', 'numeric', 'min:0'],
            'notes'                   => ['nullable', 'string', 'max:500'],
            'components'              => ['required', 'array', 'min:1'],
            'components.*.item_id'    => ['required', 'integer', 'exists:items,id'],
            'components.*.grams'      => ['required', 'numeric', 'min:0.1'],
            'components.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
