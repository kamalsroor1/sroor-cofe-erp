<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\DTOs\CreateTenantDTO;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisionerService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\TenantSampleSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Stancl\Tenancy\Events\CreatingDatabase;
use Stancl\Tenancy\Events\DatabaseCreated;
use Stancl\Tenancy\Events\DatabaseMigrated;
use Stancl\Tenancy\Events\MigratingDatabase;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\Concerns\DetectsRealPhoneNumbers;
use Tests\TestCase;

/**
 * F1a: the central DatabaseSeeder, TenantSampleSeeder and every other seeder / console
 * command must not ship real phone numbers or known passwords.
 *
 * Contract pinned here:
 *  - exactly one super admin is seeded; its phone/email come from SEED_SUPER_ADMIN_PHONE /
 *    SEED_SUPER_ADMIN_EMAIL with an obvious dummy phone fallback;
 *  - its password comes from SEED_SUPER_ADMIN_PASSWORD, otherwise a random one;
 *  - a re-run never overwrites an existing user's password;
 *  - TenantSampleSeeder never provisions with the literal 'password'; it honours
 *    SEED_DEMO_TENANT_PASSWORD and uses a dummy phone.
 *
 * TenantSampleSeeder is replaced with a no-op in the DatabaseSeeder runs so no tenant
 * database is ever provisioned.
 */
final class DatabaseSeederSecurityTest extends TestCase
{
    use DetectsRealPhoneNumbers;
    use RefreshDatabase;

    /** @var list<string> */
    private const ENV_KEYS = [
        'SEED_SUPER_ADMIN_PHONE',
        'SEED_SUPER_ADMIN_EMAIL',
        'SEED_SUPER_ADMIN_PASSWORD',
        'SEED_DEMO_TENANT_PASSWORD',
    ];

    /** @var list<string> */
    private const KNOWN_WEAK_PASSWORDS = ['password', '123456789', '12345678', 'secret', 'admin'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::ENV_KEYS as $key) {
            $this->clearEnv($key);
        }

        Event::fake([
            TenantCreated::class,
            CreatingDatabase::class,
            DatabaseCreated::class,
            MigratingDatabase::class,
            DatabaseMigrated::class,
        ]);

        // Never provision a sample tenant from these runs.
        $this->app->instance(TenantSampleSeeder::class, new class extends TenantSampleSeeder
        {
            public function run(): void {}
        });
    }

    protected function tearDown(): void
    {
        foreach (self::ENV_KEYS as $key) {
            $this->clearEnv($key);
        }

        parent::tearDown();
    }

    private function setEnv(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function clearEnv(string $key): void
    {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }

    /** @return Collection<int, User> */
    private function superAdmins(): Collection
    {
        return User::role('super_admin')->get();
    }

    private function runDatabaseSeeder(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    public function test_it_seeds_exactly_one_super_admin_with_a_dummy_phone(): void
    {
        $this->runDatabaseSeeder();

        $admins = $this->superAdmins();
        $this->assertCount(1, $admins, 'Only one super admin may be seeded (admin2 with a known password must be gone).');

        foreach (User::query()->whereNotNull('phone')->pluck('phone') as $phone) {
            $this->assertTrue(
                self::isObviousDummyPhone((string) $phone),
                'A seeded user has a real-looking phone number (value intentionally not printed).'
            );
        }
    }

    #[DataProvider('weakPasswordProvider')]
    public function test_super_admin_password_is_not_a_known_weak_value(string $weak): void
    {
        $this->runDatabaseSeeder();

        foreach ($this->superAdmins() as $admin) {
            $this->assertFalse(Hash::check($weak, $admin->password), 'Super admin was seeded with a known weak password.');
        }
    }

    /** @return array<string, array{0: string}> */
    public static function weakPasswordProvider(): array
    {
        return array_combine(array_map(static fn (string $p): string => "weak: {$p}", self::KNOWN_WEAK_PASSWORDS), array_map(
            static fn (string $p): array => [$p],
            self::KNOWN_WEAK_PASSWORDS
        ));
    }

    public function test_super_admin_identity_and_password_come_from_env_when_set(): void
    {
        $this->setEnv('SEED_SUPER_ADMIN_PHONE', '01000000099');
        $this->setEnv('SEED_SUPER_ADMIN_EMAIL', 'owner@seed.test');
        $this->setEnv('SEED_SUPER_ADMIN_PASSWORD', 'Env-Provided-Pass-9');

        $this->runDatabaseSeeder();

        $admins = $this->superAdmins();
        $this->assertCount(1, $admins);

        $admin = $admins->first();
        $this->assertSame('01000000099', $admin->phone);
        $this->assertSame('owner@seed.test', $admin->email);
        $this->assertTrue(Hash::check('Env-Provided-Pass-9', $admin->password));
        $this->assertTrue($admin->hasRole('super_admin'));
    }

    public function test_generated_password_is_printed_once_and_matches_the_stored_hash(): void
    {
        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class]);
        $output = Artisan::output();

        $admin = $this->superAdmins()->first();
        $this->assertNotNull($admin);

        $plain = $this->findPrintedPassword($output, $admin->password);
        $this->assertNotNull($plain, 'The generated super admin password must be printed once so the operator can log in.');

        // A re-run must not print a password again (no user was created).
        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class]);
        $this->assertStringNotContainsString($plain, Artisan::output());
    }

    /**
     * Format-agnostic search for the plain password in console output: try every
     * whitespace-delimited token, also with up to 3 surrounding punctuation chars
     * stripped (random passwords may themselves end in punctuation).
     */
    private function findPrintedPassword(string $output, string $hash): ?string
    {
        foreach (preg_split('/\s+/u', $output) ?: [] as $token) {
            $length = strlen($token);

            for ($left = 0; $left <= 3; $left++) {
                for ($right = 0; $right <= 3; $right++) {
                    $candidateLength = $length - $left - $right;

                    if ($candidateLength < 12) {
                        continue;
                    }

                    $candidate = substr($token, $left, $candidateLength);

                    if (Hash::check($candidate, $hash)) {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    public function test_password_from_env_is_never_echoed(): void
    {
        $this->setEnv('SEED_SUPER_ADMIN_PASSWORD', 'Env-Provided-Pass-9');

        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class]);

        $this->assertStringNotContainsString('Env-Provided-Pass-9', Artisan::output());
    }

    public function test_a_second_run_never_overwrites_an_existing_password(): void
    {
        $this->runDatabaseSeeder();
        $admin = $this->superAdmins()->first();
        $this->assertNotNull($admin);
        $hashBefore = $admin->password;

        // Even if the operator now provides a password via env, an existing user keeps theirs.
        $this->setEnv('SEED_SUPER_ADMIN_PASSWORD', 'Another-Pass-77');
        $this->runDatabaseSeeder();

        $this->assertCount(1, $this->superAdmins());
        $this->assertSame($hashBefore, $admin->fresh()->password);
    }

    public function test_tenant_sample_seeder_never_provisions_with_a_known_password(): void
    {
        $dto = $this->captureTenantSampleDto();

        $this->assertNotContains($dto->password, self::KNOWN_WEAK_PASSWORDS);
        $this->assertGreaterThanOrEqual(12, strlen($dto->password), 'Random demo tenant password is too short.');
        $this->assertTrue(
            $dto->phone === null || $dto->phone === '' || self::isObviousDummyPhone($dto->phone),
            'Demo tenant phone must be an obvious dummy (value intentionally not printed).'
        );
    }

    public function test_tenant_sample_seeder_uses_password_from_env_when_set(): void
    {
        $this->setEnv('SEED_DEMO_TENANT_PASSWORD', 'Demo-Env-Pass-42');

        $dto = $this->captureTenantSampleDto();

        $this->assertSame('Demo-Env-Pass-42', $dto->password);
    }

    #[DataProvider('passwordLiteralSourcesProvider')]
    public function test_seeders_and_commands_contain_no_hardcoded_password_literals(string $relativeDir): void
    {
        $offenders = [];

        foreach (glob(base_path($relativeDir).'/*.php') ?: [] as $file) {
            $contents = (string) file_get_contents($file);

            $patterns = [
                // bcrypt('...') / Hash::make('...') with a literal argument
                '/(?:bcrypt|Hash::make)\(\s*[\'"][^\'"]*[\'"]\s*\)/',
                // named argument password: '...'
                '/\bpassword:\s*[\'"][^\'"]*[\'"]/',
                // 'password' => '...'
                '/[\'"]password[\'"]\s*=>\s*[\'"][^\'"]*[\'"]/',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $offenders[] = $relativeDir.'/'.basename($file);

                    break;
                }
            }
        }

        $this->assertSame([], $offenders, 'Hardcoded password literal found; use env() or Str::password() + Hash::make().');
    }

    /** @return array<string, array{0: string}> */
    public static function passwordLiteralSourcesProvider(): array
    {
        return [
            'seeders' => ['database/seeders'],
            'console commands' => ['app/Console/Commands'],
        ];
    }

    public function test_populate_realistic_tenant_data_command_uses_dummy_identities(): void
    {
        $contents = (string) file_get_contents(app_path('Console/Commands/PopulateRealisticTenantDataCommand.php'));

        $this->assertSame(
            0,
            self::countRealLookingPhones($contents),
            'PopulateRealisticTenantDataCommand still contains real-looking phone numbers (values intentionally not printed).'
        );

        // Staff users are keyed by phone; every key must be an obvious dummy.
        preg_match_all("/User::firstOrCreate\(\s*\[\s*'phone'\s*=>\s*'([^']+)'/", $contents, $matches);
        $this->assertNotEmpty($matches[1], 'Expected staff users to be keyed by phone.');

        foreach ($matches[1] as $phone) {
            $this->assertTrue(self::isObviousDummyPhone($phone), 'Staff user key is not an obvious dummy phone.');
        }

        // The --password option and random fallback must remain (no known default password).
        $this->assertStringContainsString('{--password=', $contents);
        $this->assertStringContainsString('Str::password(', $contents);
    }

    private function captureTenantSampleDto(): CreateTenantDTO
    {
        // Unbind the no-op so the real TenantSampleSeeder runs.
        $this->app->forgetInstance(TenantSampleSeeder::class);
        $this->app->offsetUnset(TenantSampleSeeder::class);

        $this->assertNull(Tenant::find('tenant_sroor'));
        $this->seed(PlansAndFeaturesSeeder::class);

        $fake = new class extends TenantProvisionerService
        {
            public ?CreateTenantDTO $captured = null;

            public function __construct() {}

            public function provision(CreateTenantDTO $dto): Tenant
            {
                $this->captured = $dto;

                // Stop here: never provision a real tenant database from a test.
                throw new RuntimeException('captured');
            }
        };
        $this->app->instance(TenantProvisionerService::class, $fake);

        try {
            $this->seed(TenantSampleSeeder::class);
        } catch (RuntimeException $e) {
            $this->assertSame('captured', $e->getMessage());
        }

        $this->assertNotNull($fake->captured, 'TenantSampleSeeder did not call the provisioner.');

        return $fake->captured;
    }
}
