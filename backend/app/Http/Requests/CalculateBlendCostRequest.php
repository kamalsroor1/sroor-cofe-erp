<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CalculateBlendCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('items.create') || $this->user()?->can('pos.access') ?? false;
    }

    public function rules(): array
    {
        return [
            'target_weight_grams'     => ['nullable', 'numeric', 'min:1'],
            'cardamom_grams'          => ['nullable', 'numeric', 'min:0'],
            'components'              => ['required', 'array', 'min:1'],
            'components.*.item_id'    => ['required', 'integer', 'exists:items,id'],
            'components.*.percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'components.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
