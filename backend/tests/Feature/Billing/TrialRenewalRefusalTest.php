<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\BillingInvoice;
use PHPUnit\Framework\Attributes\Group;

/**
 * #230: a shop in its trial (or on a permanent free plan) cannot request a renewal invoice; the
 * subscription API says so with `can_request_renewal`, and a trial is never told its plan is
 * "permanently free".
 */
#[Group('billing')]
final class TrialRenewalRefusalTest extends BillingTestCase
{
    private const SUBSCRIPTION = '/api/v1/billing/subscription';

    private const RENEWAL = '/api/v1/billing/renewal-invoice';

    public function test_a_trial_shop_cannot_renew_and_the_message_is_the_trial_one(): void
    {
        // Even when the trial plan itself is flagged free: a trial is not "permanently free".
        $tenant = $this->tenantOn($this->plan(['is_free' => true]), ['status' => 'trial', 'trial_ends_at' => now()->addDays(5), 'subscription_ends_at' => null]);

        $this->getJson(self::SUBSCRIPTION, $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.subscription.can_request_renewal', false);

        $this->postJson(self::RENEWAL, ['cycle' => 'monthly'], $this->tenantHeaders($tenant))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'billing.renewal_not_for_trial')
            ->assertJsonPath('message', __('billing.errors.renewal_not_for_trial'));

        $this->assertNotSame(__('billing.errors.free_plan_not_billable'), __('billing.errors.renewal_not_for_trial'));
        $this->assertSame(0, BillingInvoice::query()->count());
    }

    public function test_a_trial_on_a_paid_plan_is_also_pointed_to_a_plan_change(): void
    {
        $tenant = $this->tenantOn($this->plan(), ['status' => 'trial', 'trial_ends_at' => now()->addDays(5), 'subscription_ends_at' => null]);

        $this->getJson(self::SUBSCRIPTION, $this->tenantHeaders($tenant))
            ->assertJsonPath('data.subscription.can_request_renewal', false);
        $this->postJson(self::RENEWAL, ['cycle' => 'yearly'], $this->tenantHeaders($tenant))
            ->assertStatus(422)->assertJsonPath('error_code', 'billing.renewal_not_for_trial');
    }

    public function test_a_permanent_free_shop_cannot_renew(): void
    {
        $tenant = $this->tenantOn($this->plan(['is_free' => true]), ['status' => 'active', 'subscription_ends_at' => null]);

        $this->getJson(self::SUBSCRIPTION, $this->tenantHeaders($tenant))
            ->assertJsonPath('data.subscription.can_request_renewal', false);
        $this->postJson(self::RENEWAL, ['cycle' => 'monthly'], $this->tenantHeaders($tenant))
            ->assertStatus(422)->assertJsonPath('error_code', 'billing.free_plan_not_billable');
    }

    public function test_a_paying_shop_can_renew(): void
    {
        $tenant = $this->tenantOn($this->plan(), ['subscription_ends_at' => now()->addDays(10)]);

        $this->getJson(self::SUBSCRIPTION, $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.subscription.can_request_renewal', true);
        $this->postJson(self::RENEWAL, ['cycle' => 'monthly'], $this->tenantHeaders($tenant))->assertCreated();
    }

    public function test_a_read_only_shop_after_an_ended_trial_can_still_renew(): void
    {
        $tenant = $this->tenantOn($this->plan(), ['status' => 'read_only', 'status_changed_at' => now()->subDay()]);

        $this->getJson(self::SUBSCRIPTION, $this->tenantHeaders($tenant))
            ->assertJsonPath('data.subscription.can_request_renewal', true);
    }
}
