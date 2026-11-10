<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\CentralAuditLog;
use App\Models\PlanChangeRequest;
use PHPUnit\Framework\Attributes\Group;

/**
 * The shop's side of "plan change / add-on through support": create, list, cancel.
 * 201 / 422 / 401 / 403, tenant isolation and the read-only allowance.
 */
#[Group('billing')]
final class PlanChangeRequestTenantApiTest extends PlanChangeTestCase
{
    private const URL = '/api/v1/billing/plan-change-requests';

    public function test_a_shop_requests_a_plan_change_and_sees_it_in_its_list(): void
    {
        $small = $this->plan(['name' => 'Small']);
        $big = $this->plan(['name' => 'Big']);
        $tenant = $this->tenantOn($small);
        $headers = $this->tenantHeaders($tenant);

        $this->postJson(self::URL, ['kind' => 'plan_change', 'requested_plan_code' => $big->slug, 'billing_cycle' => 'yearly', 'notes' => 'We opened a 2nd branch'], $headers)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.kind.value', 'plan_change')
            ->assertJsonPath('data.status.value', 'pending')
            ->assertJsonPath('data.billing_cycle.value', 'yearly')
            ->assertJsonPath('data.current_plan.name', 'Small')
            ->assertJsonPath('data.requested_plan.code', $big->slug)
            ->assertJsonPath('data.notes', 'We opened a 2nd branch')
            ->assertJsonPath('data.invoice', null)
            ->assertJsonMissingPath('data.tenant');

        $this->getJson(self::URL, $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.requested_plan.name', 'Big');

        $row = PlanChangeRequest::query()->firstOrFail();
        $this->assertSame($tenant->getKey(), $row->tenant_id);
        $this->assertNotNull($row->requested_by_name);
        $this->assertSame(1, CentralAuditLog::query()->where('event', 'plan_change_requested')->count());
    }

    public function test_only_one_pending_request_per_kind(): void
    {
        $small = $this->plan();
        $big = $this->plan();
        $tenant = $this->subscribedTenant($small);
        $addon = $this->addonOn($small);
        $headers = $this->tenantHeaders($tenant);
        $payload = ['kind' => 'plan_change', 'requested_plan_code' => $big->slug, 'billing_cycle' => 'monthly'];

        $this->postJson(self::URL, $payload, $headers)->assertCreated();
        $this->postJson(self::URL, $payload, $headers)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'billing.request_already_pending')
            ->assertJsonPath('message', __('billing.errors.request_already_pending'));

        // A different kind is independent, and a second add-on request is again blocked.
        $addonPayload = ['kind' => 'addon', 'addon_key' => $addon->key, 'quantity' => 2, 'billing_cycle' => 'monthly'];
        $this->postJson(self::URL, $addonPayload, $headers)->assertCreated()->assertJsonPath('data.quantity', 2);
        $this->postJson(self::URL, $addonPayload, $headers)->assertStatus(422)->assertJsonPath('error_code', 'billing.request_already_pending');

        $this->assertSame(2, PlanChangeRequest::query()->count());
    }

    public function test_the_same_or_an_unavailable_plan_is_refused(): void
    {
        $current = $this->plan();
        $hidden = $this->plan(['is_public' => false]);
        $inactive = $this->plan(['is_active' => false]);
        $tenant = $this->tenantOn($current);
        $headers = $this->tenantHeaders($tenant);

        $this->postJson(self::URL, ['kind' => 'plan_change', 'requested_plan_code' => $current->slug, 'billing_cycle' => 'monthly'], $headers)
            ->assertStatus(422)->assertJsonPath('error_code', 'billing.request_same_plan');

        foreach ([$hidden->slug, $inactive->slug, 'no-such-plan'] as $code) {
            $this->postJson(self::URL, ['kind' => 'plan_change', 'requested_plan_code' => $code, 'billing_cycle' => 'monthly'], $headers)
                ->assertStatus(422)->assertJsonPath('error_code', 'billing.request_plan_unavailable');
        }

        $this->assertSame(0, PlanChangeRequest::query()->count());
    }

    public function test_an_addon_must_be_offered_on_the_plan_and_the_shop_must_hold_a_subscription(): void
    {
        $plan = $this->plan();
        $other = $this->plan();
        $offered = $this->addonOn($plan);
        $elsewhere = $this->addonOn($other, 'elsewhere');
        $tenant = $this->tenantOn($plan);
        $headers = $this->tenantHeaders($tenant);

        // No subscription row yet (trial / never paid).
        $this->postJson(self::URL, ['kind' => 'addon', 'addon_key' => $offered->key, 'billing_cycle' => 'monthly'], $headers)
            ->assertStatus(422)->assertJsonPath('error_code', 'billing.addon_needs_subscription');

        $expired = $this->subscribedTenant($plan, -2);
        $this->postJson(self::URL, ['kind' => 'addon', 'addon_key' => $offered->key, 'billing_cycle' => 'monthly'], $this->tenantHeaders($expired))
            ->assertStatus(422)->assertJsonPath('error_code', 'billing.addon_needs_active_subscription');

        $subscribed = $this->subscribedTenant($plan);
        $subscribedHeaders = $this->tenantHeaders($subscribed);
        $this->postJson(self::URL, ['kind' => 'addon', 'addon_key' => $elsewhere->key, 'billing_cycle' => 'monthly'], $subscribedHeaders)
            ->assertStatus(422)->assertJsonPath('error_code', 'billing.request_addon_unavailable');
        $this->postJson(self::URL, ['kind' => 'addon', 'addon_key' => 'nope', 'billing_cycle' => 'monthly'], $subscribedHeaders)
            ->assertStatus(422)->assertJsonPath('error_code', 'billing.request_addon_unavailable');
        $this->postJson(self::URL, ['kind' => 'addon', 'addon_key' => $offered->key, 'billing_cycle' => 'monthly'], $subscribedHeaders)
            ->assertCreated()
            ->assertJsonPath('data.addon.key', $offered->key)
            ->assertJsonPath('data.requested_plan', null);
    }

    public function test_validation_errors_are_422(): void
    {
        $tenant = $this->tenantOn($this->plan());
        $headers = $this->tenantHeaders($tenant);

        $this->postJson(self::URL, [], $headers)->assertStatus(422)->assertJsonValidationErrors(['kind', 'billing_cycle']);
        $this->postJson(self::URL, ['kind' => 'plan_change', 'billing_cycle' => 'monthly'], $headers)->assertStatus(422)->assertJsonValidationErrors(['requested_plan_code']);
        $this->postJson(self::URL, ['kind' => 'addon', 'billing_cycle' => 'monthly'], $headers)->assertStatus(422)->assertJsonValidationErrors(['addon_key']);
        $this->postJson(self::URL, ['kind' => 'addon', 'addon_key' => 'x', 'quantity' => 51, 'billing_cycle' => 'monthly'], $headers)->assertStatus(422)->assertJsonValidationErrors(['quantity']);
        $this->postJson(self::URL, ['kind' => 'addon', 'addon_key' => 'x', 'quantity' => 0, 'billing_cycle' => 'monthly'], $headers)->assertStatus(422)->assertJsonValidationErrors(['quantity']);
        $this->postJson(self::URL, ['kind' => 'plan_change', 'requested_plan_code' => 'x', 'billing_cycle' => 'biennial'], $headers)->assertStatus(422)->assertJsonValidationErrors(['billing_cycle']);
        $this->postJson(self::URL, ['kind' => 'plan_change', 'requested_plan_code' => 'x', 'billing_cycle' => 'monthly', 'notes' => str_repeat('a', 501)], $headers)->assertStatus(422)->assertJsonValidationErrors(['notes']);
        $this->getJson(self::URL.'?per_page=1000', $headers)->assertStatus(422)->assertJsonValidationErrors(['per_page']);
    }

    public function test_the_shop_cancels_its_own_pending_request_once(): void
    {
        $tenant = $this->tenantOn($this->plan());
        $row = $this->requestRow($tenant, ['requested_plan_id' => $this->plan()->getKey()]);
        $headers = $this->tenantHeaders($tenant);

        $this->postJson(self::URL.'/'.$row->getKey().'/cancel', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled');

        $this->postJson(self::URL.'/'.$row->getKey().'/cancel', [], $headers)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'billing.request_not_pending');

        // A cancelled request frees the slot for a new one.
        $this->postJson(self::URL, ['kind' => 'plan_change', 'requested_plan_code' => $this->plan()->slug, 'billing_cycle' => 'monthly'], $headers)->assertCreated();
    }

    public function test_a_shop_never_sees_or_cancels_another_shops_requests(): void
    {
        $plan = $this->plan();
        $a = $this->tenantOn($plan);
        $b = $this->tenantOn($plan);
        $rowB = $this->requestRow($b, ['requested_plan_id' => $this->plan()->getKey()]);

        $this->getJson(self::URL, $this->tenantHeaders($a))->assertOk()->assertJsonCount(0, 'data');
        $this->postJson(self::URL.'/'.$rowB->getKey().'/cancel', [], $this->tenantHeaders($a))->assertNotFound();
        $this->assertTrue($rowB->fresh()->isPending());
        $this->getJson(self::URL, $this->tenantHeaders($b))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_read_only_shop_can_request_and_cancel_but_other_writes_stay_locked(): void
    {
        $small = $this->plan();
        $big = $this->plan();
        $tenant = $this->tenantOn($small, ['status' => 'read_only', 'status_changed_at' => now()->subDay()]);
        $headers = $this->tenantHeaders($tenant);

        $id = $this->postJson(self::URL, ['kind' => 'plan_change', 'requested_plan_code' => $big->slug, 'billing_cycle' => 'monthly'], $headers)
            ->assertCreated()->json('data.id');
        $this->postJson(self::URL.'/'.$id.'/cancel', [], $headers)->assertOk();
        $this->postJson('/api/v1/customers', [], $headers)->assertStatus(423);
    }

    public function test_requests_are_throttled_per_shop(): void
    {
        config(['rate_limits.billing_plan_change_request.per_hour' => 2]);
        $tenant = $this->tenantOn($this->plan());
        $headers = $this->tenantHeaders($tenant);
        $payload = ['kind' => 'plan_change', 'requested_plan_code' => $this->plan()->slug, 'billing_cycle' => 'monthly'];

        $this->postJson(self::URL, $payload, $headers)->assertCreated();
        $this->postJson(self::URL, $payload, $headers)->assertStatus(422);
        $this->postJson(self::URL, $payload, $headers)->assertStatus(429);
    }

    public function test_guests_are_401_and_users_without_the_permission_are_403(): void
    {
        $tenant = $this->createTenant();
        $cashier = $this->createTenantUser($tenant, null, ['invoices.view']);

        foreach ([['GET', self::URL], ['POST', self::URL], ['POST', self::URL.'/1/cancel']] as [$method, $uri]) {
            $this->json($method, $uri, [], $this->tenantGuestHeaders($tenant))->assertUnauthorized();
            $this->json($method, $uri, [], $this->tenantHeaders($tenant, $cashier))->assertForbidden();
        }
    }

    public function test_the_subscription_payload_carries_the_request_form_data(): void
    {
        $small = $this->plan(['name' => 'Small', 'price_monthly' => '100.000', 'price_yearly' => '1000.000']);
        $big = $this->plan(['name' => 'Big', 'price_monthly' => '300.000', 'price_yearly' => '3000.000']);
        $this->plan(['name' => 'Hidden', 'is_public' => false]);
        $addon = $this->addonOn($small, unit: '80.000', yearly: '800.000');
        $tenant = $this->subscribedTenant($small);
        $headers = $this->tenantHeaders($tenant);

        $response = $this->getJson('/api/v1/billing/subscription', $headers)->assertOk()
            ->assertJsonPath('data.pending_plan_change', null)
            ->assertJsonPath('data.pending_addon_request', null)
            ->assertJsonPath('data.available_addons.0.key', $addon->key)
            ->assertJsonPath('data.available_addons.0.unit_price', '80.000')
            ->assertJsonPath('data.available_addons.0.yearly_price', '800.000');

        $codes = array_column($response->json('data.available_plans'), 'code');
        $this->assertContains($big->slug, $codes);
        $this->assertNotContains('hidden', array_map('strtolower', array_column($response->json('data.available_plans'), 'name')));
        $this->assertSame(['code', 'name', 'price_monthly', 'price_yearly', 'is_current'], array_keys($response->json('data.available_plans.0')));

        $this->postJson(self::URL, ['kind' => 'plan_change', 'requested_plan_code' => $big->slug, 'billing_cycle' => 'monthly'], $headers)->assertCreated();

        $this->getJson('/api/v1/billing/subscription', $headers)->assertOk()
            ->assertJsonPath('data.pending_plan_change.requested_plan.code', $big->slug)
            ->assertJsonPath('data.pending_plan_change.status.value', 'pending');
    }
}
