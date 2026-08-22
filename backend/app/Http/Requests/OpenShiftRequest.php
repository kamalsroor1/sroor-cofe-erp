<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('daily_journal.view') || $this->user()?->can('pos.sell') ?? false;
    }

    public function rules(): array
    {
        return [
            'opening_cash_balance' => ['required', 'numeric', 'min:0'],
            'notes'                => ['nullable', 'string', 'max:500'],
        ];
    }
}
