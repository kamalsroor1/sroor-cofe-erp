<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Actions\Central\Data\CentralAuthSettings;
use App\Actions\Central\SuperAdmins\MigrateLegacySuperAdminsAction;
use App\Enums\CentralAuditEvent;
use App\Enums\CentralPermission;
use App\Models\CentralAuditLog;
use App\Models\CentralPersonalAccessToken;
use App\Models\CentralUser;
use App\Models\User;
use Database\Seeders\CentralPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * W2-B3 security finding: an operator account created by `central:migrate-super-admins`
 * carries the legacy password (copied verbatim) and is flagged `must_reset_password`.
 * Signing in with that flag is refused with `central_auth.password_reset_required` and no
 * token of any kind, until the operator completes the existing reset-password flow.
 */
final class MustResetPasswordTest extends TenantTestCase
{
    private const LOGIN = '/api/v1/super-admin/auth/login';

    private const RESET = '/api/v1/super-admin/auth/reset-password';

    // Fake passwords; the `fixture` prefix marks them as fake for gitleaks (.gitleaks.toml).
    private const LEGACY_PASSWORD = 'fixtureLegacyOperatorPassword';

    private const NEW_PASSWORD = 'fixtureFreshOperatorPassword';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CentralPermissionsSeeder::class);

        // The seeder no longer creates the Phase 0 `web` super_admin role, but real central DBs
        // still hold it and LegacySuperAdminDirectory finds the legacy operators through it.
        Role::findOrCreate('super_admin', 'web');
    }

    private function migratedOperator(): CentralUser
    {
        $this->endTenancy();

        $legacy = new User;
        $legacy->forceFill([
            'name' => 'legacy operator',
            'email' => 'legacy-'.Str::lower(Str::random(8)).'@central.test',
            'password' => Hash::make(self::LEGACY_PASSWORD),
            'is_active' => true,
        ])->save();
        $legacy->assignRole('super_admin');

        app(MigrateLegacySuperAdminsAction::class)->execute([$legacy]);

        return CentralUser::query()->where('email', $legacy->email)->firstOrFail();
    }

    private function flag(CentralUser $user): bool
    {
        return (bool) DB::table('central_users')->where('id', $user->getKey())->value('must_reset_password');
    }

    public function test_migrated_operators_are_flagged(): void
    {
        $this->assertTrue($this->flag($this->migratedOperator()));
    }

    public function test_an_already_existing_operator_is_not_flagged_by_the_migration(): void
    {
        $existing = CentralUser::factory()->create([
            'email' => 'existing-'.Str::lower(Str::random(6)).'@central.test',
            'is_active' => true,
        ]);
        $existing->assignRole(CentralPermission::ROLE_SUPER_ADMIN);

        $legacy = new User;
        $legacy->forceFill([
            'name' => 'legacy twin',
            'email' => $existing->email,
            'password' => Hash::make(self::LEGACY_PASSWORD),
            'is_active' => true,
        ])->save();
        $legacy->assignRole('super_admin');

        app(MigrateLegacySuperAdminsAction::class)->execute([$legacy]);

        $this->assertFalse($this->flag($existing));
    }

    public function test_login_with_the_flag_returns_password_reset_required_and_no_token(): void
    {
        $operator = $this->migratedOperator();

        $this->postJson(self::LOGIN, ['email' => $operator->email, 'password' => self::LEGACY_PASSWORD])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'central_auth.password_reset_required')
            ->assertJsonPath('message', __('central_auth.password_reset_required'))
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('data.challenge_id');

        $this->assertSame(0, CentralPersonalAccessToken::query()->where('tokenable_id', $operator->getKey())->count());

        $failure = CentralAuditLog::query()
            ->where('event', CentralAuditEvent::LoginFailed->value)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('password_reset_required', $failure->properties['reason'] ?? null);
    }

    public function test_a_wrong_password_on_a_flagged_account_is_the_uniform_422(): void
    {
        $operator = $this->migratedOperator();

        $this->postJson(self::LOGIN, ['email' => $operator->email, 'password' => 'not-the-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_the_existing_reset_flow_clears_the_flag_and_login_works_again(): void
    {
        $operator = $this->migratedOperator();
        $token = CentralAuthSettings::passwordBroker()->createToken($operator);

        $this->postJson(self::RESET, [
            'email' => $operator->email,
            'token' => $token,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk();

        $this->assertFalse($this->flag($operator));

        $this->postJson(self::LOGIN, ['email' => $operator->email, 'password' => self::LEGACY_PASSWORD])
            ->assertUnprocessable();

        $this->postJson(self::LOGIN, ['email' => $operator->email, 'password' => self::NEW_PASSWORD])
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
