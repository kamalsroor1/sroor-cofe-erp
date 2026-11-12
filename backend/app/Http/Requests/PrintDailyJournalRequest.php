<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Daily journal A4 print (daily_journal.view + reports.view). `store_id` is not validated here: ReportStoreFilter parses and
 * access-checks it (admins may pass `all`; malformed values are 422 there).
 */
final class PrintDailyJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        // ROLE-01: the A4 sheet is the store-wide ledger (every user's invoices and expenses, the
        // drawer's opening float and expected cash, every shift of the day). A user without
        // reports.view (a cashier) reads only their own figures on GET /api/v1/daily-journal, so
        // the print is refused (403) rather than narrowed. Admin passes through Gate::before.
        return $user !== null && ($user->hasRole('admin') || ($user->can('daily_journal.view') && $user->can('reports.view')));
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
