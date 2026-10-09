<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\SubscriptionAddonStatus;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Addon;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * CTO W1 Q3 [2026-10-09]: an add-on line is active only while its PARENT subscription is
 * in force (status `active` and ends_at in the future) and the line itself has started
 * (starts_at NOT NULL and reached). activeFor() and isActive() must agree.
 *
 * CTO decision 2026-10-09 (W2 batch 2): during `past_due` (grace) the lines KEEP working,
 * even though the subscription term (ends_at) is over. They stop when the TENANT becomes
 * read_only / suspended / cancelled / archived; that gate lives in TenantEntitlementService
 * (TenantEntitlementServiceTest).
 */
#[Group('billing')]
#[Group('mysql')]
final class SubscriptionAddonParentRuleTest extends TenantTestCase
{
    /**
     * @return iterable<string, array{0: SubscriptionStatus}>
     */
    public static function inactiveParentStatuses(): iterable
    {
        yield 'trialing' => [SubscriptionStatus::Trialing];
        yield 'pending_payment' => [SubscriptionStatus::PendingPayment];
        yield 'cancelled' => [SubscriptionStatus::Cancelled];
        yield 'expired' => [SubscriptionStatus::Expired];
    }

    public function test_an_active_line_of_an_active_subscription_counts(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant(SubscriptionStatus::Active);
        $line = $this->line($subscription, now()->subDay());

        $this->assertSame([$line->id], $this->activeIds($tenant));
        $this->assertTrue($line->fresh()?->isActive());
    }

    #[DataProvider('inactiveParentStatuses')]
    public function test_a_line_never_counts_while_its_subscription_is_not_active(SubscriptionStatus $status): void
    {
        [$tenant, $subscription] = $this->subscribedTenant($status);
        $line = $this->line($subscription, now()->subDay());

        $this->assertSame([], $this->activeIds($tenant));
        $this->assertFalse($line->fresh()?->isActive());
    }

    public function test_a_line_stops_counting_when_the_subscription_term_has_ended(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant(SubscriptionStatus::Active, now()->addDays(10));
        $line = $this->line($subscription, now()->subDay());

        $this->assertSame([$line->id], $this->activeIds($tenant));

        // Ten days later the subscription term is over even if no sweep changed its status yet.
        $later = now()->addDays(10);
        $this->assertSame([], SubscriptionAddon::query()->activeFor($tenant, $later)->pluck('id')->all());
        $this->assertFalse($line->fresh()?->isActive($later));
    }

    public function test_a_line_without_starts_at_never_counts(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant(SubscriptionStatus::Active);
        $line = $this->line($subscription, null);

        $this->assertSame([], $this->activeIds($tenant));
        $this->assertFalse($line->fresh()?->isActive());
    }

    public function test_a_line_keeps_counting_while_its_subscription_is_past_due(): void
    {
        // Grace period: the term has ended (ends_at in the past) and the status is past_due.
        [$tenant, $subscription] = $this->subscribedTenant(SubscriptionStatus::PastDue, now()->subDays(2));
        $line = $this->line($subscription, now()->subMonth());

        $this->assertSame([$line->id], $this->activeIds($tenant));
        $line->refresh();
        $this->assertTrue($line->isActive());
        $this->assertTrue($line->isActive(now()->addDays(5)), 'The subscription dates do not end the grace; the tenant status does.');
    }

    public function test_a_past_due_line_still_needs_to_have_started_and_not_ended(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant(SubscriptionStatus::PastDue, now()->subDay());
        $notStarted = $this->line($subscription, now()->addDay());
        $ended = $this->line($subscription, now()->subMonth());
        $ended->update(['ends_at' => now()->subHour()]);

        $this->assertSame([], $this->activeIds($tenant));
        $this->assertFalse($notStarted->fresh()?->isActive());
        $this->assertFalse($ended->fresh()?->isActive());
    }

    public function test_a_past_due_subscription_that_expires_stops_its_lines(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant(SubscriptionStatus::PastDue, now()->subDay());
        $line = $this->line($subscription, now()->subMonth());
        $this->assertSame([$line->id], $this->activeIds($tenant));

        $subscription->update(['status' => SubscriptionStatus::Expired]);

        $this->assertSame([], $this->activeIds($tenant));
        $this->assertFalse($line->fresh()?->isActive());
    }

    public function test_reactivating_the_subscription_brings_its_lines_back(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant(SubscriptionStatus::PendingPayment);
        $line = $this->line($subscription, now()->subDay());
        $this->assertSame([], $this->activeIds($tenant));

        $subscription->update(['status' => SubscriptionStatus::Active]);

        $this->assertSame([$line->id], $this->activeIds($tenant));
    }

    public function test_only_the_active_subscription_lines_count_when_a_tenant_has_several(): void
    {
        [$tenant, $current] = $this->subscribedTenant(SubscriptionStatus::Active);
        $old = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $current->plan_id,
            'billing_cycle' => BillingCycle::Monthly,
            'status' => SubscriptionStatus::Cancelled,
            'amount' => '449.000',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->addMonth(),
        ]);

        $kept = $this->line($current, now()->subDay());
        $this->line($old, now()->subMonth());

        $this->assertSame([$kept->id], $this->activeIds($tenant));
    }

    /**
     * @return list<int>
     */
    private function activeIds(Tenant $tenant): array
    {
        return SubscriptionAddon::query()->activeFor($tenant)->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @return array{0: Tenant, 1: Subscription}
     */
    private function subscribedTenant(SubscriptionStatus $status, ?CarbonInterface $endsAt = null): array
    {
        $tenant = $this->createTenant();
        $plan = Plan::query()->create([
            'name' => 'plan-'.$tenant->id,
            'slug' => 'plan-'.$tenant->id,
            'price_monthly' => '449.000',
            'price_yearly' => '4490.000',
            'features' => [],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => BillingCycle::Monthly,
            'status' => $status,
            'amount' => '449.000',
            'starts_at' => now()->subMonth(),
            'ends_at' => $endsAt ?? now()->addMonth(),
        ]);

        return [$tenant, $subscription];
    }

    private function line(Subscription $subscription, ?CarbonInterface $startsAt): SubscriptionAddon
    {
        $addon = Addon::query()->firstOrCreate(
            ['key' => 'addon.user'],
            ['name_key' => 'plans.addons.user.name', 'unit_price' => '79.000'],
        );

        return SubscriptionAddon::query()->create([
            'subscription_id' => $subscription->id,
            'addon_id' => $addon->id,
            'quantity' => 1,
            'unit_price' => '79.000',
            'status' => SubscriptionAddonStatus::Active,
            'starts_at' => $startsAt,
            'ends_at' => null,
        ]);
    }
}
