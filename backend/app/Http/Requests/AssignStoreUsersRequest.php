<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignStoreUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('stores.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'user_ids'   => ['nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
        ];
    }
}
