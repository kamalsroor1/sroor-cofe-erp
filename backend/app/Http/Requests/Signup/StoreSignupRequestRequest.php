<?php

declare(strict_types=1);

namespace App\Http\Requests\Signup;

use App\Enums\SignupBusinessType;
use App\Enums\SignupRequestStatus;
use App\Models\Plan;
use App\Models\SignupRequest;
use App\Rules\AvailableSubdomain;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PRD-05: POST /api/v1/public/signup-requests (public, no authentication).
 *
 * `website` is the honeypot: real users never see it. A filled value is accepted by the
 * validator on purpose (the Action then drops the request silently), so a bot gets no signal.
 */
final class StoreSignupRequestRequest extends FormRequest
{
    /** Egyptian mobile after normalisation: 010 / 011 / 012 / 015 + 8 digits. */
    public const PHONE_PATTERN = '/^01[0125][0-9]{8}$/';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->input('phone'))) {
            $merge['phone'] = self::normalizePhone($this->input('phone'));
        }

        if (is_string($this->input('preferred_subdomain'))) {
            $merge['preferred_subdomain'] = strtolower(self::singleLine($this->input('preferred_subdomain')));
        }

        foreach (['business_name', 'contact_name', 'city'] as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = self::singleLine($this->input($field));
            }
        }

        if (is_string($this->input('email'))) {
            $merge['email'] = trim($this->input('email'));
        }

        if (is_string($this->input('notes'))) {
            $merge['notes'] = self::multiLine($this->input('notes'));
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
            'contact_name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'regex:'.self::PHONE_PATTERN, $this->noPendingRequestForPhone()],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'business_type' => ['required', 'string', Rule::in(SignupBusinessType::values())],
            'plan_code' => ['nullable', 'string', 'max:50', $this->publicPlanExists()],
            'preferred_subdomain' => ['nullable', 'string', new AvailableSubdomain(checkTaken: false)],
            'notes' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** Control chars (incl. newlines), line/paragraph separators and bidi overrides become spaces; whitespace collapses. */
    public static function singleLine(string $value): string
    {
        $clean = preg_replace('/[\p{Cc}\x{2028}\x{2029}\x{202A}-\x{202E}\x{2066}-\x{2069}]+/u', ' ', $value);
        $clean = preg_replace('/\s+/u', ' ', $clean ?? '');

        return trim($clean ?? '');
    }

    /** Newlines are kept (normalised to "\n"); every other control char and bidi override is removed. */
    public static function multiLine(string $value): string
    {
        $clean = str_replace(["\r\n", "\r"], "\n", $value);
        $clean = preg_replace('/[^\P{Cc}\n]|[\x{2028}\x{2029}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $clean);

        return trim($clean ?? '');
    }

    /**
     * Arabic-Indic digits, spaces, dashes and the +20 / 0020 prefixes to a local "01xxxxxxxxx".
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = strtr(trim($phone), [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $digits = (string) preg_replace('/[\s\-().]/', '', $digits);

        if (str_starts_with($digits, '+20')) {
            $digits = '0'.substr($digits, 3);
        } elseif (str_starts_with($digits, '0020')) {
            $digits = '0'.substr($digits, 4);
        } elseif (preg_match('/^20(1[0125][0-9]{8})$/', $digits, $m) === 1) {
            $digits = '0'.$m[1];
        }

        return $digits;
    }

    /**
     * @return Closure(string, mixed, Closure): void
     */
    private function noPendingRequestForPhone(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $exists = is_string($value) && SignupRequest::query()
                ->where('phone', $value)
                ->where('status', SignupRequestStatus::Pending->value)
                ->exists();

            if ($exists) {
                $fail('signup.validation.duplicate_pending')->translate();
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
