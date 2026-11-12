<?php

declare(strict_types=1);

namespace App\Support\Backup;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * BKP-01: content digests that prove a dump was not damaged between the moment it was taken
 * and the moment it is imported (the row count alone cannot see a flipped bit).
 *
 *  - fileSha256(): sha256 of the dump file itself;
 *  - tableDigests(): one sha256 per table.
 *      MySQL / MariaDB: over the dump TEXT that belongs to the table (its CREATE TABLE and INSERT
 *        lines, everything up to the next statement start), so any changed byte of any row moves
 *        the digest of exactly one table;
 *      sqlite (dev / tests): over the rows of the table read from the file, ordered by rowid.
 *    The same function runs at backup time and again on the extracted dump at restore time.
 */
final class BackupIntegrity
{
    private const LINE = 1048576;

    public static function fileSha256(string $file): string
    {
        $hash = hash_file('sha256', $file);
        if ($hash === false) {
            throw new RuntimeException((string) __('console.backups.dump_empty'));
        }

        return $hash;
    }

    /**
     * @return array<string, string> table => sha256, sorted by table name
     */
    public static function tableDigests(string $file, string $driver): array
    {
        return $driver === 'sqlite' ? self::sqliteDigests($file) : self::sqlDumpDigests($file);
    }

    /**
     * @param  array<string, string>  $expected
     * @param  array<string, string>  $actual
     * @return list<string> tables whose digest differs or that exist on one side only
     */
    public static function differences(array $expected, array $actual): array
    {
        $tables = array_unique([...array_keys($expected), ...array_keys($actual)]);
        $bad = [];
        foreach ($tables as $table) {
            if (! isset($expected[$table], $actual[$table]) || ! hash_equals((string) $expected[$table], (string) $actual[$table])) {
                $bad[] = (string) $table;
            }
        }
        sort($bad);

        return $bad;
    }

    /**
     * @return array<string, string>
     */
    private static function sqlDumpDigests(string $file): array
    {
        $probe = @fopen($file, 'rb');
        if ($probe === false) {
            throw new RuntimeException((string) __('console.backups.dump_empty'));
        }
        $magic = (string) fread($probe, 2);
        fclose($probe);

        $handle = @fopen($magic === "\x1f\x8b" ? 'compress.zlib://'.$file : $file, 'rb');
        if ($handle === false) {
            throw new RuntimeException((string) __('console.backups.dump_empty'));
        }

        $contexts = [];
        $current = '';

        try {
            while (($line = fgets($handle, self::LINE + 1)) !== false) {
                $first = $line[0] ?? '';
                if (($first === 'C' || $first === 'I' || $first === 'R')
                    && preg_match('/^(?:CREATE TABLE (?:IF NOT EXISTS )?|INSERT (?:IGNORE )?INTO |REPLACE INTO )`((?:[^`]|``)+)`/', $line, $m) === 1) {
                    $current = str_replace('``', '`', $m[1]);
                    $contexts[$current] ??= hash_init('sha256');
                }
                if ($current !== '') {
                    hash_update($contexts[$current], $line);
                }
            }
        } finally {
            fclose($handle);
        }

        $digests = [];
        foreach ($contexts as $table => $context) {
            $digests[(string) $table] = hash_final($context);
        }
        ksort($digests);

        return $digests;
    }

    /**
     * @return array<string, string>
     */
    private static function sqliteDigests(string $file): array
    {
        $name = 'backup_digest_'.bin2hex(random_bytes(4));
        config(['database.connections.'.$name => [
            'driver' => 'sqlite',
            'database' => $file,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        try {
            $connection = DB::connection($name);
            $tables = $connection->select(
                "select name from sqlite_master where type = 'table' and name not like 'sqlite\\_%' escape '\\' order by name",
            );
            $digests = [];
            foreach ($tables as $row) {
                $table = (string) ((array) $row)['name'];
                $context = hash_init('sha256');
                foreach ($connection->table($table)->orderByRaw('rowid')->cursor() as $record) {
                    hash_update($context, json_encode((array) $record, JSON_THROW_ON_ERROR)."\n");
                }
                $digests[$table] = hash_final($context);
            }
            ksort($digests);

            return $digests;
        } finally {
            DB::purge($name);
            config(['database.connections.'.$name => null]);
        }
    }
}
