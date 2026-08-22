<?php

namespace App\DTOs;

class CreateTenantDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly int $planId,
        public readonly string $password,
        public readonly ?string $customDomain = null,
        public readonly int $trialDays = 14,
        public readonly ?string $tenancyDbName = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            slug: $data['slug'],
            email: $data['email'],
            phone: $data['phone'] ?? null,
            planId: (int)$data['plan_id'],
            password: $data['password'],
            customDomain: $data['custom_domain'] ?? null,
            trialDays: isset($data['trial_days']) ? (int)$data['trial_days'] : 14,
            tenancyDbName: !empty($data['tenancy_db_name']) ? trim((string)$data['tenancy_db_name']) : null,
        );
    }
}
