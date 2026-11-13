<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\Billing\BillingPaymentMethod;
use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/super-admin/billing-invoices/{invoice}/record-payment (OPS-20):
 * super_admin.billing.manage. Only the manual methods are accepted (the gateways record
 * their own payments). The "amount <= remaining" rule needs the invoice lock, so the Action owns it.
 * `idempotency_key` (optional UUID) makes a double click / retry return the payment already recorded.
 */
final class RecordBillingPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::BillingManage);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,9}(\.\d{1,3})?$/'],
            'method' => ['required', 'string', Rule::in([
                BillingPaymentMethod::Instapay->value,
                BillingPaymentMethod::VodafoneCash->value,
                BillingPaymentMethod::BankTransfer->value,
                BillingPaymentMethod::Cash->value,
            ])],
            'reference' => ['nullable', 'string', 'max:100', 'not_regex:/[<>]/'],
            'note' => ['nullable', 'string', 'max:500', 'not_regex:/[<>]/'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            // OPS-20: one UUID per opened "record payment" dialog; a retry with it records nothing new.
            'idempotency_key' => ['nullable', 'string', 'uuid'],
        ];
    }
}
