<?php

declare(strict_types=1);

namespace App\Actions\Central\SuperAdmins;

use App\Enums\CentralAuditEvent;
use App\Enums\CentralPermission;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * IDEN-1.6, `central:create-super-admin`: creates an active CentralUser (lowercase email,
 * password hashed by the model cast) holding the central-guard `super_admin` role, in one
 * central transaction, audited `central_user_created` (no password in the log).
 *
 * The new operator has no 2FA yet: the first sign-in only gets a setup token (IDEN-1.12).
 * Validation (unique email, 12+ chars password) is the command's job; this action still
 * refuses an existing email.
 */
final class CreateCentralSuperAdminAction
{
    public function __construct(
        private readonly CentralAuditLogger $auditLogger,
    ) {}

    public function execute(string $name, string $email, string $password): CentralUser
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            throw new RuntimeException('central:create-super-admin must run in the central context.');
        }

        $email = mb_strtolower(trim($email));
        $connection = (string) (new CentralUser)->getConnectionName();

        $user = DB::connection($connection)->transaction(function () use ($connection, $name, $email, $password): CentralUser {
            $role = Role::on($connection)
                ->where('name', CentralPermission::ROLE_SUPER_ADMIN)
                ->where('guard_name', CentralPermission::GUARD)
                ->first();

            if (! $role instanceof Role) {
                throw new RuntimeException((string) __('console.create_super_admin.central_role_missing'));
            }

            if (CentralUser::query()->where('email', $email)->lockForUpdate()->exists()) {
                throw new RuntimeException((string) __('console.create_super_admin.email_taken'));
            }

            $user = CentralUser::query()->create([
                'name' => trim($name),
                'email' => $email,
                'password' => $password,
                'is_active' => true,
            ]);
            $user->assignRole($role);

            $this->auditLogger->record(
                CentralAuditEvent::CentralUserCreated,
                ['email' => $email, 'role' => CentralPermission::ROLE_SUPER_ADMIN, 'source' => 'console'],
                subject: $user,
            );

            return $user;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}
