<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

/**
 * SELL-06: outcome of one IssueTrialEndInvoicesAction run (billing:issue-trial-invoices).
 *
 * `issued` holds what was issued (or, on a dry run, what would be: number/total null).
 */
final readonly class TrialInvoiceRunResult
{
    /**
     * @param  list<array{tenant_id: string, trial_ends_at: string, number: string|null, total: string|null}>  $issued
     * @param  list<array{tenant_id: string, reason: string}>  $skipped  reason = console.trial_invoices.reasons.* key
     * @param  list<array{tenant_id: string, error: string}>  $failed
     */
    public function __construct(
        public bool $dryRun,
        public int $checked,
        public array $issued,
        public array $skipped,
        public array $failed,
    ) {}
}
