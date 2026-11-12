<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\SubscriptionAddonStatus;
use App\Enums\LimitResource;
use App\Models\Addon;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Services\Branding\PlatformBranding;
use PHPUnit\Framework\Attributes\Group;

/**
 * PRD-04: GET /api/v1/billing/subscription: plan, status, days left, usage against limits,
 * add-ons, open invoices, payment instructions and support contacts.
 */
#[Group('billing')]
final class SubscriptionUsageApiTest extends BillingTestCase
{
    public function test_the_payload_has_the_contracted_shape(): void
    {
        $plan = $this->plan(['name' => 'Growth']);
        $tenant = $this->tenantOn($plan, ['subscription_ends_at' => now()->addDays(10)]);

        $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.plan.id', $plan->id)
            ->assertJsonPath('data.plan.name', 'Growth')
            ->assertJsonPath('data.plan.code', $plan->slug)
            ->assertJsonPath('data.tenant_status.value', 'active')
            ->assertJsonPath('data.tenant_status.access', 'full')
            ->assertJsonStructure(['data' => [
                'plan' => ['id', 'name', 'code'],
                'subscription' => ['status', 'billing_cycle', 'starts_at', 'ends_at', 'trial_ends_at', 'days_left', 'is_founder'],
                'tenant_status' => ['value', 'label', 'access'],
                'usage' => [['resource', 'label', 'used', 'limit', 'percent']],
                'addons',
                'open_invoices_count',
                'payment_instructions' => ['instapay_address', 'vodafone_cash_number', 'account_name', 'payment_note', 'tax_rate'],
                'support' => ['phone', 'email'],
            ]])
            ->assertJsonMissing(['resource' => LimitResource::InvoicesMonth->value])
            ->assertJsonMissing(['resource' => LimitResource::StorageMb->value]);

        $days = $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))->json('data.subscription.days_left');
        $this->assertContains($days, [10, 11]);
    }

    public function test_usage_is_counted_against_the_plan_limits(): void
    {
        $plan = $this->plan(['max_users' => 4, 'max_stores' => 1, 'max_items' => null]);
        $tenant = $this->tenantOn($plan);
        $this->createTenantUser($tenant);

        $usage = collect($this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))->json('data.usage'))->keyBy('resource');

        $this->assertSame(2, $usage['users']['used']);
        $this->assertSame(4, $usage['users']['limit']);
        $this->assertSame(50, $usage['users']['percent']);

        $this->assertSame(1, $usage['stores']['used']);
        $this->assertSame(1, $usage['stores']['limit']);
        $this->assertSame(100, $usage['stores']['percent']);

        // PLAN-01: an unlimited resource has no usage row; the unenforced limits are never shown.
        $this->assertArrayNotHasKey('items', $usage->all());
        $this->assertArrayNotHasKey('invoices_month', $usage->all());
        $this->assertArrayNotHasKey('storage_mb', $usage->all());
    }

    public function test_active_addons_extend_the_limit_and_are_listed(): void
    {
        $plan = $this->plan(['max_users' => 2]);
        $tenant = $this->tenantOn($plan);

        $addon = Addon::query()->create([
            'key' => 'extra_user_test', 'name_key' => 'billing.limit_resources.users', 'type' => 'recurring',
            'bundled_limits' => ['users' => 3], 'unit_price' => '50.000', 'is_active' => true, 'is_public' => true, 'sort_order' => 1,
        ]);
        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->getKey(), 'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'status' => 'active',
            'amount' => '100.000', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(),
        ]);
        SubscriptionAddon::query()->create([
            'subscription_id' => $subscription->getKey(), 'addon_id' => $addon->getKey(), 'quantity' => 2, 'unit_price' => '50.000',
            'billing_cycle' => 'monthly', 'status' => SubscriptionAddonStatus::Active, 'starts_at' => now()->subDay(),
        ]);

        $data = $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))->assertOk()->json('data');

        $users = collect($data['usage'])->firstWhere('resource', 'users');
        $this->assertSame(8, $users['limit']); // 2 + 3 x 2
        $this->assertSame('extra_user_test', $data['addons'][0]['key']);
        $this->assertSame(2, $data['addons'][0]['quantity']);
    }

    public function test_open_invoices_and_payment_instructions_come_from_the_platform(): void
    {
        $tenant = $this->createTenant();
        $other = $this->createTenant();
        $this->pendingInvoice($tenant);
        $this->pendingInvoice($tenant, ['status' => 'paid']);
        $this->pendingInvoice($other);

        $this->endTenancy();
        app(PlatformBranding::class)->update([
            'billing_instapay_address' => 'dummy@instapay',
            'billing_vodafone_cash_number' => '01000000123',
            'billing_account_name' => 'Dummy Account',
            'billing_payment_note' => 'Send the exact amount.',
            'billing_tax_rate' => '14',
            'support_phone' => '01000000456',
            'support_email' => 'support@example.test',
        ]);

        $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.open_invoices_count', 1)
            ->assertJsonPath('data.payment_instructions.instapay_address', 'dummy@instapay')
            ->assertJsonPath('data.payment_instructions.vodafone_cash_number', '01000000123')
            ->assertJsonPath('data.payment_instructions.account_name', 'Dummy Account')
            ->assertJsonPath('data.payment_instructions.payment_note', 'Send the exact amount.')
            ->assertJsonPath('data.payment_instructions.tax_rate', '14.000')
            ->assertJsonPath('data.support.phone', '01000000456')
            ->assertJsonPath('data.support.email', 'support@example.test');
    }

    public function test_a_trial_shop_counts_down_to_the_trial_end(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan, ['status' => 'trial', 'trial_ends_at' => now()->addDays(4), 'subscription_ends_at' => null]);

        $response = $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))->assertOk();

        $response->assertJsonPath('data.tenant_status.value', 'trial');
        $this->assertContains($response->json('data.subscription.days_left'), [4, 5]);
    }

    public function test_it_is_401_for_guests_and_403_without_the_permission(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createTenantUser($tenant, null, ['items.view']);

        $this->getJson('/api/v1/billing/subscription', $this->tenantGuestHeaders($tenant))->assertUnauthorized();
        $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant, $user))->assertForbidden();
    }

    public function test_one_shop_never_sees_another_shops_numbers(): void
    {
        $a = $this->tenantOn($this->plan(['max_users' => 3, 'name' => 'Plan A']));
        $b = $this->tenantOn($this->plan(['max_users' => 9, 'name' => 'Plan B']));

        $this->assertSame('Plan A', $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($a))->json('data.plan.name'));
        $this->assertSame('Plan B', $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($b))->json('data.plan.name'));
    }
}
