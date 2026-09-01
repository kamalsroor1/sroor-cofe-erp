<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolveTenantWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare data for validation: normalize code or tenant
     */
    protected function prepareForValidation(): void
    {
        $code = $this->query('code') ?? $this->query('tenant') ?? $this->input('code') ?? $this->input('tenant');
        if ($code !== null) {
            $this->merge([
                'code' => trim((string) $code),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => __('validation.required', ['attribute' => __('auth.workspace_code')]),
            'code.string' => __('validation.string', ['attribute' => __('auth.workspace_code')]),
            'code.min' => __('validation.min.string', ['attribute' => __('auth.workspace_code'), 'min' => 1]),
            'code.max' => __('validation.max.string', ['attribute' => __('auth.workspace_code'), 'max' => 100]),
        ];
    }
}
