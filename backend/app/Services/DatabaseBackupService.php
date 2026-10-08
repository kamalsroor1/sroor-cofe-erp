<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DatabaseBackupService
{
    /**
     * Create a complete, gzipped SQL dump of the database.
     * Returns the absolute path of the generated .sql.gz file.
     */
    public function createSqlGzBackup(): string
    {
        $backupDir = storage_path('app/backups');
        if (! is_dir($backupDir)) {
            @mkdir($backupDir, 0775, true);
        }

        $dateStr = now()->format('Y-m-d_H-i-s');
        $filename = "database_backup_{$dateStr}.sql";
        $sqlPath = "{$backupDir}/{$filename}";
        $gzPath = "{$sqlPath}.gz";

        $handle = fopen($sqlPath, 'w');
        if (! $handle) {
            throw new \RuntimeException("تعذر إنشاء ملف النسخة الاحتياطية في: {$sqlPath}");
        }

        $appName = config('app.name', 'Laravel');
        fwrite($handle, "-- ========================================================\n");
        fwrite($handle, "-- {$appName} Database Backup\n");
        fwrite($handle, '-- Generated: '.now()->toDateTimeString()."\n");
        fwrite($handle, "-- ========================================================\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($handle, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

        $driver = DB::connection()->getDriverName();
        $tables = [];

        if ($driver === 'mysql') {
            $tableResults = DB::select('SHOW TABLES');
            foreach ($tableResults as $row) {
                $arr = (array) $row;
                $tables[] = reset($arr);
            }
        } elseif ($driver === 'sqlite') {
            $tableResults = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            foreach ($tableResults as $row) {
                $tables[] = $row->name;
            }
        }

        foreach ($tables as $table) {
            if ($driver === 'mysql') {
                $createTable = DB::select("SHOW CREATE TABLE `{$table}`");
                if (! empty($createTable)) {
                    $createSql = ((array) $createTable[0])['Create Table'] ?? '';
                    fwrite($handle, "-- Structure for table `{$table}`\n");
                    fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
                    fwrite($handle, "{$createSql};\n\n");
                }
            }

            // Dump rows with chunking to protect memory
            DB::table($table)->orderByRaw('1')->chunk(500, function ($rows) use ($handle, $table) {
                if ($rows->isEmpty()) {
                    return;
                }

                $columns = array_keys((array) $rows[0]);
                $colList = '`'.implode('`, `', $columns).'`';

                fwrite($handle, "-- Data for table `{$table}`\n");
                foreach ($rows as $row) {
                    $vals = [];
                    foreach ((array) $row as $val) {
                        if ($val === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = "'".addslashes((string) $val)."'";
                        }
                    }
                    $valList = implode(', ', $vals);
                    fwrite($handle, "INSERT INTO `{$table}` ({$colList}) VALUES ({$valList});\n");
                }
                fwrite($handle, "\n");
            });
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        // Compress using gzencode (Maximum compression level 9)
        $data = file_get_contents($sqlPath);
        $gzData = gzencode($data, 9);
        file_put_contents($gzPath, $gzData);

        // Remove uncompressed .sql
        @unlink($sqlPath);

        return $gzPath;
    }
}
