<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Tenancy\ProvisionerMySQLDatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;
use SensitiveParameter;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

/**
 * Test double of the OPS-2 provisioner (MySQL DDL): records databases and users instead of
 * running DDL, so ProvisionTenantJob's MySQL-only paths (per-tenant user, DROP USER) can be
 * exercised on the sqlite suite. The real DDL is covered by ProvisionWithNonRootAccountTest.
 */
final class FakeProvisionerMySQLDatabaseManager extends ProvisionerMySQLDatabaseManager
{
    public string $tenantId = '';

    /** @var array<string, true> */
    public array $databases = [];

    /** @var list<string> */
    public array $deletedDatabases = [];

    /** @var list<string> username@host */
    public array $createdUsers = [];

    /** @var list<string> username@host */
    public array $droppedUsers = [];

    public bool $failCreateUser = false;

    public ?string $seenPassword = null;

    /** Raw central `tenants.data` of the tenant at the moment CREATE USER ran. */
    public ?string $rowDataAtCreateUser = null;

    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        $this->databases[(string) $tenant->database()->getName()] = true;

        return true;
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        $name = (string) $tenant->database()->getName();
        unset($this->databases[$name]);
        $this->deletedDatabases[] = $name;

        return true;
    }

    public function databaseExists(string $name): bool
    {
        return isset($this->databases[$name]);
    }

    public function databaseIsEmpty(string $name): bool
    {
        return true;
    }

    public function userExists(string $username, string $host): bool
    {
        return in_array($username.'@'.$host, $this->createdUsers, true);
    }

    public function createUser(string $database, string $username, #[SensitiveParameter] string $password, string $host): void
    {
        $this->seenPassword = $password;
        $this->rowDataAtCreateUser = (string) DB::table('tenants')->where('id', $this->tenantId)->value('data');

        if ($this->failCreateUser) {
            // What MySQL + Laravel produce: the SQL (with the quoted password) in the message.
            $sql = "CREATE USER '{$username}'@'{$host}' IDENTIFIED BY '{$password}'";
            $pdo = new PDOException('SQLSTATE[HY000]: General error: 1396 Operation CREATE USER failed for '.$sql);
            $pdo->errorInfo = ['HY000', 1396, 'Operation CREATE USER failed'];

            throw new QueryException('provisioner', $sql, [], $pdo);
        }

        $this->createdUsers[] = $username.'@'.$host;
    }

    public function grant(string $database, string $username, string $host): void {}

    public function dropUser(string $username, string $host): void
    {
        $this->droppedUsers[] = $username.'@'.$host;
    }
}
