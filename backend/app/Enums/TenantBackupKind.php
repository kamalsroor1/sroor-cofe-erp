<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a backup archive was taken (central `tenant_backups.kind`, OPS-9).
 *
 *  - scheduled: the nightly `backup:tenants` run (and the pre-migration deploy backup);
 *    pruned by the 7 daily / 4 weekly / 3 monthly retention;
 *  - final:     taken by the archive flow right before a tenant becomes `archived`;
 *  - pre_purge: taken by `tenants:purge --take-backup` right before the database is dropped;
 *  - manual:    an operator's ad-hoc `backup:tenants --kind=manual`; pruned like scheduled.
 *
 * `final` and `pre_purge` archives are the last copy of a tenant's data: retention never
 * deletes them (CTO Q6), and their file names carry the kind so even a run without the
 * ledger cannot match them for deletion.
 *
 * Never rename or remove a value: it is stored in the ledger.
 */
enum TenantBackupKind: string
{
    case Scheduled = 'scheduled';
    case Final = 'final';
    case PrePurge = 'pre_purge';
    case Manual = 'manual';

    /** Never pruned by retention. */
    public function isProtected(): bool
    {
        return $this === self::Final || $this === self::PrePurge;
    }

    /**
     * Kinds accepted by the archive guard (TransitionTenantStatusAction) as the tenant's
     * final backup.
     *
     * @return list<self>
     */
    public static function finalKinds(): array
    {
        return [self::Final, self::PrePurge];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * @return list<string>
     */
    public static function protectedValues(): array
    {
        return array_values(array_map(
            static fn (self $case): string => $case->value,
            array_filter(self::cases(), static fn (self $case): bool => $case->isProtected()),
        ));
    }
}
