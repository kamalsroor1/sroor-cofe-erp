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

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return array_merge([
            'reason' => ['nullable', 'string', 'max:500'],
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ], $this->priceOverrideRules());
    }
}
