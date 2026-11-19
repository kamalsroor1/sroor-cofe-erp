<?php

declare(strict_types=1);

namespace App\Actions\DataExport;

use App\Enums\DataExportStatus;
use App\Models\DataExport;
use App\Support\Csv\CsvCell;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * PRD-17: build the ZIP of CSVs for one DataExport of the CURRENT tenant (tenancy must be
 * initialised: the job's QueueTenancyBootstrapper does it).
 *
 * Every main entity table is streamed in id order, in chunks, into its own UTF-8 (BOM) CSV
 * through CsvCell (formula-injection safe). Credentials never leave the shop: `settings`,
 * tokens, sessions and the like are not exported at all, `users` is reduced to an allowlist
 * of identity columns, and any column whose name looks like a secret is dropped everywhere.
 *
 * Files are written to a private temp dir, zipped there, then streamed to the tenant-suffixed
 * private disk under a server-generated name. No DB transaction is held around the file I/O;
 * the status row is updated on its own at the start and the end.
 */
final class BuildTenantDataExportAction
{
    /**
     * CSV name => tenant table. Only tables that exist are exported (older shop schemas).
     *
     * @var array<string, string>
     */
    public const TABLES = [
        'stores' => 'stores',
        'categories' => 'categories',
        'items' => 'items',
        'store_stocks' => 'store_stocks',
        'customers' => 'customers',
        'suppliers' => 'suppliers',
        'invoices' => 'invoices',
        'invoice_items' => 'invoice_items',
        'purchases' => 'purchases',
        'purchase_items' => 'purchase_items',
        'returns' => 'returns',
        'return_items' => 'return_items',
        'payments' => 'payments',
        'expenses' => 'expenses',
        'additional_expenses' => 'additional_expenses',
        'opening_balances' => 'opening_balances',
        'stock_movements' => 'stock_movements',
        'stock_transfers' => 'stock_transfers',
        'stock_transfer_items' => 'stock_transfer_items',
        'stock_deposits' => 'stock_deposits',
        'treasury_transfers' => 'treasury_transfers',
        'cash_shifts' => 'cash_shifts',
        'users' => 'users',
    ];

    /** @var array<string, list<string>> tables reduced to an explicit column allowlist */
    public const COLUMN_ALLOWLIST = [
        'users' => ['id', 'name', 'email', 'phone', 'is_active', 'locale', 'last_login_at', 'created_at', 'updated_at', 'deleted_at'],
    ];

    /** Column names that are never exported, whatever the table. */
    private const SECRET_COLUMN_PATTERN = '/(^|_)(password|passwd|token|secret|remember|recovery|otp)(_|$)|(^|_)pin(_hash)?$|^pos_pin/i';

    public function execute(DataExport $export): DataExport
    {
        $export->forceFill([
            'status' => DataExportStatus::Processing,
            'started_at' => now(),
            'error' => null,
        ])->save();

        $workDir = storage_path('framework/data-export-'.Str::uuid()->toString());
        $zipPath = $workDir.'.zip';

        try {
            if (! is_dir($workDir) && ! mkdir($workDir, 0700, true) && ! is_dir($workDir)) {
                throw new RuntimeException('data export: cannot create work dir');
            }

            $manifest = [];
            $totalRows = 0;
            foreach (self::TABLES as $name => $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                $rows = $this->writeTable($table, $workDir.'/'.$name.'.csv');
                $manifest[] = [$name, $rows];
                $totalRows += $rows;
            }

            $this->writeCsv($workDir.'/manifest.csv', ['file', 'rows'], $manifest);
            $this->zip($workDir, $zipPath);

            $disk = Storage::disk((string) config('data_export.disk', 'local'));
            $path = trim((string) config('data_export.directory', 'data-exports'), '/').'/'.Str::uuid()->toString().'.zip';

            $stream = fopen($zipPath, 'rb');
            if ($stream === false || ! $disk->writeStream($path, $stream)) {
                throw new RuntimeException('data export: cannot store the archive');
            }
            if (is_resource($stream)) {
                fclose($stream);
            }

            $export->forceFill([
                'status' => DataExportStatus::Ready,
                'file_path' => $path,
                'file_size' => (int) filesize($zipPath),
                'tables_count' => count($manifest),
                'rows_count' => $totalRows,
                'completed_at' => now(),
                'expires_at' => now()->addHours(max(1, (int) config('data_export.retention_hours', 24))),
            ])->save();

            return $export;
        } catch (Throwable $e) {
            report($e);
            $export->forceFill([
                'status' => DataExportStatus::Failed,
                'error' => Str::limit(class_basename($e), 250, ''),
                'completed_at' => now(),
            ])->save();

            return $export;
        } finally {
            $this->cleanup($workDir, $zipPath);
        }
    }

    /**
     * @return list<string>
     */
    public static function exportableColumns(string $table): array
    {
        $columns = Schema::getColumnListing($table);

        if (isset(self::COLUMN_ALLOWLIST[$table])) {
            $columns = array_values(array_intersect(self::COLUMN_ALLOWLIST[$table], $columns));
        }

        return array_values(array_filter(
            $columns,
            static fn (string $column): bool => preg_match(self::SECRET_COLUMN_PATTERN, $column) !== 1,
        ));
    }

    private function writeTable(string $table, string $file): int
    {
        $columns = self::exportableColumns($table);
        $handle = $this->open($file, $columns);
        $count = 0;

        try {
            $query = DB::table($table)->select($columns);
            $chunk = max(50, (int) config('data_export.chunk_size', 500));

            $rows = in_array('id', $columns, true) ? $query->lazyById($chunk, 'id') : $query->orderBy($columns[0])->lazy($chunk);

            foreach ($rows as $row) {
                $values = [];
                foreach ($columns as $column) {
                    $values[] = $row->{$column} ?? null;
                }
                fwrite($handle, CsvCell::row($values));
                $count++;
            }
        } finally {
            fclose($handle);
        }

        return $count;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     */
    private function writeCsv(string $file, array $headers, array $rows): void
    {
        $handle = $this->open($file, $headers);
        foreach ($rows as $row) {
            fwrite($handle, CsvCell::row($row));
        }
        fclose($handle);
    }

    /**
     * @param  list<string>  $headers
     * @return resource
     */
    private function open(string $file, array $headers)
    {
        $handle = fopen($file, 'wb');
        if ($handle === false) {
            throw new RuntimeException('data export: cannot write a CSV file');
        }
        // UTF-8 BOM so Excel shows Arabic correctly (same as ExportService).
        fwrite($handle, "\xEF\xBB\xBF");
        fwrite($handle, CsvCell::row($headers));

        return $handle;
    }

    private function zip(string $dir, string $zipPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('data export: cannot create the archive');
        }

        foreach (glob($dir.'/*.csv') ?: [] as $file) {
            $zip->addFile($file, basename($file));
        }

        if (! $zip->close()) {
            throw new RuntimeException('data export: cannot finalise the archive');
        }
    }

    private function cleanup(string $dir, string $zipPath): void
    {
        foreach (glob($dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        if (is_dir($dir)) {
            @rmdir($dir);
        }
        if (is_file($zipPath)) {
            @unlink($zipPath);
        }
    }
}
