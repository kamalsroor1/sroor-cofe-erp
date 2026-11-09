<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

/**
 * Outcome of one run of App\Actions\Tenants\ApplyTenantLifecycleAction (IDEN-3.6 / BE-05).
 */
final readonly class LifecycleSweepResult
{
    /**
     * @param  int  $checked  tenants evaluated
     * @param  list<array{tenant_id: string, from: string, to: string}>  $applied  moves persisted (or, on a dry run, that would be)
     * @param  list<string>  $archiveDue  tenants whose archive deadline passed: archiving needs a verified final backup (tenants:archive), never done by the sweep
     * @param  list<array{tenant_id: string, error: string}>  $failed  tenants whose move was refused or crashed
     * @param  list<array{tenant_id: string, status: string}>  $needsAttention  trial / past_due tenants with no deadline at all (null dates): the sweep never moves them, an operator must set the dates
     */
    public function __construct(
        public int $checked,
        public array $applied,
        public array $archiveDue,
        public array $failed,
        public array $needsAttention = [],
    ) {}
}
