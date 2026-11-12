<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Database\TransactionRetry;
use App\Support\Database\WriteIsolationBinlog;
use Illuminate\Console\Command;

/**
 * BUG-07 condition: `php artisan db:check-write-isolation` exits 1 when the money/stock writes
 * would run at READ COMMITTED on a server whose binary log is ON with binlog_format=STATEMENT
 * (InnoDB refuses those writes, error 1665), or when the connection cannot be checked. Read-only.
 * For any box with a release (VPS, Hostinger, a developer machine). The same rule also runs in
 * `health:check` and the deploy gate (WriteIsolationBinlogCheck), and in SQL in
 * scripts/ops/verify.sh (which runs before any release exists).
 */
final class CheckWriteIsolationCommand extends Command
{
    protected $signature = 'db:check-write-isolation';

    protected $description = 'Fail when READ COMMITTED writes meet binlog_format=STATEMENT (BUG-07)';

    public function handle(): int
    {
        $level = TransactionRetry::isolationLevel();
        $this->line((string) __('console.write_isolation.level', ['level' => $level ?? __('console.write_isolation.server_level')]));

        $violations = WriteIsolationBinlog::violations();
        if ($violations === []) {
            $this->info((string) __('console.write_isolation.ok'));

            return self::SUCCESS;
        }

        foreach ($violations as $violation) {
            $this->error((string) __('console.write_isolation.'.$violation['problem'], ['connection' => $violation['connection']]));
        }

        return self::FAILURE;
    }
}
