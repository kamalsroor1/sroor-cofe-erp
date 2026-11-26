<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Enums\TenantAccessLevel;
use App\Enums\TenantStatus;
use App\Support\Tenancy\TenantLifecyclePolicy;
use App\Support\Tenancy\TenantLifecycleSnapshot;
use App\Support\Tenancy\TenantSuspensionReason;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * #232 spec D: an open temporary-access window lifts a payment-related state to full access,
 * never a violation suspension, a cancellation or an archive, and it changes nothing once ended.
 * The effective status (where the shop returns) is not changed by the window.
 */
final class TemporaryAccessPolicyTest extends TestCase
{
    private function policy(): TenantLifecyclePolicy
    {
        return new TenantLifecyclePolicy(pastDueGraceDays: 3, readOnlyDays: 30, retentionDays: 90, trialExtensionDays: 7, trialGraceDays: 0);
    }

    public function test_payment_related_states_get_full_access_until_the_window_ends(): void
    {
        $now = CarbonImmutable::parse('2026-11-26 10:00:00');
        $until = $now->addHours(48);

        $snapshots = [
            'trial ended' => new TenantLifecycleSnapshot(TenantStatus::Trial, $now->subDays(20), trialEndsAt: $now->subDays(2), temporaryAccessEndsAt: $until),
            'read only' => new TenantLifecycleSnapshot(TenantStatus::ReadOnly, $now->subDays(2), temporaryAccessEndsAt: $until),
            'non-payment suspension' => new TenantLifecycleSnapshot(TenantStatus::Suspended, $now->subDays(2), suspensionReason: TenantSuspensionReason::NonPayment, temporaryAccessEndsAt: $until),
            'suspension derived by time' => new TenantLifecycleSnapshot(TenantStatus::ReadOnly, $now->subDays(40), temporaryAccessEndsAt: $until),
        ];

        foreach ($snapshots as $label => $snapshot) {
            $during = $this->policy()->evaluate($snapshot, $now);
            $this->assertSame(TenantAccessLevel::Full, $during->access(), $label);
            $this->assertTrue($during->hasTemporaryAccess(), $label);

            $after = $this->policy()->evaluate($snapshot, $until);
            $this->assertFalse($after->hasTemporaryAccess(), $label);
            $this->assertNotSame(TenantAccessLevel::Full, $after->access(), $label);
            $this->assertSame($during->effectiveStatus, $after->effectiveStatus, $label.': the window never moves the lifecycle itself');
        }
    }

    public function test_violation_cancellation_and_archive_are_never_lifted(): void
    {
        $now = CarbonImmutable::parse('2026-11-26 10:00:00');
        $until = $now->addHours(48);

        foreach ([
            new TenantLifecycleSnapshot(TenantStatus::Suspended, $now->subDay(), suspensionReason: TenantSuspensionReason::Violation, temporaryAccessEndsAt: $until),
            new TenantLifecycleSnapshot(TenantStatus::Cancelled, $now->subDay(), temporaryAccessEndsAt: $until),
            new TenantLifecycleSnapshot(TenantStatus::Archived, $now->subDay(), temporaryAccessEndsAt: $until),
        ] as $snapshot) {
            $decision = $this->policy()->evaluate($snapshot, $now);
            $this->assertSame(TenantAccessLevel::Blocked, $decision->access());
            $this->assertFalse($decision->hasTemporaryAccess());
        }
    }
}
