<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('items.manage') ?? false;
    }

    public function rules(): array
    {
        $itemId = $this->route('id') ?? $this->route('item');

        return [
            'name'            => ['required', 'string', 'max:255'],
            'code'            => ['nullable', 'string', 'max:100', 'unique:items,code,' . $itemId],
            'category'        => ['nullable', 'string', 'max:100'],
            'unit'            => ['required', 'string', 'max:50'],
            'cost_price'      => ['required', 'numeric', 'min:0'],
            'selling_price'   => ['required', 'numeric', 'min:0'],
            'min_stock_level' => ['nullable', 'numeric', 'min:0'],
            'is_active'       => ['sometimes', 'boolean'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ];
    }
}
