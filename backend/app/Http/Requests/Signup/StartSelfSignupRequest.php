<?php

declare(strict_types=1);

namespace App\Http\Requests\Signup;

use App\Enums\SignupBusinessType;
use App\Models\Plan;
use App\Rules\AvailableSubdomain;
use App\Services\Signup\SubdomainSuggester;
use App\Support\PasswordPolicy;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SIGN-11: POST /api/v1/signup (public, no authentication, central DB, throttled `self-signup`).
 *
 * Same input hygiene as the assisted form (StoreSignupRequestRequest: single-line text, Egyptian
 * phone normalisation, lowercase plan slug). The password follows the tenant-user policy
 * (PasswordPolicy) and must be confirmed. `subdomain` is optional (suggested from the business
 * name when empty); when given it must be free right now. `website` is the honeypot.
 */
final class StartSelfSignupRequest extends FormRequest
{
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

        if (is_string($this->input('subdomain'))) {
            $merge['subdomain'] = strtolower(StoreSignupRequestRequest::singleLine($this->input('subdomain')));
        }

        foreach (['business_name', 'owner_name', 'city'] as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = StoreSignupRequestRequest::singleLine($this->input($field));
            }
        }

        if (is_string($this->input('email'))) {
            $merge['email'] = strtolower(trim($this->input('email')));
        }

        if (is_string($this->input('plan_code'))) {
            $merge['plan_code'] = StoreSignupRequestRequest::normalizePlanCode($this->input('plan_code'));
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'min:2', 'max:150'],
            'owner_name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'regex:'.StoreSignupRequestRequest::PHONE_PATTERN],
            'password' => ['required', 'string', 'max:255', PasswordPolicy::rule(), 'confirmed'],
            'business_type' => ['required', 'string', Rule::in(SignupBusinessType::values())],
            'city' => ['nullable', 'string', 'max:100'],
            'subdomain' => ['nullable', 'string', 'bail', new AvailableSubdomain(checkTaken: true), $this->notHeldBySignup()],
            'plan_code' => ['nullable', 'string', 'max:50', $this->publicPlanExists()],
            'terms_accepted' => ['required', 'accepted'],
            'locale' => ['nullable', 'string', Rule::in(['ar', 'en'])],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'terms_accepted.required' => (string) __('signup.validation.terms_required'),
            'terms_accepted.accepted' => (string) __('signup.validation.terms_required'),
        ];
    }

    /**
     * A code already held by a verified self-signup (waiting for its shop) is taken too.
     *
     * @return Closure(string, mixed, Closure): void
     */
    private function notHeldBySignup(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && ! app(SubdomainSuggester::class)->isAvailable($value)) {
                $fail('signup.validation.subdomain_taken')->translate();
            }
        };
    }

    /**
     * @return Closure(string, mixed, Closure): void
     */
    private function publicPlanExists(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $exists = is_string($value) && Plan::query()
                ->where('slug', $value)
                ->where('is_active', true)
                ->where('is_public', true)
                ->exists();

            if (! $exists) {
                $fail('signup.validation.plan_unknown')->translate();
            }
        };
    }
}
