<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Enums\TenantAccessLevel;
use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Support\Tenancy\LifecycleDecision;
use App\Support\Tenancy\LifecycleTransitionStep;
use App\Support\Tenancy\TenantLifecyclePolicy;
use App\Support\Tenancy\TenantLifecycleSnapshot;
use App\Support\Tenancy\TenantSuspensionReason;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * IDEN-3.1: the pure tenant lifecycle policy and the TenantStatus state machine.
 *
 * Deliberately extends the bare PHPUnit TestCase: no Laravel application is booted,
 * so any hidden now()/config()/DB/cache call inside the policy would blow up here.
 *
 * CTO decisions encoded (2026-10-08, Q-L1/Q-L2/Q-L4):
 *  - trial ends → read-only 30 days → suspended → data kept 90 days → archived;
 *  - active ends → past_due 7-day grace (still working) → read-only 30 days → suspended → …;
 *  - read-only writes are HTTP 423, blocked states are HTTP 403;
 *  - a never-paid trial may be extended +7 days once (also from read-only).
 */
class TenantLifecyclePolicyTest extends TestCase
{
    private const GRACE = 7;

    private const READ_ONLY = 30;

    private const RETENTION = 90;

    private const EXTENSION = 7;

    private function policy(?int $retention = self::RETENTION): TenantLifecyclePolicy
    {
        return new TenantLifecyclePolicy(
            pastDueGraceDays: self::GRACE,
            readOnlyDays: self::READ_ONLY,
            retentionDays: $retention,
            trialExtensionDays: self::EXTENSION,
        );
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($time, 'UTC');
    }

    // ------------------------------------------------------------------
    // State machine
    // ------------------------------------------------------------------

    public function test_there_are_exactly_seven_statuses(): void
    {
        $this->assertSame(
            ['trial', 'active', 'past_due', 'read_only', 'suspended', 'cancelled', 'archived'],
            TenantStatus::values(),
        );
        $this->assertNotContains('expired', TenantStatus::values(), '`expired` is not a tenant status (subscriptions only).');
    }

    /**
     * The full transition table: from → to → actors allowed. Everything not listed is invalid.
     *
     * @return array<string, array<string, list<TenantLifecycleActor>>>
     */
    private static function expectedTransitions(): array
    {
        $system = TenantLifecycleActor::System;
        $admin = TenantLifecycleActor::SuperAdmin;
        $billing = TenantLifecycleActor::Billing;

        return [
            'trial' => ['active' => [$billing], 'read_only' => [$system, $admin], 'suspended' => [$admin], 'cancelled' => [$admin]],
            'active' => ['past_due' => [$system], 'read_only' => [$admin], 'suspended' => [$admin], 'cancelled' => [$admin]],
            'past_due' => ['active' => [$billing], 'read_only' => [$system, $admin], 'suspended' => [$admin], 'cancelled' => [$admin]],
            'read_only' => ['active' => [$billing], 'trial' => [$admin], 'suspended' => [$system, $admin], 'cancelled' => [$admin]],
            'suspended' => ['active' => [$billing], 'read_only' => [$admin], 'cancelled' => [$admin], 'archived' => [$system, $admin]],
            'cancelled' => ['active' => [$billing], 'archived' => [$system, $admin]],
            // CTO W1 Q2: archive is reversible by a super-admin (back to status_before_archive).
            'archived' => ['suspended' => [$admin], 'cancelled' => [$admin]],
        ];
    }

    /**
     * @return iterable<string, array{0: TenantStatus, 1: TenantStatus, 2: TenantLifecycleActor, 3: bool}>
     */
    public static function everyTransition(): iterable
    {
        $table = self::expectedTransitions();

        foreach (TenantStatus::cases() as $from) {
            foreach (TenantStatus::cases() as $to) {
                foreach (TenantLifecycleActor::cases() as $actor) {
                    $allowed = in_array($actor, $table[$from->value][$to->value] ?? [], true);

                    yield "{$from->value} -> {$to->value} by {$actor->value}" => [$from, $to, $actor, $allowed];
                }
            }
        }
    }

    #[DataProvider('everyTransition')]
    public function test_state_machine_matches_the_table(TenantStatus $from, TenantStatus $to, TenantLifecycleActor $actor, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to, $actor));
        $this->assertSame($allowed, $this->policy()->canTransition($from, $to, $actor));
    }

    public function test_only_billing_can_make_a_tenant_active(): void
    {
        foreach (TenantStatus::cases() as $from) {
            $this->assertFalse($from->canTransitionTo(TenantStatus::Active, TenantLifecycleActor::SuperAdmin), $from->value);
            $this->assertFalse($from->canTransitionTo(TenantStatus::Active, TenantLifecycleActor::System), $from->value);
        }
    }

    public function test_no_self_transitions_and_archived_is_terminal_for_automatic_moves(): void
    {
        foreach (TenantStatus::cases() as $status) {
            foreach (TenantLifecycleActor::cases() as $actor) {
                $this->assertFalse($status->canTransitionTo($status, $actor), $status->value);
            }
        }

        $this->assertTrue(TenantStatus::Archived->isTerminal());
        $this->assertSame([], TenantStatus::Archived->allowedTargets(TenantLifecycleActor::System));
        $this->assertSame([], TenantStatus::Archived->allowedTargets(TenantLifecycleActor::Billing));
    }

    public function test_archive_is_reversible_by_a_super_admin_only_and_only_from_suspended_or_cancelled(): void
    {
        $this->assertSame(
            [TenantStatus::Suspended, TenantStatus::Cancelled],
            TenantStatus::Archived->allowedTargets(TenantLifecycleActor::SuperAdmin),
        );

        foreach (TenantStatus::cases() as $status) {
            $expected = in_array($status, [TenantStatus::Suspended, TenantStatus::Cancelled], true);
            $this->assertSame($expected, $status->canBeArchived(), $status->value);
            $this->assertSame($expected, $status->canTransitionTo(TenantStatus::Archived, TenantLifecycleActor::SuperAdmin), $status->value);
        }
    }

    public function test_allowed_targets_lists_the_table_row_for_an_actor(): void
    {
        $this->assertSame(
            [TenantStatus::ReadOnly, TenantStatus::Suspended, TenantStatus::Cancelled],
            TenantStatus::Trial->allowedTargets(TenantLifecycleActor::SuperAdmin),
        );
        $this->assertSame([TenantStatus::PastDue], TenantStatus::Active->allowedTargets(TenantLifecycleActor::System));
    }

    // ------------------------------------------------------------------
    // Access levels and HTTP codes
    // ------------------------------------------------------------------

    public function test_access_level_per_status(): void
    {
        $expected = [
            'trial' => TenantAccessLevel::Full,
            'active' => TenantAccessLevel::Full,
            'past_due' => TenantAccessLevel::Full,
            'read_only' => TenantAccessLevel::ReadOnly,
            'suspended' => TenantAccessLevel::Blocked,
            'cancelled' => TenantAccessLevel::Blocked,
            'archived' => TenantAccessLevel::Blocked,
        ];

        foreach (TenantStatus::cases() as $status) {
            $this->assertSame($expected[$status->value], $status->accessLevel(), $status->value);
        }
    }

    public function test_denied_http_status_is_423_for_read_only_writes_and_403_for_blocked(): void
    {
        $this->assertNull(TenantAccessLevel::Full->deniedStatus(isWrite: true));
        $this->assertNull(TenantAccessLevel::Full->deniedStatus(isWrite: false));
        $this->assertSame(423, TenantAccessLevel::ReadOnly->deniedStatus(isWrite: true));
        $this->assertNull(TenantAccessLevel::ReadOnly->deniedStatus(isWrite: false));
        $this->assertSame(403, TenantAccessLevel::Blocked->deniedStatus(isWrite: true));
        $this->assertSame(403, TenantAccessLevel::Blocked->deniedStatus(isWrite: false));
    }

    // ------------------------------------------------------------------
    // evaluate(): derived status, next deadline, days left
    // ------------------------------------------------------------------

    public function test_running_trial_stays_trial_with_days_left(): void
    {
        $decision = $this->policy()->evaluate(new TenantLifecycleSnapshot(
            status: TenantStatus::Trial,
            statusChangedAt: $this->at('2026-10-01 00:00:00'),
            trialEndsAt: $this->at('2026-10-15 00:00:00'),
        ), $this->at('2026-10-10 12:00:00'));

        $this->assertSame(TenantStatus::Trial, $decision->storedStatus);
        $this->assertSame(TenantStatus::Trial, $decision->effectiveStatus);
        $this->assertFalse($decision->isDerived());
        $this->assertSame([], $decision->pendingTransitions);
        $this->assertSame(TenantStatus::ReadOnly, $decision->nextStatus);
        $this->assertEquals($this->at('2026-10-15 00:00:00'), $decision->nextTransitionAt);
        $this->assertSame(5, $decision->daysLeft, '4.5 days round up to 5.');
        $this->assertTrue($decision->canWrite());
        $this->assertTrue($decision->canRead());
        $this->assertNull($decision->deniedStatus(isWrite: true));
    }

    public function test_trial_end_is_inclusive_and_derives_read_only(): void
    {
        $decision = $this->policy()->evaluate(new TenantLifecycleSnapshot(
            status: TenantStatus::Trial,
            trialEndsAt: $this->at('2026-10-15 00:00:00'),
        ), $this->at('2026-10-15 00:00:00'));

        $this->assertSame(TenantStatus::ReadOnly, $decision->effectiveStatus);
        $this->assertTrue($decision->isDerived());
        $this->assertEquals($this->at('2026-10-15 00:00:00'), $decision->effectiveSince);
        $this->assertSame(TenantStatus::Suspended, $decision->nextStatus);
        $this->assertEquals($this->at('2026-11-14 00:00:00'), $decision->nextTransitionAt);
        $this->assertSame(30, $decision->daysLeft);
        $this->assertFalse($decision->canWrite());
        $this->assertTrue($decision->canRead());
        $this->assertSame(423, $decision->deniedStatus(isWrite: true));
        $this->assertNull($decision->deniedStatus(isWrite: false));
        $this->assertSame('subscription.state_messages.read_only', $decision->messageKey());
        $this->assertSame(['days' => 30], $decision->messageParameters());
    }

    public function test_one_second_before_trial_end_is_still_trial(): void
    {
        $decision = $this->policy()->evaluate(new TenantLifecycleSnapshot(
            status: TenantStatus::Trial,
            trialEndsAt: $this->at('2026-10-15 00:00:00'),
        ), $this->at('2026-10-14 23:59:59'));

        $this->assertSame(TenantStatus::Trial, $decision->effectiveStatus);
        $this->assertSame(1, $decision->daysLeft);
    }

    public function test_trial_never_swept_catches_up_to_archived_in_steps(): void
    {
        $decision = $this->policy()->evaluate(new TenantLifecycleSnapshot(
            status: TenantStatus::Trial,
            trialEndsAt: $this->at('2026-01-01 00:00:00'),
        ), $this->at('2026-10-01 00:00:00'));

        $this->assertSame(TenantStatus::Archived, $decision->effectiveStatus);
        $this->assertSame(TenantAccessLevel::Blocked, $decision->access());
        $this->assertSame(403, $decision->deniedStatus(isWrite: false));
        $this->assertNull($decision->nextStatus);
        $this->assertNull($decision->nextTransitionAt);
        $this->assertNull($decision->daysLeft);

        $this->assertEquals([
            new LifecycleTransitionStep(TenantStatus::Trial, TenantStatus::ReadOnly, $this->at('2026-01-01 00:00:00')),
            new LifecycleTransitionStep(TenantStatus::ReadOnly, TenantStatus::Suspended, $this->at('2026-01-31 00:00:00')),
            new LifecycleTransitionStep(TenantStatus::Suspended, TenantStatus::Archived, $this->at('2026-05-01 00:00:00')),
        ], $decision->pendingTransitions);

        foreach ($decision->pendingTransitions as $step) {
            $this->assertTrue($step->from->canTransitionTo($step->to, TenantLifecycleActor::System), 'Every derived step must be a legal System transition.');
        }
    }

    public function test_paid_subscription_end_goes_to_past_due_grace_with_full_access(): void
    {
        $decision = $this->policy()->evaluate(new TenantLifecycleSnapshot(
            status: TenantStatus::Active,
            subscriptionEndsAt: $this->at('2026-10-01 00:00:00'),
        ), $this->at('2026-10-03 00:00:00'));

        $this->assertSame(TenantStatus::PastDue, $decision->effectiveStatus);
        $this->assertEquals($this->at('2026-10-01 00:00:00'), $decision->effectiveSince);
        $this->assertTrue($decision->canWrite(), 'Grace keeps the shop working.');
        $this->assertSame(TenantStatus::ReadOnly, $decision->nextStatus);
        $this->assertEquals($this->at('2026-10-08 00:00:00'), $decision->nextTransitionAt);
        $this->assertSame(5, $decision->daysLeft);
    }

    public function test_paid_path_full_catch_up_is_four_steps(): void
    {
        $decision = $this->policy()->evaluate(new TenantLifecycleSnapshot(
            status: TenantStatus::Active,
            subscriptionEndsAt: $this->at('2026-01-01 00:00:00'),
        ), $this->at('2026-12-31 00:00:00'));

        $this->assertSame(TenantStatus::Archived, $decision->effectiveStatus);
        $this->assertCount(TenantLifecyclePolicy::MAX_CATCH_UP_STEPS, $decision->pendingTransitions);
        $this->assertSame(
            ['past_due', 'read_only', 'suspended', 'archived'],
            array_map(static fn (LifecycleTransitionStep $s): string => $s->to->value, $decision->pendingTransitions),
        );
        $this->assertEquals($this->at('2026-01-08 00:00:00'), $decision->pendingTransitions[1]->at);
        $this->assertEquals($this->at('2026-02-07 00:00:00'), $decision->pendingTransitions[2]->at);
        $this->assertEquals($this->at('2026-05-08 00:00:00'), $decision->pendingTransitions[3]->at);
    }

    public function test_active_without_end_date_never_expires(): void
    {
        $decision = $this->policy()->evaluate(new TenantLifecycleSnapshot(status: TenantStatus::Active), $this->at('2030-01-01'));

        $this->assertSame(TenantStatus::Active, $decision->effectiveStatus);
        $this->assertNull($decision->nextTransitionAt);
        $this->assertNull($decision->daysLeft);
    }

    public function test_stored_past_due_uses_grace_ends_at_when_set(): void
    {
        $decision = $this->policy()->evaluate(new TenantLifecycleSnapshot(
            status: TenantStatus::PastDue,
            statusChangedAt: $this->at('2026-10-01 00:00:00'),
            subscriptionEndsAt: $this->at('2026-10-01 00:00:00'),
            graceEndsAt: $this->at('2026-10-05 00:00:00'),
        ), $this->at('2026-10-04 00:00:00'));

        $this->assertSame(TenantStatus::PastDue, $decision->effectiveStatus);
        $this->assertEquals($this->at('2026-10-05 00:00:00'), $decision->nextTransitionAt);
        $this->assertSame(1, $decision->daysLeft);
    }

    public function test_stale_grace_ends_at_from_an_earlier_cycle_is_ignored(): void
    {
        // A grace date older than the moment past_due started belongs to a previous cycle.
        $decision = $this->policy()->evaluate(new TenantLifecycleSnapshot(
            status: TenantStatus::Active,
            subscriptionEndsAt: $this->at('2026-10-01 00:00:00'),
            graceEndsAt: $this->at('2025-06-01 00:00:00'),
        ), $this->at('2026-10-02 00:00:00'));

        $this->assertSame(TenantStatus::PastDue, $decision->effectiveStatus);
        $this->assertEquals($this->at('2026-10-08 00:00:00'), $decision->nextTransitionAt);
    }

    public function test_stored_past_due_without_any_anchor_never_moves_automatically(): void
    {
        $decision = $this->policy()->evaluate(new TenantLifecycleSnapshot(status: TenantStatus::PastDue), $this->at('2030-01-01'));

        $this->assertSame(TenantStatus::PastDue, $decision->effectiveStatus);
        $this->assertNull($decision->nextTransitionAt);
    }

    public function test_stored_read_only_is_suspended_thirty_days_after_it_started(): void
    {
        $snapshot = new TenantLifecycleSnapshot(
            status: TenantStatus::ReadOnly,
            statusChangedAt: $this->at('2026-09-01 00:00:00'),
        );

        $this->assertSame(TenantStatus::ReadOnly, $this->policy()->evaluate($snapshot, $this->at('2026-09-30 23:59:59'))->effectiveStatus);

        $after = $this->policy()->evaluate($snapshot, $this->at('2026-10-01 00:00:00'));
        $this->assertSame(TenantStatus::Suspended, $after->effectiveStatus);
        $this->assertSame(403, $after->deniedStatus(isWrite: false));
        $this->assertEquals($this->at('2026-12-30 00:00:00'), $after->nextTransitionAt);
        $this->assertSame(90, $after->daysLeft);
    }

    public function test_stored_read_only_without_status_changed_at_stays_read_only(): void
    {
        // Safe default: without an anchor we never lock a shop out automatically.
        $decision = $this->policy()->evaluate(new TenantLifecycleSnapshot(status: TenantStatus::ReadOnly), $this->at('2030-01-01'));

        $this->assertSame(TenantStatus::ReadOnly, $decision->effectiveStatus);
        $this->assertNull($decision->nextTransitionAt);
    }

    public function test_cancelled_is_archived_after_retention(): void
    {
        $snapshot = new TenantLifecycleSnapshot(
            status: TenantStatus::Cancelled,
            statusChangedAt: $this->at('2026-01-01 00:00:00'),
        );

        $this->assertSame(TenantStatus::Cancelled, $this->policy()->evaluate($snapshot, $this->at('2026-03-31 23:59:59'))->effectiveStatus);
        $this->assertSame(TenantStatus::Archived, $this->policy()->evaluate($snapshot, $this->at('2026-04-01 00:00:00'))->effectiveStatus);
    }

    public function test_null_retention_disables_automatic_archiving(): void
    {
        $decision = $this->policy(retention: null)->evaluate(new TenantLifecycleSnapshot(
            status: TenantStatus::Suspended,
            statusChangedAt: $this->at('2020-01-01 00:00:00'),
        ), $this->at('2030-01-01'));

        $this->assertSame(TenantStatus::Suspended, $decision->effectiveStatus);
        $this->assertNull($decision->nextTransitionAt);
    }

    public function test_violation_suspension_is_never_archived_automatically(): void
    {
        $decision = $this->policy()->evaluate(new TenantLifecycleSnapshot(
            status: TenantStatus::Suspended,
            statusChangedAt: $this->at('2020-01-01 00:00:00'),
            suspensionReason: TenantSuspensionReason::Violation,
        ), $this->at('2030-01-01'));

        $this->assertSame(TenantStatus::Suspended, $decision->effectiveStatus);
        $this->assertSame([], $decision->pendingTransitions);
        $this->assertNull($decision->nextTransitionAt);
    }

    /**
     * @return iterable<string, array{0: TenantSuspensionReason}>
     */
    public static function archivableSuspensionReasons(): iterable
    {
        foreach (TenantSuspensionReason::cases() as $reason) {
            if (! $reason->blocksAutomaticArchive()) {
                yield $reason->value => [$reason];
            }
        }
    }

    #[DataProvider('archivableSuspensionReasons')]
    public function test_other_suspension_reasons_are_archived_after_retention(TenantSuspensionReason $reason): void
    {
        $snapshot = new TenantLifecycleSnapshot(
            status: TenantStatus::Suspended,
            statusChangedAt: $this->at('2026-01-01 00:00:00'),
            suspensionReason: $reason,
        );

        $this->assertSame(TenantStatus::Suspended, $this->policy()->evaluate($snapshot, $this->at('2026-03-31 23:59:59'))->effectiveStatus);
        $this->assertSame(TenantStatus::Archived, $this->policy()->evaluate($snapshot, $this->at('2026-04-01 00:00:00'))->effectiveStatus);
    }

    public function test_cancelled_is_archived_after_retention_even_with_a_violation_reason(): void
    {
        $snapshot = new TenantLifecycleSnapshot(
            status: TenantStatus::Cancelled,
            statusChangedAt: $this->at('2026-01-01 00:00:00'),
            suspensionReason: TenantSuspensionReason::Violation,
        );

        $this->assertSame(TenantStatus::Archived, $this->policy()->evaluate($snapshot, $this->at('2026-04-01 00:00:00'))->effectiveStatus);
    }

    public function test_archived_is_terminal_for_evaluation(): void
    {
        $decision = $this->policy()->evaluate(new TenantLifecycleSnapshot(
            status: TenantStatus::Archived,
            statusChangedAt: $this->at('2020-01-01'),
        ), $this->at('2030-01-01'));

        $this->assertSame(TenantStatus::Archived, $decision->effectiveStatus);
        $this->assertSame([], $decision->pendingTransitions);
        $this->assertNull($decision->nextStatus);
    }

    public function test_evaluation_depends_only_on_the_clock_passed_in(): void
    {
        $snapshot = new TenantLifecycleSnapshot(status: TenantStatus::Trial, trialEndsAt: $this->at('2026-10-15 00:00:00'));

        CarbonImmutable::setTestNow($this->at('2099-01-01'));

        try {
            $decision = $this->policy()->evaluate($snapshot, $this->at('2026-10-10 00:00:00'));
        } finally {
            CarbonImmutable::setTestNow();
        }

        $this->assertSame(TenantStatus::Trial, $decision->effectiveStatus);
        $this->assertSame(5, $decision->daysLeft);
    }

    public function test_policy_source_has_no_clock_or_io_calls(): void
    {
        $dir = dirname(__DIR__, 3).'/app/Support/Tenancy';
        $files = ['TenantLifecyclePolicy.php', 'LifecycleDecision.php', 'TenantLifecycleSnapshot.php', 'LifecycleTransitionStep.php', 'TrialExtensionDecision.php'];

        foreach ($files as $file) {
            $this->assertFileExists($dir.'/'.$file);
            $source = (string) file_get_contents($dir.'/'.$file);
            $code = (string) preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $source);

            foreach (['now(', 'today(', '::now', 'config(', 'DB::', 'Cache::', 'cache(', 'tenant(', 'tenancy(', 'request(', 'Http::', 'Log::', 'date(', 'time('] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $code, "{$file} must stay pure: found `{$forbidden}`.");
            }
        }
    }

    // ------------------------------------------------------------------
    // Trial extension (+7 days, once, never-paid only)
    // ------------------------------------------------------------------

    public function test_running_trial_can_be_extended_once_from_its_end_date(): void
    {
        $decision = $this->policy()->extendTrial(new TenantLifecycleSnapshot(
            status: TenantStatus::Trial,
            trialEndsAt: $this->at('2026-10-15 00:00:00'),
        ), $this->at('2026-10-10 00:00:00'));

        $this->assertTrue($decision->allowed);
        $this->assertNull($decision->reasonKey);
        $this->assertEquals($this->at('2026-10-22 00:00:00'), $decision->newTrialEndsAt);
        $this->assertSame(TenantStatus::Trial, $decision->targetStatus);
    }

    public function test_read_only_never_paid_trial_can_be_extended_from_now(): void
    {
        $decision = $this->policy()->extendTrial(new TenantLifecycleSnapshot(
            status: TenantStatus::Trial,
            trialEndsAt: $this->at('2026-10-01 00:00:00'),
        ), $this->at('2026-10-10 08:00:00'));

        $this->assertTrue($decision->allowed);
        $this->assertEquals($this->at('2026-10-17 08:00:00'), $decision->newTrialEndsAt);
        $this->assertSame(TenantStatus::Trial, $decision->targetStatus);
        $this->assertTrue(TenantStatus::ReadOnly->canTransitionTo($decision->targetStatus, TenantLifecycleActor::SuperAdmin));
    }

    public function test_trial_extension_is_refused_a_second_time(): void
    {
        $decision = $this->policy()->extendTrial(new TenantLifecycleSnapshot(
            status: TenantStatus::Trial,
            trialEndsAt: $this->at('2026-10-15 00:00:00'),
            trialExtendedAt: $this->at('2026-10-08 00:00:00'),
        ), $this->at('2026-10-10 00:00:00'));

        $this->assertFalse($decision->allowed);
        $this->assertSame('subscription.trial_extension.already_used', $decision->reasonKey);
        $this->assertNull($decision->newTrialEndsAt);
        $this->assertNull($decision->targetStatus);
    }

    public function test_trial_extension_is_refused_for_a_tenant_that_has_paid(): void
    {
        $decision = $this->policy()->extendTrial(new TenantLifecycleSnapshot(
            status: TenantStatus::ReadOnly,
            statusChangedAt: $this->at('2026-10-05 00:00:00'),
            hasEverPaid: true,
        ), $this->at('2026-10-10 00:00:00'));

        $this->assertFalse($decision->allowed);
        $this->assertSame('subscription.trial_extension.already_paid', $decision->reasonKey);
    }

    /**
     * @return array<string, array{0: TenantStatus}>
     */
    public static function nonExtendableStatuses(): array
    {
        return [
            'active' => [TenantStatus::Active],
            'past_due' => [TenantStatus::PastDue],
            'suspended' => [TenantStatus::Suspended],
            'cancelled' => [TenantStatus::Cancelled],
            'archived' => [TenantStatus::Archived],
        ];
    }

    #[DataProvider('nonExtendableStatuses')]
    public function test_trial_extension_is_refused_outside_trial_and_read_only(TenantStatus $status): void
    {
        $decision = $this->policy()->extendTrial(new TenantLifecycleSnapshot(
            status: $status,
            statusChangedAt: $this->at('2026-10-09 00:00:00'),
        ), $this->at('2026-10-10 00:00:00'));

        $this->assertFalse($decision->allowed);
        $this->assertSame('subscription.trial_extension.not_eligible', $decision->reasonKey);
    }

    public function test_open_ended_trial_cannot_be_extended(): void
    {
        $decision = $this->policy()->extendTrial(new TenantLifecycleSnapshot(status: TenantStatus::Trial), $this->at('2026-10-10'));

        $this->assertFalse($decision->allowed);
        $this->assertSame('subscription.trial_extension.not_eligible', $decision->reasonKey);
    }

    public function test_misconfigured_durations_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TenantLifecyclePolicy(pastDueGraceDays: 7, readOnlyDays: 0, retentionDays: 90, trialExtensionDays: 7);
    }

    public function test_decision_is_a_value_object(): void
    {
        $decision = $this->policy()->evaluate(new TenantLifecycleSnapshot(status: TenantStatus::Active), $this->at('2026-10-10'));

        $this->assertInstanceOf(LifecycleDecision::class, $decision);
        $this->assertTrue((new \ReflectionClass(LifecycleDecision::class))->isReadOnly());
        $this->assertTrue((new \ReflectionClass(TenantLifecycleSnapshot::class))->isReadOnly());
    }
}
