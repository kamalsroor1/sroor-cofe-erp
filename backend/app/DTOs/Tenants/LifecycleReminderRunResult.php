<?php

declare(strict_types=1);

namespace App\DTOs\Tenants;

/**
 * SELL-02: outcome of one tenants:send-lifecycle-reminders run.
 *
 * Per reminder `status`: sent | already_sent | failed | would_send (dry run).
 * `operator`: sent | failed | not_configured | nothing (no unsent reminder) | dry_run | locked
 * (another run holds the lock; nothing was done).
 */
final readonly class LifecycleReminderRunResult
{
    public const OPERATOR_SENT = 'sent';

    public const OPERATOR_FAILED = 'failed';

    public const OPERATOR_NOT_CONFIGURED = 'not_configured';

    public const OPERATOR_NOTHING = 'nothing';

    public const OPERATOR_DRY_RUN = 'dry_run';

    public const OPERATOR_LOCKED = 'locked';

    /**
     * @param  list<array{tenant_id: string, tenant_name: string, kind: string, stage: string, days_left: int, target_date: string, status: string}>  $reminders
     */
    public function __construct(
        public bool $dryRun,
        public array $reminders,
        public string $operator,
    ) {}

    public function count(string $status): int
    {
        return count(array_filter($this->reminders, static fn (array $row): bool => $row['status'] === $status));
    }
}
