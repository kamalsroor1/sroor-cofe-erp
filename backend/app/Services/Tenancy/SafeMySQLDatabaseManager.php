<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;
use Throwable;

class SafeMySQLDatabaseManager extends MySQLDatabaseManager
{
    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        $database = $tenant->database()->getName();
        $charset = $this->database()->getConfig('charset') ?: 'utf8mb4';
        $collation = $this->database()->getConfig('collation') ?: 'utf8mb4_unicode_ci';

        try {
            return $this->database()->statement("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET `{$charset}` COLLATE `{$collation}`");
        } catch (Throwable $e) {
            // On Shared Hosting where DB is pre-created in hPanel, gracefully continue to migrations
            return true;
        }
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        try {
            return parent::deleteDatabase($tenant);
        } catch (Throwable $e) {
            return true;
        }
    }
}
