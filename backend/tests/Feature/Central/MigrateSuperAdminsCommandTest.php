<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralAuditEvent;
use App\Enums\CentralPermission;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use App\Models\User;
use Database\Seeders\CentralPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * IDEN-1.6: `central:migrate-super-admins`.
 *
 * Dry run by default; writes only with --execute AND an explicit selection (--email
 * allowlist or an interactive yes per candidate); copies the legacy hash verbatim;
 * idempotent; never prints a hash; afterwards no migrated user holds `super_admin` on
 * any guard in the central `users` table; audited `super_admin_migrated`.
 */
final class MigrateSuperAdminsCommandTest extends TenantTestCase
{
    private const COMMAND = 'central:migrate-super-admins';

    // Fake legacy password; the `fixture` prefix marks it as fake for gitleaks (.gitleaks.toml).
    private const LEGACY_PASSWORD = 'fixtureLegacyOperatorPassword';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CentralPermissionsSeeder::class);
    }

    /** A Phase 0 operator: App\Models\User in the central `users` table with the web-guard role. */
    private function legacySuperAdmin(?string $email = null): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'مشرف قديم '.Str::lower(Str::random(4)),
            'email' => $email ?? 'legacy-'.Str::lower(Str::random(8)).'@central.test',
            'password' => Hash::make(self::LEGACY_PASSWORD),
            'is_active' => true,
        ])->save();
        $user->assignRole('super_admin');

        return $user;
    }

    private function legacyHash(User $user): string
    {
        return (string) DB::table('users')->where('id', $user->getKey())->value('password');
    }

    /** `super_admin` role assignments of a legacy user, on every guard. */
    private function legacyAssignments(User $user): int
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'super_admin')
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->where('model_has_roles.model_id', $user->getKey())
            ->count();
    }

    /** Simulates a stray assignment of the legacy user to the central-guard super_admin role. */
    private function addStrayCentralGuardAssignment(User $user): void
    {
        $role = Role::query()->where('name', 'super_admin')->where('guard_name', CentralPermission::GUARD)->firstOrFail();

        DB::table('model_has_roles')->insert([
            'role_id' => $role->getKey(),
            'model_type' => $user->getMorphClass(),
            'model_id' => $user->getKey(),
        ]);
    }

    public function test_dry_run_is_the_default_lists_candidates_and_writes_nothing(): void
    {
        $legacy = $this->legacySuperAdmin();

        $this->artisan(self::COMMAND)
            ->expectsOutputToContain(__('console.migrate_super_admins.mode_dry_run'))
            ->expectsOutputToContain($legacy->email)
            ->expectsOutputToContain(__('console.migrate_super_admins.selection_hint'))
            ->doesntExpectOutputToContain($this->legacyHash($legacy))
            ->assertSuccessful();

        $this->artisan(self::COMMAND, ['--email' => [$legacy->email]])
            ->expectsOutputToContain(__('console.migrate_super_admins.would_migrate', ['id' => $legacy->getKey(), 'email' => $legacy->email]))
            ->expectsOutputToContain(__('console.migrate_super_admins.dry_run_done'))
            ->assertSuccessful();

        $this->assertSame(0, CentralUser::query()->count());
        $this->assertSame(1, $this->legacyAssignments($legacy));
        $this->assertSame(0, CentralAuditLog::query()->count());
    }

    public function test_execute_with_an_email_allowlist_migrates_exactly_those_operators(): void
    {
        $chosen = $this->legacySuperAdmin('Chosen.Operator@Central.test');
        $this->addStrayCentralGuardAssignment($chosen);
        $notChosen = $this->legacySuperAdmin();
        $hash = $this->legacyHash($chosen);

        $this->artisan(self::COMMAND, ['--email' => ['  CHOSEN.operator@central.TEST '], '--execute' => true])
            ->expectsOutputToContain(__('console.migrate_super_admins.done', ['count' => 1]))
            ->expectsOutputToContain(__('console.migrate_super_admins.assertion_passed'))
            ->doesntExpectOutputToContain($hash)
            ->assertSuccessful();

        $central = CentralUser::query()->sole();
        $this->assertSame('chosen.operator@central.test', $central->email);
        $this->assertSame($hash, $central->getRawOriginal('password'), 'the legacy hash is copied byte for byte');
        $this->assertTrue($central->is_active);
        $this->assertTrue($central->hasRole(CentralPermission::ROLE_SUPER_ADMIN, CentralPermission::GUARD));
        $this->assertNull($central->two_factor_confirmed_at);

        // No super_admin role is left on ANY guard for the migrated legacy user.
        $this->assertSame(0, $this->legacyAssignments($chosen));
        // The operator that was not selected is untouched.
        $this->assertSame(1, $this->legacyAssignments($notChosen));

        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::SuperAdminMigrated->value)->sole();
        $this->assertSame($chosen->getKey(), $log->properties['legacy_user_id'] ?? null);
        $this->assertSame($central->getKey(), $log->properties['central_user_id'] ?? null);
        $this->assertSame('created', $log->properties['status'] ?? null);
        $this->assertStringNotContainsString($hash, (string) json_encode($log->properties));

        // Same password as before, through the central login (setup token: 2FA not set up yet).
        $this->postJson('/api/v1/super-admin/auth/login', ['email' => $central->email, 'password' => self::LEGACY_PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.two_factor_setup_required', true);
    }

    public function test_running_twice_is_idempotent(): void
    {
        $legacy = $this->legacySuperAdmin();

        $this->artisan(self::COMMAND, ['--email' => [$legacy->email], '--execute' => true])->assertSuccessful();
        $this->artisan(self::COMMAND, ['--email' => [$legacy->email], '--execute' => true])
            ->expectsOutputToContain(__('console.migrate_super_admins.no_candidates'))
            ->assertSuccessful();

        $this->assertSame(1, CentralUser::query()->count());
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::SuperAdminMigrated->value)->count());
    }

    public function test_an_existing_central_operator_is_kept_and_only_gets_the_role(): void
    {
        $legacy = $this->legacySuperAdmin();
        $existing = CentralUser::factory()->create(['email' => $legacy->email, 'password' => Hash::make('fixtureExistingCentralPassword')]);
        $existingHash = $existing->getRawOriginal('password');

        $this->artisan(self::COMMAND, ['--email' => [$legacy->email], '--execute' => true])->assertSuccessful();

        $existing->refresh();
        $this->assertSame(1, CentralUser::query()->count());
        $this->assertSame($existingHash, $existing->getRawOriginal('password'));
        $this->assertTrue($existing->hasRole(CentralPermission::ROLE_SUPER_ADMIN, CentralPermission::GUARD));
        $this->assertSame(0, $this->legacyAssignments($legacy));

        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::SuperAdminMigrated->value)->sole();
        $this->assertSame('existing', $log->properties['status'] ?? null);
    }

    public function test_execute_without_a_selection_refuses_when_not_interactive(): void
    {
        $legacy = $this->legacySuperAdmin();

        $this->artisan(self::COMMAND, ['--execute' => true, '--no-interaction' => true])
            ->expectsOutputToContain(__('console.migrate_super_admins.selection_required'))
            ->assertFailed();

        $this->assertSame(0, CentralUser::query()->count());
        $this->assertSame(1, $this->legacyAssignments($legacy));
    }

    public function test_interactive_execute_asks_per_candidate_and_migrates_only_confirmed_ones(): void
    {
        $yes = $this->legacySuperAdmin();
        $no = $this->legacySuperAdmin();

        $this->artisan(self::COMMAND, ['--execute' => true])
            ->expectsConfirmation(__('console.migrate_super_admins.confirm_candidate', ['id' => $yes->getKey(), 'email' => $yes->email]), 'yes')
            ->expectsConfirmation(__('console.migrate_super_admins.confirm_candidate', ['id' => $no->getKey(), 'email' => $no->email]), 'no')
            ->assertSuccessful();

        $this->assertSame([$yes->email], CentralUser::query()->pluck('email')->all());
        $this->assertSame(0, $this->legacyAssignments($yes));
        $this->assertSame(1, $this->legacyAssignments($no));
    }

    public function test_an_email_that_is_not_a_candidate_promotes_nobody(): void
    {
        $legacy = $this->legacySuperAdmin();
        $plain = new User;
        $plain->forceFill(['name' => 'plain', 'email' => 'plain@central.test', 'password' => Hash::make('fixturePlainUserPassword')])->save();

        $this->artisan(self::COMMAND, ['--email' => ['plain@central.test'], '--execute' => true])
            ->expectsOutputToContain(__('console.migrate_super_admins.not_a_candidate', ['email' => 'plain@central.test']))
            ->expectsOutputToContain(__('console.migrate_super_admins.nothing_selected'))
            ->assertSuccessful();

        $this->assertSame(0, CentralUser::query()->count());
        $this->assertSame(1, $this->legacyAssignments($legacy));
    }

    public function test_it_fails_without_writing_when_the_central_role_is_missing(): void
    {
        $legacy = $this->legacySuperAdmin();
        Role::query()->where('name', 'super_admin')->where('guard_name', CentralPermission::GUARD)->delete();

        $this->artisan(self::COMMAND, ['--email' => [$legacy->email], '--execute' => true])
            ->expectsOutputToContain(__('console.migrate_super_admins.central_role_missing'))
            ->assertFailed();

        $this->assertSame(0, CentralUser::query()->count());
        $this->assertSame(1, $this->legacyAssignments($legacy));
    }

    public function test_no_candidates_is_a_clean_success(): void
    {
        $this->artisan(self::COMMAND)
            ->expectsOutputToContain(__('console.migrate_super_admins.no_candidates'))
            ->assertSuccessful();
    }
}
