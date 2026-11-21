<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Actions\Tenants\AdvanceDocumentSequencesAction;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TenantTestCase;

/**
 * BKP-02 on a real MySQL / MariaDB server: backup:tenants -> backup:restore-tenant --keep-restored
 * into a second schema -> backup:swap-tenant. The shop must then point at the restored schema with
 * every token revoked and its document counters pushed forward, the previous schema untouched.
 * The restored schema is prepared (migrate, revoke, advance counters) BEFORE the repoint
 * (22ab9096), so a failure while advancing the counters must leave the shop on its previous schema.
 * The shop is suspended first: the command refuses a trading shop without --live.
 *
 * `mysql-only`: needs a MySQL server and the mysqldump / mysql clients (phpunit.mysql.xml).
 */
#[Group('mysql')]
#[Group('mysql-only')]
final class SwapTenantMysqlRoundTripTest extends TenantTestCase
{
    private const PASSWORD = 'fixture-archive-password-not-a-secret-bkp02';

    private const SCOPE = 'INV-MAIN-20200101';

    private string $tmp;

    /** @var list<string> */
    private array $restored = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sroor-bkp02-'.bin2hex(random_bytes(5));
        File::ensureDirectoryExists($this->tmp);

        Storage::fake('google');
        Notification::fake();
        Http::fake();

        config([
            'backup.backup.password' => self::PASSWORD,
            'backup.tenants.disks' => ['google'],
            'backup.tenants.path_prefix' => 'sroor-backups',
            'backup.tenants.temporary_directory' => $this->tmp.DIRECTORY_SEPARATOR.'work',
            'backup.tenants.verify_after_upload' => true,
            'backup.tenants.notify_telegram_chat_id' => null,
            'backup.tenants.notify_mail' => null,
        ]);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        DB::purge('tenant');
        foreach ($this->restored as $database) {
            DB::connection($this->provisioner())->statement("drop database if exists `{$database}`");
        }
        File::deleteDirectory($this->tmp);

        parent::tearDown();
    }

    public function test_a_shop_is_swapped_onto_its_restored_database_with_tokens_revoked_and_counters_advanced(): void
    {
        $tenant = $this->tenantWithData();
        $id = (string) $tenant->getTenantKey();
        $previous = (string) $tenant->database()->getName();
        $restored = $this->restoredCopy($tenant);

        $this->assertSame(1, $this->countIn($restored, 'personal_access_tokens'), 'the backup carries the token');

        $this->artisan('backup:swap-tenant', ['tenant' => $id, '--database' => $restored, '--number-margin' => 1000, '--force' => true])
            ->assertExitCode(0);

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame($restored, (string) $fresh->database()->getName());

        $this->inTenant($fresh, function () use ($restored): void {
            $this->assertSame($restored, (string) DB::connection()->getDatabaseName());
            $this->assertSame(0, DB::table('personal_access_tokens')->count());
            $this->assertSame(1007, (int) DB::table('document_sequences')->where('scope', self::SCOPE)->value('last_value'));
            $this->assertSame(1, DB::table('customers')->where('name', 'عميل قبل النسخة')->count());
        });

        // The previous database is never touched.
        $this->assertSame(1, $this->countIn($previous, 'personal_access_tokens'));
        $this->assertSame(7, (int) DB::connection($this->provisioner())
            ->table("{$previous}.document_sequences")->where('scope', self::SCOPE)->value('last_value'));
    }

    public function test_a_failure_while_preparing_the_restored_database_never_switches_the_shop(): void
    {
        $tenant = $this->tenantWithData();
        $id = (string) $tenant->getTenantKey();
        $previous = (string) $tenant->database()->getName();
        $restored = $this->restoredCopy($tenant);

        $this->app->instance(AdvanceDocumentSequencesAction::class, new class
        {
            /** @return array{bumped: int, created: int} */
            public function execute(int $margin): array
            {
                throw new RuntimeException('sequence table locked');
            }
        });

        $this->artisan('backup:swap-tenant', ['tenant' => $id, '--database' => $restored, '--force' => true])
            ->expectsOutputToContain((string) __('console.swap.prepare_failed', ['database' => $previous, 'reason' => RuntimeException::class]))
            ->assertExitCode(1);

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame($previous, (string) $fresh->database()->getName());
        $this->inTenant($fresh, function () use ($previous): void {
            $this->assertSame($previous, (string) DB::connection()->getDatabaseName());
            $this->assertSame(1, DB::table('personal_access_tokens')->count());
            $this->assertSame(7, (int) DB::table('document_sequences')->where('scope', self::SCOPE)->value('last_value'));
        });
        // The restored database is kept for the operator (never dropped by the swap).
        $this->assertSame(1, $this->countIn($restored, 'customers', 'عميل قبل النسخة'));
    }

    private function tenantWithData(): Tenant
    {
        foreach (['mysqldump', 'mysql'] as $client) {
            if ((new ExecutableFinder)->find($client) === null) {
                $this->fail("BKP-02: the `{$client}` client is required on PATH for the mysql group.");
            }
        }

        $tenant = $this->createTenant(['status' => TenantStatus::Suspended->value]);
        $this->tenantToken($tenant);

        $this->inTenant($tenant, static function (): void {
            DB::table('customers')->insert(['name' => 'عميل قبل النسخة', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('document_sequences')->insert(['scope' => self::SCOPE, 'last_value' => 7, 'created_at' => now(), 'updated_at' => now()]);
        });

        return $tenant;
    }

    /** backup:tenants then backup:restore-tenant --keep-restored into a fresh `<restore prefix>…` schema. */
    private function restoredCopy(Tenant $tenant): string
    {
        $id = (string) $tenant->getTenantKey();
        $this->artisan('backup:tenants', ['--tenant' => [$id]])->assertExitCode(0);

        $database = (string) config('backup.tenants.restore_database_prefix').'swap_'.Str::lower(Str::random(8));
        $this->restored[] = $database;

        $this->artisan('backup:restore-tenant', ['subject' => $id, '--database' => $database, '--keep-restored' => true])
            ->assertExitCode(0);

        return $database;
    }

    private function countIn(string $database, string $table, ?string $name = null): int
    {
        $query = DB::connection($this->provisioner())->table("{$database}.{$table}");
        if ($name !== null) {
            $query->where('name', $name);
        }

        return $query->count();
    }

    private function provisioner(): string
    {
        return (string) config('tenancy.provisioning.connection', 'provisioner');
    }
}
