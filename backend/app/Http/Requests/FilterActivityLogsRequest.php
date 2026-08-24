<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class FilterActivityLogsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        return $user->can('logs.view') || $user->hasRole('admin') || $user->hasRole('super_admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'search'    => ['nullable', 'string', 'max:100'],
            'module'    => ['nullable', 'string', 'max:50'],
            'action'    => ['nullable', 'string', 'max:50'],
            'user_id'   => ['nullable'],
            'store_id'  => ['nullable'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date'   => ['nullable', 'date_format:Y-m-d'],
            'from'      => ['nullable', 'date_format:Y-m-d'],
            'to'        => ['nullable', 'date_format:Y-m-d'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:200'],
            'page'      => ['nullable', 'integer', 'min:1'],
        ];
    }
}
