<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name'             => ['required', 'string', 'max:255'],
            'phone'            => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($userId)],
            'email'            => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'current_password' => ['nullable', 'required_with:new_password', 'string'],
            'new_password'     => ['nullable', 'string', 'min:6', 'confirmed'],
            'theme_preference' => ['required', 'in:dark,light'],
        ];
    }
}
