<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use InvalidArgumentException;
use SensitiveParameter;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;

/**
 * MySQL/MariaDB DDL for queued tenant provisioning (OPS-2), on the dedicated
 * `provisioner` connection (config('tenancy.provisioning.connection')), never root.
 *
 * Differences from stancl's managers (and from the legacy SafeMySQLDatabaseManager):
 *  - NOTHING is swallowed: every failure throws, the job turns it into an error code;
 *  - CREATE DATABASE without IF NOT EXISTS: an existing database is never silently reused
 *    (the job decides explicitly whether it may adopt one);
 *  - identifiers are validated before being quoted, values go through bindings / PDO quote;
 *  - the per-tenant user's GRANT target escapes `\`, `_` and `%` (vps-runbook.md §12.2):
 *    stock stancl grants on `tenant_shop_a`.* unescaped, which also matches `tenant_shop-a`
 *    (cross-tenant access), and the provisioner's strict `tenant\_%` grant refuses it.
 *
 * It is NOT registered in config('tenancy.database.managers'): runtime lookups (bootstrapper,
 * harness, legacy pipeline) keep using the app account; only the provisioning job uses this.
 */
class ProvisionerMySQLDatabaseManager extends MySQLDatabaseManager
{
    /**
     * Privileges a per-tenant user receives on its own database (stancl's list, which is
     * every database-level privilege of MySQL 8 = what the provisioner holds WITH GRANT OPTION).
     */
    public const TENANT_USER_GRANTS = [
        'ALTER', 'ALTER ROUTINE', 'CREATE', 'CREATE ROUTINE', 'CREATE TEMPORARY TABLES', 'CREATE VIEW',
        'DELETE', 'DROP', 'EVENT', 'EXECUTE', 'INDEX', 'INSERT', 'LOCK TABLES', 'REFERENCES', 'SELECT',
        'SHOW VIEW', 'TRIGGER', 'UPDATE',
    ];

    private const DATABASE_NAME_PATTERN = '/^[A-Za-z0-9_\-]{1,64}$/';

    private const USERNAME_PATTERN = '/^[A-Za-z0-9_]{1,32}$/';

    private const HOST_PATTERN = '/^[A-Za-z0-9.%_:\-]{1,255}$/';

    /** A manager bound to the configured provisioner connection. */
    public static function forProvisioning(): self
    {
        $manager = app(self::class);
        $manager->setConnection((string) config('tenancy.provisioning.connection', 'provisioner'));

        return $manager;
    }

    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        $database = self::assertDatabaseName((string) $tenant->database()->getName());
        $connection = $this->database();
        $charset = self::assertPlainWord((string) ($connection->getConfig('charset') ?: 'utf8mb4'));
        $collation = self::assertPlainWord((string) ($connection->getConfig('collation') ?: 'utf8mb4_unicode_ci'));

        return $connection->statement("CREATE DATABASE `{$database}` CHARACTER SET `{$charset}` COLLATE `{$collation}`");
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        $database = self::assertDatabaseName((string) $tenant->database()->getName());

        return $this->database()->statement("DROP DATABASE IF EXISTS `{$database}`");
    }

    public function databaseExists(string $name): bool
    {
        return $this->database()->selectOne(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$name],
        ) !== null;
    }

    /** True when the database holds no table and no view (safe to adopt). */
    public function databaseIsEmpty(string $name): bool
    {
        $row = $this->database()->selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
            [$name],
        );

        return (int) ($row->aggregate ?? 0) === 0;
    }

    public function userExists(string $username, string $host): bool
    {
        return $this->database()->selectOne(
            'SELECT 1 AS found FROM mysql.user WHERE User = ? AND Host = ?',
            [$username, $host],
        ) !== null;
    }

    /**
     * CREATE USER + GRANT on the (escaped) tenant database only. The password never reaches
     * a stack trace (#[SensitiveParameter]); the caller must not chain the QueryException of
     * a failed CREATE USER either, its SQL contains the quoted password.
     */
    public function createUser(string $database, string $username, #[SensitiveParameter] string $password, string $host): void
    {
        self::assertDatabaseName($database);
        $account = $this->account($username, $host);
        $connection = $this->database();
        $quotedPassword = $connection->getPdo()->quote($password);

        $connection->statement("CREATE USER {$account} IDENTIFIED BY {$quotedPassword}");
        $this->grant($database, $username, $host);
    }

    /** (Re-)grant the tenant user its privileges on its own database. Idempotent. */
    public function grant(string $database, string $username, string $host): void
    {
        $this->database()->statement(sprintf(
            'GRANT %s ON `%s`.* TO %s',
            implode(', ', self::TENANT_USER_GRANTS),
            self::grantTarget($database),
            $this->account($username, $host),
        ));
    }

    public function dropUser(string $username, string $host): void
    {
        $this->database()->statement('DROP USER IF EXISTS '.$this->account($username, $host));
    }

    /**
     * The database name as a GRANT target: inside the backticks `_` and `%` are wildcards
     * and `\` is the escape character, so all three are escaped (vps-runbook.md §12.2).
     */
    public static function grantTarget(string $database): string
    {
        return str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], self::assertDatabaseName($database));
    }

    public static function assertDatabaseName(string $name): string
    {
        if (preg_match(self::DATABASE_NAME_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException('Refusing an unsafe tenant database name.');
        }

        return $name;
    }

    private function account(string $username, string $host): string
    {
        if (preg_match(self::USERNAME_PATTERN, $username) !== 1 || preg_match(self::HOST_PATTERN, $host) !== 1) {
            throw new InvalidArgumentException('Refusing an unsafe tenant database account.');
        }

        return "'{$username}'@'{$host}'";
    }

    private static function assertPlainWord(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $value) !== 1) {
            throw new InvalidArgumentException('Refusing an unsafe charset/collation.');
        }

        return $value;
    }
}
