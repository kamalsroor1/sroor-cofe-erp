<?php

declare(strict_types=1);

namespace App\DTOs\Signup;

use App\Enums\SignupBusinessType;
use SensitiveParameter;

/**
 * SIGN-11: a validated public self-signup (POST /api/v1/signup). The password is plain here
 * only; StartSelfSignupAction stores its hash and never the value.
 */
final readonly class StartSelfSignupDTO
{
    public function __construct(
        public string $businessName,
        public string $ownerName,
        public string $email,
        public string $phone,
        #[SensitiveParameter] public string $password,
        public SignupBusinessType $businessType,
        public ?string $city,
        public ?string $subdomain,
        public ?string $planCode,
        public string $termsVersion,
        public ?string $locale,
        public bool $honeypotFilled,
        public ?string $ip,
        public ?string $userAgent,
    ) {}

    /**
     * @param  array{business_name: string, owner_name: string, email: string, phone: string, password: string, business_type: string, city?: string|null, subdomain?: string|null, plan_code?: string|null, locale?: string|null, website?: string|null}  $data
     */
    public static function fromArray(#[SensitiveParameter] array $data, ?string $ip = null, ?string $userAgent = null): self
    {
        return new self(
            businessName: $data['business_name'],
            ownerName: $data['owner_name'],
            email: strtolower(trim($data['email'])),
            phone: $data['phone'],
            password: $data['password'],
            businessType: SignupBusinessType::from($data['business_type']),
            city: self::blankToNull($data['city'] ?? null),
            subdomain: self::blankToNull($data['subdomain'] ?? null),
            planCode: self::blankToNull($data['plan_code'] ?? null),
            termsVersion: trim((string) config('legal.version')),
            locale: self::blankToNull($data['locale'] ?? null),
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
