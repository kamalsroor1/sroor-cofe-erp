<?php

declare(strict_types=1);

namespace App\DTOs\AccountDeletion;

final readonly class CreateAccountDeletionRequestDTO
{
    public function __construct(
        public string $contactName,
        public string $phone,
        public ?string $email,
        public ?string $shopCode,
        public ?string $reason,
        public string $locale,
        public bool $honeypotFilled,
        public ?string $ip,
        public ?string $userAgent,
    ) {}

    /**
     * @param  array{contact_name: string, phone: string, email?: string|null, shop_code?: string|null, reason?: string|null, website?: string|null}  $data
     */
    public static function fromArray(array $data, string $locale, ?string $ip = null, ?string $userAgent = null): self
    {
        return new self(
            contactName: $data['contact_name'],
            phone: $data['phone'],
            email: self::blankToNull($data['email'] ?? null),
            shopCode: self::blankToNull($data['shop_code'] ?? null),
            reason: self::blankToNull($data['reason'] ?? null),
            locale: $locale === 'en' ? 'en' : 'ar',
            honeypotFilled: self::blankToNull($data['website'] ?? null) !== null,
            ip: $ip,
            userAgent: $userAgent === null ? null : mb_substr($userAgent, 0, 512),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'contact_name' => $this->contactName,
            'phone' => $this->phone,
            'email' => $this->email,
            'shop_code' => $this->shopCode,
            'reason' => $this->reason,
            'locale' => $this->locale,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
        ];
    }

    private static function blankToNull(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $value;
    }
}
