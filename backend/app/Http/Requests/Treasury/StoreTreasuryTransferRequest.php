<?php

declare(strict_types=1);

namespace App\Http\Requests\Treasury;

use App\Enums\PaymentMethod;
use App\Rules\DocumentDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SHOP-06: move money between two treasury accounts (payment methods) of the active branch.
 */
final class StoreTreasuryTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('treasury.transfer') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $methods = array_map(fn (PaymentMethod $m): string => $m->value, PaymentMethod::cases());

        return [
            'from_method' => ['required', 'string', Rule::in($methods)],
            'to_method' => ['required', 'string', Rule::in($methods), 'different:from_method'],
            'amount' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
            'transfer_fee' => ['nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
            'transfer_date' => ['nullable', 'date', new DocumentDate($this->user())],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_method.different' => __('treasury.same_account_error'),
        ];
    }
}
