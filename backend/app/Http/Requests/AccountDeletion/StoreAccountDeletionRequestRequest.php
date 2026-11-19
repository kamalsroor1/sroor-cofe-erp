<?php

declare(strict_types=1);

namespace App\Http\Requests\AccountDeletion;

use App\Http\Requests\Signup\StoreSignupRequestRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * MOB-05: POST /api/v1/public/account-deletion-requests (public, no authentication, throttled).
 *
 * `website` is the honeypot (never shown to people): a filled value passes validation on purpose
 * and the Action drops the request silently. `confirm` is the "I understand deletion is final" box.
 */
final class StoreAccountDeletionRequestRequest extends FormRequest
{
    /** A shop code is the shop's subdomain label. */
    public const SHOP_CODE_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->input('phone'))) {
            $merge['phone'] = StoreSignupRequestRequest::normalizePhone($this->input('phone'));
        }
        if (is_string($this->input('contact_name'))) {
            $merge['contact_name'] = StoreSignupRequestRequest::singleLine($this->input('contact_name'));
        }
        if (is_string($this->input('shop_code'))) {
            $merge['shop_code'] = strtolower(StoreSignupRequestRequest::singleLine($this->input('shop_code')));
        }
        if (is_string($this->input('email'))) {
            $merge['email'] = trim($this->input('email'));
        }
        if (is_string($this->input('reason'))) {
            $merge['reason'] = StoreSignupRequestRequest::multiLine($this->input('reason'));
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contact_name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'regex:'.StoreSignupRequestRequest::PHONE_PATTERN],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'shop_code' => ['nullable', 'string', 'max:63', 'regex:'.self::SHOP_CODE_PATTERN],
            'reason' => ['nullable', 'string', 'max:1000'],
            'confirm' => ['required', 'accepted'],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm.required' => (string) __('account_deletion.validation.confirm_required'),
            'confirm.accepted' => (string) __('account_deletion.validation.confirm_required'),
            'phone.regex' => (string) __('account_deletion.validation.phone_invalid'),
            'shop_code.regex' => (string) __('account_deletion.validation.shop_code_invalid'),
        ];
    }
}
