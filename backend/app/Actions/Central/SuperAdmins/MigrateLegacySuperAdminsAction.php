<?php

declare(strict_types=1);

namespace App\Actions\Central\SuperAdmins;

use App\Actions\Central\Data\SuperAdminMigrationResult;
use App\Enums\CentralAuditEvent;
use App\Enums\CentralPermission;
use App\Models\CentralUser;
use App\Models\User;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * IDEN-1.6: moves explicitly selected Phase 0 operators (App\Models\User in the central
 * `users` table with a `super_admin` role) to App\Models\CentralUser.
 *
 * Per operator, in ONE central transaction (legacy row locked):
 *  - CentralUser found by lowercase email, or created with the legacy password hash copied
 *    VERBATIM (raw attributes: the `hashed` cast never re-hashes or re-checks it), so the
 *    operator keeps the same password; is_active is carried over;
 *  - the central-guard `super_admin` role is ensured (CentralPermissionsSeeder must have run);
 *  - every `super_admin` role assignment of the legacy user is removed, on EVERY guard;
 *  - `super_admin_migrated` is audited (ids and email only).
 *
 * Idempotent: an existing CentralUser is never overwritten, the role is only added when
 * missing, and a migrated legacy user is no longer a candidate. After all operators the
 * action re-checks that none of them still holds `super_admin` on any guard and throws
 * otherwise. Nothing here prints or returns a hash or a token.
 */
final class MigrateLegacySuperAdminsAction
{
    public function __construct(
        private readonly LegacySuperAdminDirectory $directory,
        private readonly CentralAuditLogger $auditLogger,
    ) {}

    /**
     * @param  iterable<User>  $legacyUsers  the candidates the operator explicitly selected
     * @return list<SuperAdminMigrationResult>
     */
    public function execute(iterable $legacyUsers): array
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            throw new RuntimeException('central:migrate-super-admins must run in the central context.');
        }

        $role = Role::on($this->directory->connection())
            ->where('name', CentralPermission::ROLE_SUPER_ADMIN)
            ->where('guard_name', CentralPermission::GUARD)
            ->first();

        if (! $role instanceof Role) {
            throw new RuntimeException((string) __('console.migrate_super_admins.central_role_missing'));
        }

        // One outer central transaction: a failed final assertion rolls back every operator
        // of this run (and their audit rows), not only the last one.
        $results = DB::connection($this->directory->connection())->transaction(function () use ($legacyUsers, $role): array {
            $results = [];
            foreach ($legacyUsers as $legacyUser) {
                $results[] = $this->migrateOne((int) $legacyUser->getKey(), $role);
            }

            $migratedIds = array_values(array_map(
                static fn (SuperAdminMigrationResult $result): int => $result->legacyUserId,
                array_filter($results, static fn (SuperAdminMigrationResult $result): bool => $result->migrated()),
            ));

            if ($this->directory->remainingAssignments($migratedIds) !== 0) {
                throw new RuntimeException((string) __('console.migrate_super_admins.assertion_failed'));
            }

            return $results;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $results;
    }

    private function migrateOne(int $legacyUserId, Role $role): SuperAdminMigrationResult
    {
        $connection = $this->directory->connection();

        return DB::connection($connection)->transaction(function () use ($connection, $legacyUserId, $role): SuperAdminMigrationResult {
            /** @var User $legacy */
            $legacy = User::on($connection)->whereKey($legacyUserId)->lockForUpdate()->firstOrFail();

            $email = mb_strtolower(trim((string) $legacy->getAttribute('email')));

            if ($email === '') {
                return new SuperAdminMigrationResult($legacyUserId, '', SuperAdminMigrationResult::SKIPPED_NO_EMAIL);
            }

            $central = CentralUser::query()->where('email', $email)->lockForUpdate()->first();
            $status = SuperAdminMigrationResult::EXISTING;

            if (! $central instanceof CentralUser) {
                $central = $this->createFromLegacy($legacy, $email);
                $status = SuperAdminMigrationResult::CREATED;
            }

            if (! $central->hasRole($role)) {
                $central->assignRole($role);
            }

            $revoked = $this->directory->revoke($legacyUserId);
            $legacy->unsetRelation('roles');

            $this->auditLogger->record(
                CentralAuditEvent::SuperAdminMigrated,
                [
                    'legacy_user_id' => $legacyUserId,
                    'central_user_id' => (int) $central->getKey(),
                    'email' => $email,
                    'status' => $status,
                    'revoked_legacy_roles' => $revoked,
                ],
                subject: $central,
            );

            return new SuperAdminMigrationResult($legacyUserId, $email, $status, (int) $central->getKey(), $revoked);
        });
    }

    private function createFromLegacy(User $legacy, string $email): CentralUser
    {
        $isActive = $legacy->getAttribute('is_active');
        $name = trim((string) $legacy->getAttribute('name'));

        $central = new CentralUser;
        // Raw attributes on purpose: the legacy bcrypt/argon hash is copied byte for byte.
        $central->setRawAttributes([
            'name' => $name !== '' ? $name : $email,
            'email' => $email,
            'password' => (string) $legacy->getRawOriginal('password'),
            'is_active' => $isActive === null ? true : (bool) $isActive,
        ]);
        $central->save();

        return $central;
    }
}
