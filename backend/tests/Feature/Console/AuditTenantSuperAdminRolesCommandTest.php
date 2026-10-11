<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\CentralPermission;
use App\Models\CentralUser;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * F3a: tenants:audit-super-admin must only count model_has_roles rows whose model_type
 * is the User model. A super_admin role row attached to another morph (e.g. a Store)
 * that happens to share a user's id must neither be counted as a role holder nor pull
 * that user's phone into the "central super admin phones" match list.
 *
 * IDEN-1.8: runs on the real topology (Tests\TenantTestCase): the legacy central `users`
 * rows and their roles live in the CENTRAL database, the audited tenant has its OWN database.
 * Before, both sides shared one sqlite :memory: database, so one inserted row fed both halves
 * of the audit at once; each half is now seeded where it really lives.
 */
final class AuditTenantSuperAdminRolesCommandTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
    }

    /** Legacy web-guard `super_admin` role in the CENTRAL database. */
    private function centralLegacyRole(): Role
    {
        $this->endTenancy();

        return $this->webRole('super_admin');
    }

    /** A legacy row of the CENTRAL `users` table. */
    private function centralLegacyUser(string $phone, string $email): User
    {
        $this->endTenancy();

        $user = new User;
        $user->forceFill([
            'name' => 'مستخدم مركزي',
            'phone' => $phone,
            'email' => $email,
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ])->save();

        return $user;
    }

    /** A web-guard `super_admin` role inside the TENANT database (pre P0-AUTH-4 leftovers). */
    private function tenantLegacyRoleId(): int
    {
        return $this->inTenant($this->tenant, fn (): int => (int) $this->webRole('super_admin')->id);
    }

    private function tenantUser(string $phone, string $email, ?string $role = null): User
    {
        return $this->createTenantUser($this->tenant, $role, [], [
            'name' => 'مستخدم',
            'phone' => $phone,
            'email' => $email,
        ]);
    }

    /** Insert a model_has_roles row on a NON-User morph, in the current context's database. */
    private function foreignMorphRoleRow(int $roleId, int $modelId): void
    {
        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => Store::class,
            'model_id' => $modelId,
        ]);
    }

    /**
     * @return array{tenant: string, role_exists: string, role_users: string, permissions: string, permission_roles: string, phone_matches: string}
     */
    private function auditRow(): array
    {
        $this->endTenancy();
        $tenantId = (string) $this->tenant->getTenantKey();

        $exit = Artisan::call('tenants:audit-super-admin', ['--tenant' => $tenantId]);
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $this->assertFalse(tenancy()->initialized, 'The audit must leave tenant context.');

        foreach (preg_split('/\R/u', $output) ?: [] as $line) {
            $cells = array_map('trim', explode('|', trim($line)));
            // A table row renders as "| c1 | c2 | ... |" => first/last cells empty.
            if (count($cells) === 8 && $cells[1] === $tenantId) {
                return [
                    'tenant' => $cells[1],
                    'role_exists' => $cells[2],
                    'role_users' => $cells[3],
                    'permissions' => $cells[4],
                    'permission_roles' => $cells[5],
                    'phone_matches' => $cells[6],
                ];
            }
        }

        $this->fail('Audit table row for the test tenant was not found in the command output.');
    }

    /** @return list<string> */
    private function matchedIds(string $cell): array
    {
        return $cell === '-' ? [] : array_map('trim', explode(',', $cell));
    }

    /** @return array{central: list<array<string, mixed>>, tenant: list<array<string, mixed>>} */
    private function roleRowsSnapshot(): array
    {
        $read = static fn (): array => DB::table('model_has_roles')
            ->orderBy('model_type')->orderBy('model_id')->orderBy('role_id')
            ->get()->map(fn ($r): array => (array) $r)->all();

        $this->endTenancy();
        $central = $read();

        return ['central' => $central, 'tenant' => $this->inTenant($this->tenant, $read)];
    }

    public function test_role_row_on_a_non_user_morph_is_not_counted_as_a_holder(): void
    {
        $roleId = $this->tenantLegacyRoleId();
        $bystander = $this->tenantUser('01222222201', 'bystander@tenant.test');

        // A super_admin row attached to a Store whose id equals the bystander's id.
        $this->inTenant($this->tenant, fn () => $this->foreignMorphRoleRow($roleId, (int) $bystander->id));

        $row = $this->auditRow();

        $this->assertSame('0', $row['role_users'], 'A non-User morph row must not be counted as a super_admin holder.');
    }

    public function test_role_row_on_a_non_user_morph_does_not_pull_a_user_into_phone_matches(): void
    {
        $role = $this->centralLegacyRole();
        $centralBystander = $this->centralLegacyUser('01222222202', 'bystander4@central.test');
        $this->foreignMorphRoleRow((int) $role->id, (int) $centralBystander->id);

        // The tenant holds a user with the bystander's phone: only a real holder would match it.
        $tenantTwin = $this->tenantUser('01222222202', 'bystander4@tenant.test');

        $row = $this->auditRow();

        $this->assertNotContains('#'.$tenantTwin->id, $this->matchedIds($row['phone_matches']), 'A non-User morph row pulled an unrelated user into the phone matches.');
        $this->assertSame('-', $row['phone_matches']);
    }

    public function test_real_user_holding_super_admin_is_still_counted_and_matched(): void
    {
        $this->centralLegacyRole();
        $this->centralLegacyUser('01222222203', 'holder@central.test')->assignRole('super_admin');

        $this->tenantLegacyRoleId();
        $holder = $this->tenantUser('01222222203', 'holder@tenant.test', 'super_admin');
        $other = $this->tenantUser('01222222204', 'other@tenant.test');

        $row = $this->auditRow();

        $this->assertSame('1', $row['role_users']);
        $matches = $this->matchedIds($row['phone_matches']);
        $this->assertContains('#'.$holder->id, $matches);
        $this->assertNotContains('#'.$other->id, $matches);
    }

    public function test_mixed_rows_count_only_the_user_morph(): void
    {
        $centralRole = $this->centralLegacyRole();
        $this->centralLegacyUser('01222222205', 'holder2@central.test')->assignRole('super_admin');
        $centralBystander = $this->centralLegacyUser('01222222206', 'bystander2@central.test');
        $this->foreignMorphRoleRow((int) $centralRole->id, (int) $centralBystander->id);

        $tenantRoleId = $this->tenantLegacyRoleId();
        $holder = $this->tenantUser('01222222205', 'holder2@tenant.test', 'super_admin');
        $bystander = $this->tenantUser('01222222206', 'bystander2@tenant.test');
        $this->inTenant($this->tenant, fn () => $this->foreignMorphRoleRow($tenantRoleId, (int) $bystander->id));

        $row = $this->auditRow();

        $this->assertSame('1', $row['role_users']);
        $this->assertSame(['#'.$holder->id], $this->matchedIds($row['phone_matches']));
    }

    public function test_audit_remains_read_only_with_foreign_morph_rows(): void
    {
        $centralRole = $this->centralLegacyRole();
        $centralBystander = $this->centralLegacyUser('01222222207', 'bystander3@central.test');
        $this->foreignMorphRoleRow((int) $centralRole->id, (int) $centralBystander->id);

        $tenantRoleId = $this->tenantLegacyRoleId();
        $bystander = $this->tenantUser('01222222207', 'bystander3@tenant.test');
        $this->inTenant($this->tenant, fn () => $this->foreignMorphRoleRow($tenantRoleId, (int) $bystander->id));

        $before = $this->roleRowsSnapshot();

        $this->auditRow();

        $this->assertSame($before, $this->roleRowsSnapshot());
    }

    public function test_central_operator_sharing_an_id_with_a_legacy_user_does_not_pull_its_phone(): void
    {
        // IDEN-1.1: CentralUser is standalone (`central_users`); its role rows carry the
        // CentralUser morph and must never be joined onto `users.id`.
        $legacy = $this->centralLegacyUser('01222222208', 'legacy-no-role@central.test');
        $this->seedCentralPlatformRoles();

        $operator = new CentralUser;
        $operator->forceFill([
            'id' => $legacy->id,
            'name' => 'مشغل',
            'email' => 'operator-'.Str::lower(Str::random(6)).'@central.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ])->save();
        $operator->assignRole($this->centralRole(CentralPermission::ROLE_SUPER_ADMIN));
        $this->assertSame((int) $legacy->id, (int) $operator->id, 'fixture: same id in both tables');

        $tenantTwin = $this->tenantUser('01222222208', 'twin@tenant.test');

        $row = $this->auditRow();

        $this->assertNotContains('#'.$tenantTwin->id, $this->matchedIds($row['phone_matches']));
        $this->assertSame('-', $row['phone_matches']);
    }
}
