<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GetDailyJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || $this->user()?->can('daily_journal.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'date'     => ['sometimes', 'date'],
            'store_id' => ['sometimes', 'integer', 'exists:stores,id'],
        ];
    }
}
