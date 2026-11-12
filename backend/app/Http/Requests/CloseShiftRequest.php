<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('daily_journal.close_shift') ?? false;
    }

    public function rules(): array
    {
        return [
            // BUG-03: the daily journal and the POS post the open shift's id; omitted/null closes the
            // active store's open shift.
            'shift_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'actual_cash_balance' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
