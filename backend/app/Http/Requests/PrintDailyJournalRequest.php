<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Daily journal A4 print. `store_id` is not validated here: ReportStoreFilter parses and
 * access-checks it (admins may pass `all`; malformed values are 422 there).
 */
final class PrintDailyJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->hasRole('admin') || $user->can('daily_journal.view'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }
}
