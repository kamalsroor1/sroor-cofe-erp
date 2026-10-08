<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Enums\TenantStatus;
use Carbon\CarbonImmutable;

/**
 * One time-driven move the lifecycle sweep still has to persist (IDEN-3.1):
 * the tenant became `$to` at `$at`. Always a legal TenantLifecycleActor::System move.
 */
final readonly class LifecycleTransitionStep
{
    public function __construct(
        public TenantStatus $from,
        public TenantStatus $to,
        public CarbonImmutable $at,
    ) {}
}
