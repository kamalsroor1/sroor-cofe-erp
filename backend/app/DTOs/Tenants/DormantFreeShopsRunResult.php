<?php

declare(strict_types=1);

namespace App\DTOs\Tenants;

/**
 * FREE-15: outcome of one tenants:handle-dormant-free run (tenant ids only, never names or emails).
 *
 * `state`: ran | disabled | locked | no_free_plans.
 * `postponed`: dormant long enough to archive, but the reminder was missing or too recent
 * (the reminder is sent first and the archive waits for a later run).
 */
final readonly class DormantFreeShopsRunResult
{
    public const STATE_RAN = 'ran';

    public const STATE_DISABLED = 'disabled';

    public const STATE_LOCKED = 'locked';

    public const STATE_NO_FREE_PLANS = 'no_free_plans';

    /**
     * @param  list<string>  $reminded
     * @param  list<string>  $archived
     * @param  list<string>  $postponed
     * @param  list<string>  $skipped  archive already in progress / activity resumed meanwhile / daily cap reached
     * @param  list<string>  $failed
     */
    public function __construct(
        public string $state,
        public bool $dryRun,
        public int $checked = 0,
        public array $reminded = [],
        public array $archived = [],
        public array $postponed = [],
        public array $skipped = [],
        public array $failed = [],
    ) {}
}
