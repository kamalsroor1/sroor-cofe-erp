<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\BackupTenantsCommand;
use App\Models\TenantBackup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * Code review (W2 lane 3I): the manifest row counts that backup:restore-tenant verifies are
 * read from the DUMP, not from the live database on another connection before the
 * `--single-transaction` snapshot (busy tables used to mismatch => count_mismatch on restore).
 *
 *  - MySQL/MariaDB: countDumpRows() parses the .sql file (CREATE TABLE => 0, INSERT tuples);
 *  - sqlite: the VACUUM INTO copy is opened and counted;
 *  - the manifest says so: `tables_source` = `dump`.
 */
final class BackupManifestFromDumpTest extends TenantTestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sroor-manifest-'.bin2hex(random_bytes(5));
        File::ensureDirectoryExists($this->tmp);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);

        parent::tearDown();
    }

    /** @return array<string, array{0: string, 1: int}> */
    public static function insertValues(): array
    {
        return [
            'single row' => ["(1,'a');", 1],
            'extended insert' => ["(1,'a'),(2,'b'),(3,NULL);", 3],
            'parens and commas in strings' => ["(1,'x),(y'),(2,'(()'),(3,'a,b');", 3],
            'escaped quotes and backslashes' => ["(1,'it\\'s'),(2,'c:\\\\dir\\\\'),(3,'say \\\"hi\\\"');", 3],
            'doubled quotes' => ["(1,'it''s (fine)'),(2,'''');", 2],
            'hex blob and function call' => ['(1,0x28290A),(2,ST_GeomFromText(\'POINT(1 1)\'));', 2],
            'arabic text' => ["(1,'قهوة (محمصة)'),(2,'بن، هيل');", 2],
            'empty' => [';', 0],
        ];
    }

    #[DataProvider('insertValues')]
    public function test_insert_tuples_are_counted_at_top_level_only(string $values, int $expected): void
    {
        $this->assertSame($expected, BackupTenantsCommand::countInsertTuples($values));
    }

    public function test_a_mysqldump_file_is_counted_per_table(): void
    {
        $dump = implode("\n", [
            '-- MySQL dump 10.13',
            '/*!40101 SET NAMES utf8mb4 */;',
            'DROP TABLE IF EXISTS `sales`;',
            'CREATE TABLE `sales` (',
            '  `id` bigint unsigned NOT NULL AUTO_INCREMENT,',
            "  `note` varchar(255) DEFAULT NULL COMMENT 'INSERT INTO `fake` VALUES (1)',",
            '  PRIMARY KEY (`id`)',
            ') ENGINE=InnoDB;',
            'LOCK TABLES `sales` WRITE;',
            "INSERT INTO `sales` VALUES (1,'a'),(2,'b (x)');",
            "INSERT INTO `sales` VALUES (3,'c');",
            'UNLOCK TABLES;',
            'CREATE TABLE `empty_table` (`id` int) ENGINE=InnoDB;',
            'CREATE TABLE `odd``name` (`id` int) ENGINE=InnoDB;',
            'INSERT INTO `odd``name` (`id`) VALUES (1),(2);',
            '/*!50001 CREATE VIEW `v_sales` AS select 1 AS `x` */;',
            '',
        ]);
        $file = $this->tmp.DIRECTORY_SEPARATOR.'database.sql';
        file_put_contents($file, $dump);

        $this->assertSame(
            ['empty_table' => 0, 'odd`name' => 2, 'sales' => 3],
            BackupTenantsCommand::countDumpRows($file),
        );
    }

    public function test_a_sqlite_dump_file_is_counted_from_the_file_itself(): void
    {
        $file = $this->tmp.DIRECTORY_SEPARATOR.'copy.sqlite';
        touch($file);
        $name = 'manifest_fixture_'.bin2hex(random_bytes(3));
        config(['database.connections.'.$name => ['driver' => 'sqlite', 'database' => $file, 'prefix' => '']]);
        DB::connection($name)->statement('create table probe (id integer primary key)');
        DB::connection($name)->table('probe')->insert([['id' => 1], ['id' => 2]]);
        DB::purge($name);

        $this->assertSame(['probe' => 2], BackupTenantsCommand::countSqliteFileRows($file));
    }

    public function test_the_archived_manifest_describes_the_dump(): void
    {
        Storage::fake('google');
        Notification::fake();
        config([
            'backup.backup.password' => 'fixture-archive-password-not-a-secret-0002',
            'backup.tenants.disks' => ['google'],
            'backup.tenants.path_prefix' => 'sroor-backups',
            'backup.tenants.temporary_directory' => $this->tmp.DIRECTORY_SEPARATOR.'work',
            'backup.tenants.verify_after_upload' => true,
        ]);
        $tenant = $this->createTenant();
        $this->createTenantUser($tenant);

        $this->artisan('backup:tenants', ['--tenant' => [(string) $tenant->getTenantKey()]])->assertExitCode(0);

        $row = TenantBackup::query()->where('tenant_id', $tenant->getTenantKey())->sole();
        $manifest = BackupTenantsCommand::readManifest(Storage::disk('google')->path($row->path), 'fixture-archive-password-not-a-secret-0002');

        $this->assertSame(BackupTenantsCommand::TABLES_SOURCE_DUMP, $manifest['tables_source'] ?? null);
        $this->assertSame(
            $this->inTenant($tenant, static fn (): int => User::query()->count()),
            $manifest['tables']['users'],
        );
    }
}
