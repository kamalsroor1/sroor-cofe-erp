<?php

declare(strict_types=1);

namespace App\Actions\Central\Data;

/**
 * Outcome for one legacy operator of central:migrate-super-admins (IDEN-1.6).
 * Carries ids and the email only: never a password hash or a token.
 */
final class SuperAdminMigrationResult
{
    /** A new CentralUser was created with the legacy password hash. */
    public const CREATED = 'created';

    /** A CentralUser with that email already existed (kept as is, role ensured). */
    public const EXISTING = 'existing';

    /** The legacy row has no email: an operator signs in by email, nothing done. */
    public const SKIPPED_NO_EMAIL = 'skipped_no_email';

    public function __construct(
        public readonly int $legacyUserId,
        public readonly string $email,
        public readonly string $status,
        public readonly ?int $centralUserId = null,
        public readonly int $revokedLegacyRoles = 0,
    ) {}

    public function migrated(): bool
    {
        return $this->status !== self::SKIPPED_NO_EMAIL;
    }
}
