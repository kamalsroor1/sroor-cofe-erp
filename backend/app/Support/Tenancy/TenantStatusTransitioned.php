<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A tenant's status changed (IDEN-3.3). Dispatched AFTER the central transaction commits
 * (ShouldDispatchAfterCommit), so listeners (notifications IDEN-3.7, cache bust ENTI-2.x,
 * Telegram OPS-10) never see a change that was rolled back.
 *
 * Carries ids and values only, never models, so it is safe to queue.
 */
final class TenantStatusTransitioned implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $tenantId,
        public readonly ?TenantStatus $from,
        public readonly TenantStatus $to,
        public readonly TenantLifecycleActor $actor,
        public readonly ?TenantSuspensionReason $reason,
        public readonly ?int $centralUserId,
        public readonly int $lifecycleEventId,
    ) {}
}
