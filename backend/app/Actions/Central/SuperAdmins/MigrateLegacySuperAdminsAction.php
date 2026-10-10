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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * IDEN-1.6: moves explicitly selected Phase 0 operators (App\Models\User in the central
 * `users` table with a `super_admin` role) to App\Models\CentralUser.
 *
 * Per operator, in ONE central transaction (legacy row locked):
 *  - CentralUser found by lowercase email, or created with the legacy password hash copied
 *    VERBATIM (raw attributes: the `hashed` cast never re-hashes or re-checks it); a created
 *    account is flagged `must_reset_password` (login refused until the reset flow, W2-B3);
 *    is_active is carried over;
 *  - the central-guard `super_admin` role is ensured (CentralPermissionsSeeder must have run);
 *  - every `super_admin` role assignment of the legacy user is removed, on EVERY guard;
 *  - the legacy row is retired (security audit, W2 lane 3I): ALL its roles and direct
 *    permissions (any guard) removed, password scrambled, deactivated, API tokens revoked;
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
            $this->retireLegacy($connection, $legacyUserId);
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

    /**
     * Security audit (W2 lane 3I): once moved, the legacy central `users` row is retired for
     * good: every role / direct permission on every guard is removed, its password is replaced
     * by an unknown random hash, it is deactivated and its API tokens are revoked. Runs after
     * createFromLegacy() copied the original hash. Query builder on purpose: no model events.
     */
    private function retireLegacy(string $connection, int $legacyUserId): void
    {
        $this->directory->revokeEverything($legacyUserId);

        $values = ['password' => Hash::make(Str::random(64))];
        $schema = Schema::connection($connection);
        if ($schema->hasColumn('users', 'is_active')) {
            $values['is_active'] = false;
        }
        if ($schema->hasColumn('users', 'api_token')) {
            $values['api_token'] = null;
        }
        if ($schema->hasColumn('users', 'remember_token')) {
            $values['remember_token'] = null;
        }

        DB::connection($connection)->table('users')->where('id', $legacyUserId)->update($values);

        if ($schema->hasTable('personal_access_tokens')) {
            DB::connection($connection)->table('personal_access_tokens')
                ->where('tokenable_type', (new User)->getMorphClass())
                ->where('tokenable_id', $legacyUserId)
                ->delete();
        }
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
            // W2-B3 security: the copied legacy password must be replaced before the first sign-in.
            'must_reset_password' => true,
        ]);
        $central->save();

        return $central;
    }
}
