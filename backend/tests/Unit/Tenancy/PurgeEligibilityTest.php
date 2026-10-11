<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Enums\TenantStatus;
use App\Support\Tenancy\PurgeEligibility;
use App\Support\Tenancy\TenantSuspensionReason;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * OPS-9 (CTO Q3): purge eligibility is pure (facts + injected clock).
 * archived ≥ 30 days, suspended / cancelled ≥ 90 days, a violation never, nothing else.
 */
final class PurgeEligibilityTest extends TestCase
{
    private const NOW = '2026-12-31 12:00:00';

    private static function now(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::NOW);
    }

    private static function evaluate(
        ?TenantStatus $status,
        ?TenantSuspensionReason $reason = null,
        ?CarbonImmutable $archivedAt = null,
        ?CarbonImmutable $changedAt = null,
        bool $purged = false,
    ): PurgeEligibility {
        return PurgeEligibility::evaluate($status, $reason, $archivedAt, $changedAt, $purged, self::now(), 30, 90);
    }

    public function test_archived_for_30_days_is_eligible_exactly_at_the_boundary(): void
    {
        $archivedAt = self::now()->subDays(30);

        $decision = self::evaluate(TenantStatus::Archived, archivedAt: $archivedAt);

        $this->assertTrue($decision->eligible);
        $this->assertNull($decision->reason);
        $this->assertTrue($archivedAt->addDays(30)->equalTo($decision->eligibleAt));
    }

    public function test_archived_one_second_short_of_30_days_is_too_recent_and_says_when(): void
    {
        $archivedAt = self::now()->subDays(30)->addSecond();

        $decision = self::evaluate(TenantStatus::Archived, archivedAt: $archivedAt, changedAt: self::now()->subYear());

        $this->assertFalse($decision->eligible);
        $this->assertSame(PurgeEligibility::REASON_TOO_RECENT, $decision->reason);
        $this->assertSame(self::now()->addSecond()->toIso8601String(), $decision->eligibleAt?->toIso8601String(), 'archived_at wins over status_changed_at.');
    }

    public function test_archived_without_archived_at_falls_back_to_status_changed_at(): void
    {
        $decision = self::evaluate(TenantStatus::Archived, changedAt: self::now()->subDays(45));

        $this->assertTrue($decision->eligible);
    }

    /** @return array<string, array{0: TenantStatus}> */
    public static function blockedStatuses(): array
    {
        return ['suspended' => [TenantStatus::Suspended], 'cancelled' => [TenantStatus::Cancelled]];
    }

    #[DataProvider('blockedStatuses')]
    public function test_blocked_tenants_need_90_days(TenantStatus $status): void
    {
        $this->assertFalse(self::evaluate($status, TenantSuspensionReason::NonPayment, changedAt: self::now()->subDays(89))->eligible);

        $decision = self::evaluate($status, TenantSuspensionReason::NonPayment, changedAt: self::now()->subDays(90));
        $this->assertTrue($decision->eligible);
    }

    /** @return array<string, array{0: TenantStatus}> */
    public static function violationStatuses(): array
    {
        return [
            'archived' => [TenantStatus::Archived],
            'suspended' => [TenantStatus::Suspended],
            'cancelled' => [TenantStatus::Cancelled],
        ];
    }

    #[DataProvider('violationStatuses')]
    public function test_a_violation_is_never_eligible_however_old(TenantStatus $status): void
    {
        $decision = self::evaluate($status, TenantSuspensionReason::Violation, self::now()->subYears(5), self::now()->subYears(5));

        $this->assertFalse($decision->eligible);
        $this->assertSame(PurgeEligibility::REASON_VIOLATION, $decision->reason);
        $this->assertNull($decision->eligibleAt, 'Waiting never makes a violation eligible.');
    }

    /** @return array<string, array{0: TenantStatus|null}> */
    public static function neverEligible(): array
    {
        return [
            'trial' => [TenantStatus::Trial],
            'active' => [TenantStatus::Active],
            'past_due' => [TenantStatus::PastDue],
            'read_only' => [TenantStatus::ReadOnly],
            'unknown' => [null],
        ];
    }

    #[DataProvider('neverEligible')]
    public function test_working_or_unknown_statuses_are_never_eligible(?TenantStatus $status): void
    {
        $decision = self::evaluate($status, archivedAt: self::now()->subYears(3), changedAt: self::now()->subYears(3));

        $this->assertFalse($decision->eligible);
        $this->assertSame(PurgeEligibility::REASON_STATUS, $decision->reason);
        $this->assertNull($decision->eligibleAt);
    }

    public function test_an_unknown_anchor_is_not_eligible(): void
    {
        $decision = self::evaluate(TenantStatus::Suspended);

        $this->assertFalse($decision->eligible);
        $this->assertSame(PurgeEligibility::REASON_NO_ANCHOR, $decision->reason);
    }

    public function test_an_already_purged_tenant_is_not_eligible(): void
    {
        $decision = self::evaluate(TenantStatus::Archived, archivedAt: self::now()->subYear(), purged: true);

        $this->assertFalse($decision->eligible);
        $this->assertSame(PurgeEligibility::REASON_PURGED, $decision->reason);
    }

    public function test_the_clock_is_injected_not_read(): void
    {
        $archivedAt = CarbonImmutable::parse('2026-01-01 00:00:00');

        $before = PurgeEligibility::evaluate(TenantStatus::Archived, null, $archivedAt, null, false, CarbonImmutable::parse('2026-01-30 23:59:59'), 30, 90);
        $after = PurgeEligibility::evaluate(TenantStatus::Archived, null, $archivedAt, null, false, CarbonImmutable::parse('2026-01-31 00:00:00'), 30, 90);

        $this->assertFalse($before->eligible);
        $this->assertTrue($after->eligible);
    }
}
