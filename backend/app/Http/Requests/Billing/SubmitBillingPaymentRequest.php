<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\Billing\BillingPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/billing/invoices/{id}/payments (multipart), tenant permission billing.manage.
 *
 * The receipt is jpg/jpeg/png/webp/pdf up to billing.receipt.max_kb (5 MB). SVG is refused twice:
 * the `mimes` rule checks the real content type, and `extensions` checks the client name, so a
 * script-bearing SVG renamed or retyped cannot pass. The file name is never used on disk.
 */
final class SubmitBillingPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('billing.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var list<string> $mimes */
        $mimes = (array) config('billing.receipt.mimes', ['jpg', 'jpeg', 'png', 'webp', 'pdf']);

        return [
            'receipt' => [
                'required',
                'file',
                'mimes:'.implode(',', $mimes),
                'extensions:'.implode(',', $mimes),
                'max:'.max(1, (int) config('billing.receipt.max_kb', 5120)),
            ],
            'method' => ['required', 'string', Rule::in([
                BillingPaymentMethod::Instapay->value,
                BillingPaymentMethod::VodafoneCash->value,
                BillingPaymentMethod::BankTransfer->value,
            ])],
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,9}(\.\d{1,3})?$/'],
            'payer_reference' => ['required', 'string', 'min:3', 'max:100', 'not_regex:/[<>]/'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
        ];
    }
}
