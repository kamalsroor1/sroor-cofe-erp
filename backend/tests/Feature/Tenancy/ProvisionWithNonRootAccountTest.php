<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\DTOs\CreateTenantDTO;
use App\Enums\TenantProvisioningStatus;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisionerService;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;
use Throwable;

/**
 * OPS-2 on a real MySQL server: a tenant is provisioned end to end by an account that holds
 * ONLY the documented provisioner grants (vps-runbook.md §5):
 *
 *   GRANT CREATE USER ON *.*
 *   GRANT ALL PRIVILEGES ON `tenant\_%`.* WITH GRANT OPTION
 *   GRANT SELECT (`Host`, `User`) ON `mysql`.`user`
 *
 * with one MySQL user per tenant. Two tenants whose database names differ only by `_` vs `-`
 * (`tenant_<r>_a` / `tenant_<r>-a`) prove the escaped GRANT target (§12.2): tenant A's user
 * cannot reach tenant B's database.
 *
 * Group `mysql-only`: phpunit.xml (sqlite) excludes it; run with phpunit.mysql.xml. The
 * test accounts use host `%` (the suite connects over TCP); production uses `localhost`.
 */
#[Group('tenancy')]
#[Group('mysql')]
#[Group('mysql-only')]
final class ProvisionWithNonRootAccountTest extends TenantTestCase
{
    private const PROVISIONER_CONNECTION = 'qa_provisioner';

    private const ACCOUNT_HOST = '%';

    private string $provisionerUser = '';

    /** @var list<string> */
    private array $tenantIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $driver = DB::connection($this->centralConnectionName())->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->fail("ProvisionWithNonRootAccountTest needs MySQL/MariaDB (central driver: {$driver}). Run it with phpunit.mysql.xml.");
        }

        $this->beforeApplicationDestroyed(fn () => $this->cleanUp());
        $this->createProvisionerAccount();
    }

    public function test_a_tenant_is_provisioned_by_the_non_root_provisioner_with_its_own_escaped_user(): void
    {
        $random = Str::lower(Str::random(6));
        $underscore = $this->provision('qa'.$random.'_a');
        $dash = $this->provision('qa'.$random.'-a');

        foreach ([$underscore, $dash] as $tenant) {
            $fresh = Tenant::query()->findOrFail($tenant->getTenantKey());
            $this->assertSame(TenantProvisioningStatus::Ready, $fresh->provisioning_status, (string) $fresh->provisioning_error_code);
            // The created user is recorded by name (+ host), and it is the tenant's current username.
            $this->assertSame($fresh->getInternal('db_username'), $fresh->getInternal(TenantProvisionerService::CREATED_USER_KEY)['username'] ?? null);
            $this->assertSame($fresh->database()->getName(), $fresh->getInternal(TenantProvisionerService::CREATED_DATABASE_KEY));
            $this->assertNotSame($this->provisionerUser, $fresh->getInternal('db_username'));

            $seeded = $this->inTenant($fresh, static fn (): array => [
                'user' => DB::connection()->getConfig('username'),
                'admins' => User::query()->count(),
                'stores' => Store::query()->where('is_main', true)->count(),
            ]);
            $this->assertSame($fresh->getInternal('db_username'), $seeded['user'], 'The tenant connection uses its own MySQL user.');
            $this->assertSame(1, $seeded['admins']);
            $this->assertSame(1, $seeded['stores']);
        }

        $underscoreDb = (string) $underscore->database()->getName();
        $dashDb = (string) $dash->database()->getName();
        $grants = $this->grantsOf((string) Tenant::query()->findOrFail($underscore->getTenantKey())->getInternal('db_username'));

        $this->assertStringContainsString('`'.str_replace('_', '\\_', $underscoreDb).'`', $grants, 'GRANT target must be escaped.');

        // Tenant A's user reaches its own database but not B's (the unescaped grant would).
        $pdo = $this->connectAs(Tenant::query()->findOrFail($underscore->getTenantKey()));
        $own = $pdo->query("SELECT COUNT(*) FROM `{$underscoreDb}`.`users`");
        $this->assertNotFalse($own);
        $this->assertSame(1, (int) $own->fetchColumn());

        try {
            $pdo->query("SELECT COUNT(*) FROM `{$dashDb}`.`users`");
            $this->fail('Tenant A\'s MySQL user must not read tenant B\'s database.');
        } catch (PDOException $e) {
            $this->assertContains((int) ($e->errorInfo[1] ?? 0), [1044, 1142], $e->getMessage());
        }
    }

    private function provision(string $slug): Tenant
    {
        $this->tenantIds[] = $slug;

        return app(TenantProvisionerService::class)->provision(new CreateTenantDTO(
            name: 'MySQL '.$slug,
            slug: $slug,
            email: str_replace(['_', '-'], ['u', 'd'], $slug).'@mysql-provision.test',
            phone: '0100000'.random_int(1000, 9999),
            planId: $this->plan()->id,
            password: 'secret-pass-1234',
        ));
    }

    /** A provisioner holding exactly the runbook grants, wired as the provisioning connection. */
    private function createProvisionerAccount(): void
    {
        $root = $this->rootConnection();
        $this->provisionerUser = 'qa_prov_'.Str::lower(Str::random(8));
        $password = Str::password(32, symbols: false);
        $account = "'{$this->provisionerUser}'@'".self::ACCOUNT_HOST."'";
        $prefix = str_replace('_', '\\_', (string) config('tenancy.database.prefix', 'tenant_'));

        $root->statement("CREATE USER {$account} IDENTIFIED BY '{$password}'");
        $root->statement("GRANT CREATE USER ON *.* TO {$account}");
        $root->statement("GRANT ALL PRIVILEGES ON `{$prefix}%`.* TO {$account} WITH GRANT OPTION");
        $root->statement("GRANT SELECT (`Host`, `User`) ON `mysql`.`user` TO {$account}");

        $central = (array) config('database.connections.'.$this->centralConnectionName());
        config([
            'database.connections.'.self::PROVISIONER_CONNECTION => array_merge($central, [
                'database' => null,
                'username' => $this->provisionerUser,
                'password' => $password,
            ]),
            'tenancy.provisioning.connection' => self::PROVISIONER_CONNECTION,
            'tenancy.provisioning.per_tenant_db_user' => true,
            'tenancy.provisioning.db_user_host' => self::ACCOUNT_HOST,
            'tenancy.provisioning.queue_connection' => 'sync',
        ]);
    }

    private function grantsOf(string $username): string
    {
        $rows = $this->rootConnection()->select("SHOW GRANTS FOR '{$username}'@'".self::ACCOUNT_HOST."'");

        return implode("\n", array_map(static fn (object $row): string => (string) array_values((array) $row)[0], $rows));
    }

    private function connectAs(Tenant $tenant): PDO
    {
        $central = (array) config('database.connections.'.$this->centralConnectionName());

        return new PDO(
            sprintf('mysql:host=%s;port=%s', $central['host'], $central['port']),
            (string) $tenant->getInternal('db_username'),
            (string) $tenant->getInternal('db_password'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    private function rootConnection(): Connection
    {
        $name = 'qa_provision_root';
        config(["database.connections.{$name}" => (array) config('database.connections.'.$this->centralConnectionName())]);

        return DB::connection($name);
    }

    private function plan(): Plan
    {
        return Plan::query()->create([
            'name' => 'MySQL provisioning plan',
            'slug' => 'myprov-'.Str::lower(Str::random(6)),
            'price_monthly' => '100.000',
            'price_yearly' => '1000.000',
            'max_users' => 5,
            'max_stores' => 1,
            'max_items' => 100,
            'max_invoices_per_month' => 1000,
            'is_active' => true,
            'sort_order' => 1,
            'features' => [],
        ]);
    }

    private function cleanUp(): void
    {
        $this->endTenancy();
        DB::purge('tenant');
        DB::purge(self::PROVISIONER_CONNECTION);
        $root = $this->rootConnection();
        $prefix = (string) config('tenancy.database.prefix', 'tenant_');

        foreach ($this->tenantIds as $id) {
            try {
                $root->statement('DROP DATABASE IF EXISTS `'.$prefix.$id.'`');
            } catch (Throwable) {
                // best effort: the next run's harness sweep does not touch these
            }

            $storage = storage_path().'/'.config('tenancy.filesystem.suffix_base', 'tenant').$id;
            if (is_dir($storage)) {
                File::deleteDirectory($storage);
            }
        }

        foreach ($root->select("SELECT User FROM mysql.user WHERE User LIKE 'tu\\_%' AND Host = ?", [self::ACCOUNT_HOST]) as $row) {
            // Only users granted on this test's databases (per-tenant users of this run).
            $grants = $this->grantsOf((string) $row->User);
            foreach ($this->tenantIds as $id) {
                if (str_contains($grants, str_replace('_', '\\_', $prefix.$id))) {
                    $root->statement("DROP USER IF EXISTS '{$row->User}'@'".self::ACCOUNT_HOST."'");
                    break;
                }
            }
        }

        if ($this->provisionerUser !== '') {
            $root->statement("DROP USER IF EXISTS '{$this->provisionerUser}'@'".self::ACCOUNT_HOST."'");
        }

        DB::purge('qa_provision_root');
    }
}
