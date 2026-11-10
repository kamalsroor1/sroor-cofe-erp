<?php

declare(strict_types=1);

namespace App\DTOs\Signup;

use App\Enums\SignupBusinessType;

final readonly class CreateSignupRequestDTO
{
    public function __construct(
        public string $businessName,
        public string $contactName,
        public string $phone,
        public ?string $email,
        public ?string $city,
        public SignupBusinessType $businessType,
        public ?string $planCode,
        public ?string $preferredSubdomain,
        public ?string $notes,
        public bool $termsAccepted,
        public string $termsVersion,
        public bool $honeypotFilled,
        public ?string $ip,
        public ?string $userAgent,
    ) {}

    /**
     * @param  array{business_name: string, contact_name: string, phone: string, email?: string|null, city?: string|null, business_type: string, plan_code?: string|null, preferred_subdomain?: string|null, notes?: string|null, terms_accepted?: mixed, website?: string|null}  $data
     */
    public static function fromArray(array $data, ?string $ip = null, ?string $userAgent = null): self
    {
        return new self(
            businessName: $data['business_name'],
            contactName: $data['contact_name'],
            phone: $data['phone'],
            email: self::blankToNull($data['email'] ?? null),
            city: self::blankToNull($data['city'] ?? null),
            businessType: SignupBusinessType::from($data['business_type']),
            planCode: self::blankToNull($data['plan_code'] ?? null),
            preferredSubdomain: self::blankToNull($data['preferred_subdomain'] ?? null),
            notes: self::blankToNull($data['notes'] ?? null),
            termsAccepted: filter_var($data['terms_accepted'] ?? false, FILTER_VALIDATE_BOOL),
            termsVersion: trim((string) config('legal.version')),
            honeypotFilled: self::blankToNull($data['website'] ?? null) !== null,
            ip: $ip,
            userAgent: $userAgent === null ? null : mb_substr($userAgent, 0, 512),
        );
    }

    private static function blankToNull(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $value;
    }
}
