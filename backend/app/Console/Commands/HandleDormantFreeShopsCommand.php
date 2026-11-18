<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenants\HandleDormantFreeShopsAction;
use App\DTOs\Tenants\DormantFreeShopsRunResult;
use Illuminate\Console\Command;

/**
 * FREE-15: daily reminder (60 days) and archive (90 days) of dormant FREE shops. Data is kept,
 * nothing is ever deleted (no purge anywhere in this flow). Scheduled in routes/console.php.
 *
 *   php artisan tenants:handle-dormant-free [--dry-run]
 *
 * Exit code: failure only when at least one shop could not be handled (the others still ran).
 */
final class HandleDormantFreeShopsCommand extends Command
{
    protected $signature = 'tenants:handle-dormant-free {--dry-run : List what would be reminded or archived without sending, archiving or recording anything}';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('console.dormant_free.description'));
    }

    public function handle(HandleDormantFreeShopsAction $action): int
    {
        // Central context: the action only reads central tables.
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = $action->execute($dryRun);

        if ($result->state !== DormantFreeShopsRunResult::STATE_RAN) {
            $this->warn((string) __('console.dormant_free.'.$result->state));

            return self::SUCCESS;
        }

        if ($dryRun) {
            foreach ($result->reminded as $id) {
                $this->line((string) __('console.dormant_free.would_remind', ['tenant_id' => $id]));
            }
            foreach ($result->archived as $id) {
                $this->line((string) __('console.dormant_free.would_archive', ['tenant_id' => $id]));
            }
        }

        foreach ($result->failed as $id) {
            $this->error((string) __('console.dormant_free.failed_row', ['tenant_id' => $id]));
        }

        $this->info((string) __('console.dormant_free.summary', [
            'checked' => $result->checked,
            'reminded' => count($result->reminded),
            'archived' => count($result->archived),
            'postponed' => count($result->postponed),
            'skipped' => count($result->skipped),
            'failed' => count($result->failed),
        ]));

        return $result->failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
