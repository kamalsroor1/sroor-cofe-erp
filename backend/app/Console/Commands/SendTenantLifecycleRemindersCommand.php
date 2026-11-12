<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenants\SendLifecycleRemindersAction;
use App\DTOs\Tenants\LifecycleReminderRunResult;
use Illuminate\Console\Command;

/**
 * SELL-02: daily trial / subscription expiry reminders (in-app for the shop, one Telegram
 * summary for the operator). Scheduled in routes/console.php. Idempotent per tenant, reminder
 * and period.
 *
 *   php artisan tenants:send-lifecycle-reminders [--dry-run]
 *
 * Exit code: failure only when a tenant notification could not be written (a Telegram failure
 * is reported as a warning and retried by the next run of the same day).
 */
final class SendTenantLifecycleRemindersCommand extends Command
{
    protected $signature = 'tenants:send-lifecycle-reminders {--dry-run : List the reminders that are due without sending or recording anything}';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('console.lifecycle_reminders.description'));
    }

    public function handle(SendLifecycleRemindersAction $action): int
    {
        // Central context: the action opens each tenant itself.
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = $action->execute($dryRun);

        if ($result->operator === LifecycleReminderRunResult::OPERATOR_LOCKED) {
            $this->warn((string) __('console.lifecycle_reminders.locked'));

            return self::SUCCESS;
        }

        foreach ($result->reminders as $row) {
            $line = (string) __('console.lifecycle_reminders.row', [
                'tenant_id' => $row['tenant_id'],
                'kind' => (string) __('console.lifecycle_reminders.kinds.'.$row['kind']),
                'stage' => $row['stage'],
                'date' => $row['target_date'],
                'status' => (string) __('console.lifecycle_reminders.statuses.'.$row['status']),
            ]);

            $row['status'] === 'failed' ? $this->error($line) : $this->line($line);
        }

        $operatorLine = (string) __('console.lifecycle_reminders.operator.'.$result->operator);
        $result->operator === LifecycleReminderRunResult::OPERATOR_FAILED ? $this->warn($operatorLine) : $this->line($operatorLine);

        $this->info((string) __('console.lifecycle_reminders.summary', [
            'due' => count($result->reminders),
            'sent' => $result->count('sent'),
            'already' => $result->count('already_sent'),
            'failed' => $result->count('failed'),
        ]));

        return $result->count('failed') === 0 ? self::SUCCESS : self::FAILURE;
    }
}
