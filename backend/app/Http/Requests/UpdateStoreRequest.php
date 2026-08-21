<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('stores.manage') ?? false;
    }

    public function rules(): array
    {
        $storeId = $this->route('id') ?? $this->route('store');

        return [
            'name'      => ['required', 'string', 'max:255'],
            'code'      => ['nullable', 'string', 'max:50', 'unique:stores,code,' . $storeId],
            'type'      => ['required', 'string'],
            'address'   => ['nullable', 'string', 'max:255'],
            'phone'     => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'is_main'   => ['nullable', 'boolean'],
        ];
    }
}
