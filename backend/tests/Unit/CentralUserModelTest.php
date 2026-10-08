<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CentralPersonalAccessToken;
use App\Models\CentralUser;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Guard;
use Tests\TenantTestCase;

/**
 * IDEN-1.1: the platform operator identity is a standalone CentralUser.
 *
 *  - it is NOT an App\Models\User (no tenant role/permission/store logic leaks in);
 *  - it lives in `central_users` and always reads/writes the central connection,
 *    even while a tenant is initialized;
 *  - its tokens live in `central_personal_access_tokens` only, always expire
 *    (default 240 minutes) and carry the `central:*` ability by default;
 *  - the auth config exposes `central` (sanctum) + `central_web` (session) guards
 *    and no `super_admin` guard;
 *  - the two migrations roll back cleanly.
 */
#[Group('mysql')]
final class CentralUserModelTest extends TenantTestCase
{
    private const USERS_MIGRATION = 'database/migrations/2026_10_10_100000_create_central_users_table.php';

    private const TOKENS_MIGRATION = 'database/migrations/2026_10_10_100010_create_central_personal_access_tokens_table.php';

    /**
     * Later central migrations whose tables hold an FK to central_users. `migrate:rollback`
     * drops them before central_users (MySQL refuses otherwise, error 3730), so the
     * round trip below does the same: down() newest first, up() oldest first.
     *
     * @var list<string>
     */
    private const DEPENDENT_MIGRATIONS = [
        'database/migrations/2026_10_10_200510_create_billing_invoices_table.php',   // issued_by
        'database/migrations/2026_10_10_200520_create_billing_payments_table.php',   // verified_by (+ FK to billing_invoices)
        'database/migrations/2026_10_10_400000_create_platform_settings_table.php',  // updated_by
    ];

    public function test_central_user_is_standalone_and_not_a_tenant_user(): void
    {
        $user = CentralUser::factory()->create();

        // Runtime proof (not just the static type): User is nowhere in the class hierarchy.
        $this->assertNotContains(User::class, (array) class_parents($user));
        $this->assertNotContains(User::class, (array) class_implements($user));
        $this->assertSame('central_users', $user->getTable());
        $this->assertSame('central', Guard::getDefaultName($user));
        $this->assertTrue($user->is_active);
    }

    public function test_sensitive_attributes_are_hidden_and_not_mass_assignable(): void
    {
        // Factories run unguarded, so mass assignment is checked through fill() directly.
        $filled = (new CentralUser)->fill([
            'name' => 'مشغل',
            'email' => 'filled@central.test',
            'password' => 'secret-password',
            'two_factor_secret' => 'should-not-be-mass-assigned',
            'two_factor_confirmed_at' => now(),
            'last_login_ip' => '10.0.0.1',
        ]);
        $this->assertNull($filled->two_factor_secret);
        $this->assertNull($filled->two_factor_confirmed_at);
        $this->assertNull($filled->last_login_ip);
        $this->assertNotSame('secret-password', $filled->password, 'Passwords are hashed by the cast.');

        $user = CentralUser::factory()->create();

        $user->forceFill([
            'two_factor_secret' => 'encrypted-secret',
            'two_factor_recovery_codes' => 'encrypted-codes',
            'remember_token' => 'remember-me',
        ])->save();

        $array = $user->fresh()?->toArray() ?? [];

        foreach (['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $array, "{$hidden} must never be serialized.");
        }
    }

    public function test_connection_stays_central_while_a_tenant_is_initialized(): void
    {
        $central = $this->centralConnectionName();
        $operator = CentralUser::factory()->create(['email' => 'operator@central.test']);
        $tenant = $this->createTenant();

        $seen = $this->inTenant($tenant, fn (): array => [
            'default' => DB::getDefaultConnection(),
            'model' => (new CentralUser)->getConnectionName(),
            'token_model' => (new CentralPersonalAccessToken)->getConnectionName(),
            'email' => CentralUser::query()->whereKey($operator->getKey())->value('email'),
            'tenant_has_table' => Schema::hasTable('central_users'),
        ]);

        $this->assertNotSame($central, $seen['default'], 'Tenancy must switch the default connection for this test to mean anything.');
        $this->assertSame($central, $seen['model']);
        $this->assertSame($central, $seen['token_model']);
        $this->assertSame('operator@central.test', $seen['email']);
        $this->assertFalse($seen['tenant_has_table'], 'central_users must never exist in a tenant DB.');
    }

    public function test_create_token_writes_only_to_the_central_token_table(): void
    {
        Carbon::setTestNow('2026-10-10 10:00:00');

        $user = CentralUser::factory()->create();
        $centralDb = DB::connection($this->centralConnectionName());
        $sanctumTokensBefore = $centralDb->table('personal_access_tokens')->count();

        $newToken = $user->createToken('operator-login');

        $this->assertInstanceOf(CentralPersonalAccessToken::class, $newToken->accessToken);
        $this->assertSame(1, $centralDb->table('central_personal_access_tokens')->count());
        $this->assertSame($sanctumTokensBefore, $centralDb->table('personal_access_tokens')->count());

        $row = $centralDb->table('central_personal_access_tokens')->first();
        $this->assertNotNull($row);
        $this->assertSame(CentralUser::class, $row->tokenable_type);
        $this->assertSame((int) $user->getKey(), (int) $row->tokenable_id);
        $this->assertSame(['central:*'], json_decode((string) $row->abilities, true));

        // Default TTL is 240 minutes (CTO Q-B4), never "no expiry".
        $this->assertSame(240, (int) config('central.token_ttl_minutes'));
        $this->assertNotNull($newToken->accessToken->expires_at);
        $this->assertTrue($newToken->accessToken->expires_at->equalTo(Carbon::parse('2026-10-10 14:00:00')));

        // Lookup only through the central token model; Sanctum's default model never sees it.
        $plain = $newToken->plainTextToken;
        $found = CentralPersonalAccessToken::findToken($plain);
        $this->assertNotNull($found);
        $this->assertTrue($found->tokenable?->is($user) ?? false);
        $this->assertNull(PersonalAccessToken::findToken($plain));

        Carbon::setTestNow();
    }

    public function test_create_token_inside_a_tenant_still_writes_to_the_central_db(): void
    {
        $user = CentralUser::factory()->create();
        $tenant = $this->createTenant();

        $tenantTokensBefore = $this->inTenant($tenant, fn (): int => DB::table('personal_access_tokens')->count());

        $plain = $this->inTenant($tenant, fn (): string => $user->createToken('in-tenant')->plainTextToken);

        $this->assertSame($tenantTokensBefore, $this->inTenant($tenant, fn (): int => DB::table('personal_access_tokens')->count()));
        $this->assertSame(1, DB::connection($this->centralConnectionName())->table('central_personal_access_tokens')->count());
        $this->assertNotNull(CentralPersonalAccessToken::findToken($plain));
        $this->assertNull($this->inTenant($tenant, fn (): ?PersonalAccessToken => PersonalAccessToken::findToken($plain)));
    }

    public function test_explicit_expiry_and_abilities_are_respected(): void
    {
        $user = CentralUser::factory()->create();
        $expiresAt = now()->addMinutes(15)->startOfSecond();

        $token = $user->createToken('short', ['central:read'], $expiresAt)->accessToken;

        $this->assertSame(['central:read'], $token->abilities);
        $this->assertNotNull($token->expires_at);
        $this->assertTrue($token->expires_at->equalTo($expiresAt));
    }

    public function test_tokens_relation_uses_the_central_token_model(): void
    {
        $user = CentralUser::factory()->create();
        $user->createToken('one');
        $user->createToken('two');

        $this->assertSame(2, $user->tokens()->count());
        $this->assertInstanceOf(CentralPersonalAccessToken::class, $user->tokens()->firstOrFail());
    }

    public function test_auth_config_has_central_guards_and_no_super_admin_guard(): void
    {
        $guards = (array) config('auth.guards');

        $this->assertArrayNotHasKey('super_admin', $guards);
        $this->assertSame(['driver' => 'sanctum', 'provider' => 'central_users'], $guards['central'] ?? null);
        $this->assertSame(['driver' => 'session', 'provider' => 'central_users'], $guards['central_web'] ?? null);
        $this->assertSame(CentralUser::class, config('auth.providers.central_users.model'));
    }

    public function test_migrations_create_the_expected_columns(): void
    {
        $schema = Schema::connection($this->centralConnectionName());

        $this->assertTrue($schema->hasColumns('central_users', [
            'id', 'name', 'email', 'password', 'is_active',
            'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
            'last_login_at', 'last_login_ip', 'remember_token', 'created_at', 'updated_at',
        ]));
        $this->assertFalse($schema->hasColumn('central_users', 'phone'), 'Operators log in by email only (CTO Q-B7).');

        $this->assertTrue($schema->hasColumns('central_personal_access_tokens', [
            'id', 'tokenable_type', 'tokenable_id', 'name', 'token', 'abilities',
            'last_used_at', 'expires_at', 'created_at', 'updated_at',
        ]));
    }

    public function test_migrations_roll_back_cleanly_and_re_run(): void
    {
        $schema = Schema::connection($this->centralConnectionName());
        $users = require base_path(self::USERS_MIGRATION);
        $tokens = require base_path(self::TOKENS_MIGRATION);
        $dependents = array_map(static fn (string $path): object => require base_path($path), self::DEPENDENT_MIGRATIONS);

        foreach (array_reverse($dependents) as $dependent) {
            $dependent->down();
        }
        $tokens->down();
        $users->down();

        $this->assertFalse($schema->hasTable('central_personal_access_tokens'));
        $this->assertFalse($schema->hasTable('central_users'));
        $this->assertTrue($schema->hasTable('users'), 'Rollback must not touch the legacy central users table.');
        $this->assertTrue($schema->hasTable('personal_access_tokens'));

        $users->up();
        $tokens->up();
        foreach ($dependents as $dependent) {
            $dependent->up();
        }

        $this->assertTrue($schema->hasTable('central_users'));
        $this->assertTrue($schema->hasTable('central_personal_access_tokens'));
        $this->assertTrue($schema->hasTable('billing_invoices'));
        $this->assertTrue($schema->hasTable('billing_payments'));
        $this->assertTrue($schema->hasTable('platform_settings'));
    }
}
