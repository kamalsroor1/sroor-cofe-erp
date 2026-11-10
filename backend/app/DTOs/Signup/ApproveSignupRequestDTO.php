<?php

declare(strict_types=1);

namespace App\DTOs\Signup;

use SensitiveParameter;

final readonly class ApproveSignupRequestDTO
{
    public function __construct(
        public int $signupRequestId,
        public string $subdomain,
        public ?string $planCode,
        public string $ownerEmail,
        #[SensitiveParameter] public string $ownerPassword,
    ) {}

    /**
     * @param  array{subdomain: string, plan_code?: string|null, owner_email: string, owner_password: string}  $data
     */
    public static function fromArray(int $signupRequestId, array $data): self
    {
        $planCode = $data['plan_code'] ?? null;

        return new self(
            signupRequestId: $signupRequestId,
            subdomain: strtolower(trim($data['subdomain'])),
            planCode: is_string($planCode) && trim($planCode) !== '' ? trim($planCode) : null,
            ownerEmail: trim($data['owner_email']),
            ownerPassword: $data['owner_password'],
        );
    }
}
