<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Billing\IssueTrialEndInvoicesAction;
use Illuminate\Console\Command;

/**
 * SELL-06: daily, issue the first subscription invoice of trials ending within the lead days
 * (config billing.trial_invoice_lead_days, 3). Scheduled in routes/console.php. Idempotent.
 *
 *   php artisan billing:issue-trial-invoices [--dry-run]
 */
final class IssueTrialEndInvoicesCommand extends Command
{
    protected $signature = 'billing:issue-trial-invoices {--dry-run : List the invoices that would be issued without issuing them}';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('console.trial_invoices.description'));
    }

    public function handle(IssueTrialEndInvoicesAction $action): int
    {
        // Central only: billing rows live in the central DB.
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = $action->execute($dryRun);

        foreach ($result->issued as $row) {
            $this->line((string) __($dryRun ? 'console.trial_invoices.would_issue' : 'console.trial_invoices.issued', [
                'tenant_id' => $row['tenant_id'],
                'trial_ends_at' => $row['trial_ends_at'],
                'number' => (string) $row['number'],
                'total' => (string) $row['total'],
            ]));
        }
        foreach ($result->skipped as $row) {
            $this->line((string) __('console.trial_invoices.skipped', [
                'tenant_id' => $row['tenant_id'],
                'reason' => (string) __('console.trial_invoices.reasons.'.$row['reason']),
            ]));
        }
        foreach ($result->failed as $row) {
            $this->error((string) __('console.trial_invoices.failed', $row));
        }

        $this->info((string) __('console.trial_invoices.summary', [
            'checked' => $result->checked,
            'issued' => count($result->issued),
            'skipped' => count($result->skipped),
            'failed' => count($result->failed),
        ]));

        return $result->failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
