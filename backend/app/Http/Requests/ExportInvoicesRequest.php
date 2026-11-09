<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Same filters as the invoices index, plus an optional `ids` list for "export selected".
 * Same permission as listing invoices.
 */
class ExportInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->hasRole('admin') || $user->can('invoices.view') || $user->can('pos.access'));
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:20'],
            'payment_type' => ['nullable', 'string', 'max:20'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'customer_id' => ['nullable'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'ids' => ['nullable', 'array', 'max:500'],
            'ids.*' => ['integer', 'min:1'],
        ];
    }
}
