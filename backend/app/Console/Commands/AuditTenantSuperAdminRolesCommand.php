<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * STRICTLY READ-ONLY. Reports, per tenant DB, any leaked platform identity:
 * a super_admin role (and its holders), super_admin.* permissions (and the roles
 * holding them), and tenant users whose phone matches a central super_admin phone.
 *
 * It performs no writes and intentionally offers no --fix option: remediation of a
 * live tenant is a reviewed, backed-up manual operation.
 */
final class AuditTenantSuperAdminRolesCommand extends Command
{
    protected $signature = 'tenants:audit-super-admin {--tenant= : Audit a single tenant id}';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('console.audit_super_admin.description'));
    }

    public function handle(): int
    {
        $centralPhones = $this->centralSuperAdminPhones();

        $query = Tenant::query();
        $tenantId = $this->option('tenant');

        if ($tenantId === null || $tenantId === '') {
            // OPS-2: a pending / running / failed tenant has no database to audit.
            $query->provisioned();
        } else {
            $query->whereKey($tenantId);

            if (! (clone $query)->exists()) {
                $this->error((string) __('console.audit_super_admin.tenant_not_found', ['tenant' => $tenantId]));

                return self::FAILURE;
            }
        }

        $rows = [];
        $total = 0;
        $withFindings = 0;

        foreach ($query->cursor() as $tenant) {
            $total++;

            try {
                $report = $tenant->run(fn (): array => $this->auditCurrentTenant($centralPhones));
            } catch (Throwable $e) {
                $this->warn((string) __('console.audit_super_admin.tenant_failed', [
                    'tenant' => $tenant->getTenantKey(),
                    'error' => $e->getMessage(),
                ]));

                continue;
            }

            if ($report['has_findings']) {
                $withFindings++;
            }

            $rows[] = [
                $tenant->getTenantKey(),
                $report['role_exists'] ? __('common.yes') : __('common.no'),
                $report['role_users'],
                $report['permissions'] === [] ? '-' : implode(', ', $report['permissions']),
                $report['permission_roles'] === [] ? '-' : implode(', ', $report['permission_roles']),
                $report['phone_matches'] === [] ? '-' : implode(', ', $report['phone_matches']),
            ];
        }

        if ($total === 0) {
            $this->info((string) __('console.audit_super_admin.no_tenants'));

            return self::SUCCESS;
        }

        $this->table([
            __('console.audit_super_admin.col_tenant'),
            __('console.audit_super_admin.col_role_exists'),
            __('console.audit_super_admin.col_role_users'),
            __('console.audit_super_admin.col_permissions'),
            __('console.audit_super_admin.col_permission_roles'),
            __('console.audit_super_admin.col_phone_matches'),
        ], $rows);

        $this->info((string) __('console.audit_super_admin.findings', [
            'count' => $withFindings,
            'total' => $total,
        ]));

        return self::SUCCESS;
    }

    /**
     * Phones of users holding super_admin in the CENTRAL database. Read before any
     * tenant is initialized, through the pinned central connection.
     *
     * @return list<string>
     */
    private function centralSuperAdminPhones(): array
    {
        $connection = (string) config('tenancy.database.central_connection', config('database.default'));

        return DB::connection($connection)
            ->table('users')
            ->join('model_has_roles', function (JoinClause $join): void {
                // Only role rows attached to the User morph: a role on any other model
                // sharing a user's id must not pull that user's phone into the list.
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->whereIn('model_has_roles.model_type', $this->userMorphTypes());
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'super_admin')
            ->whereNotNull('users.phone')
            ->distinct()
            ->pluck('users.phone')
            ->map(fn ($phone): string => (string) $phone)
            ->values()
            ->all();
    }

    /**
     * Morph types a legacy central `users` row's role can carry. CentralUser (IDEN-1.1)
     * is standalone and lives in `central_users`, so its morph type must NOT be joined
     * onto `users.id`: an operator sharing an id with a legacy user would otherwise pull
     * that user's phone into the list.
     *
     * @return list<string>
     */
    private function userMorphTypes(): array
    {
        return [(new User)->getMorphClass()];
    }

    /**
     * Runs inside $tenant->run(); the default connection is the tenant DB.
     *
     * @param  list<string>  $centralPhones
     * @return array{role_exists: bool, role_users: int, permissions: list<string>, permission_roles: list<string>, phone_matches: list<string>, has_findings: bool}
     */
    private function auditCurrentTenant(array $centralPhones): array
    {
        $roleIds = DB::table('roles')->where('name', 'super_admin')->pluck('id');

        $roleUsers = $roleIds->isEmpty()
            ? 0
            : DB::table('model_has_roles')
                ->whereIn('role_id', $roleIds)
                ->where('model_type', (new User)->getMorphClass())
                ->count();

        $permissions = DB::table('permissions')
            ->where('name', 'like', 'super_admin.%')
            ->pluck('name', 'id');

        $permissionRoles = $permissions->isEmpty()
            ? []
            : DB::table('role_has_permissions')
                ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
                ->whereIn('role_has_permissions.permission_id', $permissions->keys())
                ->distinct()
                ->orderBy('roles.name')
                ->pluck('roles.name')
                ->all();

        // Report matching tenant user ids only; phones are not echoed to the console.
        $phoneMatches = $centralPhones === []
            ? []
            : DB::table('users')
                ->whereIn('phone', $centralPhones)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): string => '#'.$id)
                ->all();

        $permissionNames = $permissions->values()->all();

        return [
            'role_exists' => $roleIds->isNotEmpty(),
            'role_users' => $roleUsers,
            'permissions' => $permissionNames,
            'permission_roles' => $permissionRoles,
            'phone_matches' => $phoneMatches,
            'has_findings' => $roleIds->isNotEmpty() || $permissionNames !== [] || $phoneMatches !== [],
        ];
    }
}
