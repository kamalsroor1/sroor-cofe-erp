<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('stores.manage') || $this->user()?->can('stores.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'from_store_id'    => ['required', 'different:to_store_id', 'exists:stores,id'],
            'to_store_id'      => ['required', 'different:from_store_id', 'exists:stores,id'],
            'transfer_date'    => ['required', 'date'],
            'notes'            => ['nullable', 'string', 'max:500'],
            'items'            => ['required', 'array', 'min:1'],
            'items.*.item_id'  => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ];
    }
}
