<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SmartReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->hasRole('admin') || $user->can('purchases.view'));
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'analysis_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'target_cover_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'urgency' => ['nullable', 'string', 'in:all,critical,warning,safe'],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
