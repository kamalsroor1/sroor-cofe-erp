<?php

declare(strict_types=1);

namespace App\Http\Requests\Treasury;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SHOP-06: the treasury transfers history. Readable by whoever may transfer, or sees the
 * treasury figures (reports.view); a cashier with only daily_journal.view does not.
 */
final class ListTreasuryTransfersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->can('treasury.transfer') || $user->can('reports.view'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $methods = array_map(fn (PaymentMethod $m): string => $m->value, PaymentMethod::cases());

        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'method' => ['nullable', 'string', Rule::in([...$methods, 'all'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
