<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPriceOverride;
use Illuminate\Foundation\Http\FormRequest;

class CancelInvoiceRequest extends FormRequest
{
    use ValidatesPriceOverride;

    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') || ($this->user()?->can('invoices.cancel') ?? false);
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');
        $fallback = $this->input('cancellation_reason');

        if (is_string($reason)) {
            $reason = trim($reason);
            $this->merge(['reason' => $reason]);
        }

        if (($reason === null || $reason === '') && is_string($fallback)) {
            $trimmedFallback = trim($fallback);
            if ($trimmedFallback !== '') {
                $this->merge([
                    'reason' => $trimmedFallback,
                    'cancellation_reason' => $trimmedFallback,
                ]);
            }
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return array_merge([
            'reason' => ['required', 'string', 'max:500'],
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ], $this->priceOverrideRules());
    }
}
