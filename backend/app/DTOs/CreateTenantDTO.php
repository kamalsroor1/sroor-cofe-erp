<?php

namespace App\DTOs;

use App\Enums\TenantSignupSource;

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
        public readonly int $trialDays = 0,
        public readonly ?string $tenancyDbName = null,
        public readonly ?string $tenancyDbUsername = null,
        public readonly ?string $tenancyDbPassword = null,
        // PLAN-10: how the tenant was created (console by default; the signup flow passes self_signup).
        public readonly TenantSignupSource $signupSource = TenantSignupSource::Console,
        // SIGN-12: `password` is already a hash (a self-signup owner's chosen password, hashed at
        // signup); the provisioner stores it as is, never rehashes it.
        public readonly bool $passwordIsHashed = false,
        // SIGN-12: false when the owner chose the password (self-signup); true when the platform did.
        public readonly bool $ownerMustChangePassword = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            slug: $data['slug'],
            email: $data['email'],
            phone: $data['phone'] ?? null,
            planId: (int) $data['plan_id'],
            password: $data['password'],
            customDomain: $data['custom_domain'] ?? null,
            trialDays: isset($data['trial_days']) ? (int) $data['trial_days'] : 0,
            tenancyDbName: ! empty($data['tenancy_db_name']) ? trim((string) $data['tenancy_db_name']) : null,
            tenancyDbUsername: ! empty($data['tenancy_db_username']) ? trim((string) $data['tenancy_db_username']) : null,
            tenancyDbPassword: ! empty($data['tenancy_db_password']) ? trim((string) $data['tenancy_db_password']) : null,
            signupSource: ($data['signup_source'] ?? null) instanceof TenantSignupSource
                ? $data['signup_source']
                : (TenantSignupSource::tryFrom((string) ($data['signup_source'] ?? '')) ?? TenantSignupSource::Console),
            // Security review: `password` from an array is ALWAYS a plain password (hashed by the
            // provisioner); a pre-hashed one only enters through forSelfSignup().
            passwordIsHashed: false,
            ownerMustChangePassword: ($data['owner_must_change_password'] ?? true) !== false,
        );
    }

    /**
     * SIGN-12: the tenant of a verified self-signup. `$passwordHash` is the owner's chosen password,
     * hashed at signup (the provisioner verifies it is a real hash and never rehashes it); the owner
     * is not asked to change it.
     */
    public static function forSelfSignup(
        string $name,
        string $slug,
        string $email,
        ?string $phone,
        int $planId,
        #[\SensitiveParameter] string $passwordHash,
        int $trialDays,
    ): self {
        return new self(
            name: $name,
            slug: $slug,
            email: $email,
            phone: $phone,
            planId: $planId,
            password: $passwordHash,
            trialDays: $trialDays,
            signupSource: TenantSignupSource::SelfSignup,
            passwordIsHashed: true,
            ownerMustChangePassword: false,
        );
    }
}
