<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralAuditEvent;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\TenantLifecycleEvent;
use Database\Seeders\CentralPermissionsSeeder;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * IDEN-1.4: the legacy POST /api/v1/super-admin/tenants/{id}/toggle-status goes through the
 * IDEN-3.3 state machine (TransitionTenantStatusAction, actor super_admin, causer = operator).
 */
final class ToggleStatusStateMachineTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private CentralUser $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CentralPermissionsSeeder::class);
        $this->operator = $this->centralSuperAdmin();
    }

    /** @param array<string, mixed> $payload */
    private function toggle(Tenant $tenant, array $payload): TestResponse
    {
        return $this->postJson(
            '/api/v1/super-admin/tenants/'.$tenant->getTenantKey().'/toggle-status',
            $payload,
            // toggle-status demands a recent second factor (security audit, W2 lane 3I).
            $this->steppedUpCentralHeaders($this->operator),
        );
    }

    public function test_activation_is_refused_with_409_because_only_billing_activates(): void
    {
        $tenant = $this->createTenant(['status' => 'suspended', 'suspension_reason' => 'other']);

        $this->toggle($tenant, ['status' => 'active', 'extend_days' => 0])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'subscription.invalid_transition')
            ->assertJsonPath('details.to', 'active')
            ->assertJsonPath('details.actor', 'super_admin');

        $this->assertSame('suspended', Tenant::query()->findOrFail($tenant->getKey())->status);
    }

    public function test_suspension_goes_through_the_state_machine_with_the_operator_as_causer(): void
    {
        $tenant = $this->createTenant(['status' => 'active']);

        $this->toggle($tenant, ['status' => 'suspended', 'reason' => 'violation', 'note' => 'مخالفة شروط'])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $event = TenantLifecycleEvent::query()->where('tenant_id', $tenant->getKey())->latest('id')->firstOrFail();
        $this->assertSame('suspended', $event->to_status->value);
        $this->assertSame('super_admin', $event->actor->value);
        $this->assertSame((int) $this->operator->getKey(), $event->central_user_id);
        $this->assertSame('violation', $event->reason?->value);
    }

    public function test_suspension_without_reason_is_422(): void
    {
        $tenant = $this->createTenant(['status' => 'active']);

        $this->toggle($tenant, ['status' => 'suspended'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_extend_days_is_only_accepted_with_trial(): void
    {
        $tenant = $this->createTenant(['status' => 'active']);

        $this->toggle($tenant, ['status' => 'suspended', 'reason' => 'other', 'extend_days' => 30])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('extend_days');
    }

    public function test_a_trial_is_extended_without_a_status_change(): void
    {
        $this->freezeSecond();
        $tenant = $this->createTenant(['status' => 'trial', 'trial_ends_at' => now()->addDays(3), 'subscription_ends_at' => null]);

        $this->toggle($tenant, ['status' => 'trial', 'extend_days' => 10])
            ->assertOk()
            ->assertJsonPath('data.status', 'trial');

        $fresh = Tenant::query()->findOrFail($tenant->getKey());
        $this->assertTrue($fresh->trial_ends_at?->equalTo(now()->addDays(13)));
        $this->assertSame(0, TenantLifecycleEvent::query()->where('tenant_id', $tenant->getKey())->count());
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::TenantTrialExtended->value)->count());
    }

    public function test_trial_to_trial_without_days_is_409(): void
    {
        $tenant = $this->createTenant(['status' => 'trial', 'trial_ends_at' => now()->addDays(3)]);

        $this->toggle($tenant, ['status' => 'trial'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'subscription.invalid_transition');
    }

    public function test_read_only_back_to_trial_sets_a_new_trial_end(): void
    {
        $this->freezeSecond();
        $tenant = $this->createTenant(['status' => 'read_only', 'trial_ends_at' => now()->subDay(), 'subscription_ends_at' => null]);

        $this->toggle($tenant, ['status' => 'trial', 'extend_days' => 5])
            ->assertOk()
            ->assertJsonPath('data.status', 'trial');

        $fresh = Tenant::query()->findOrFail($tenant->getKey());
        $this->assertTrue($fresh->trial_ends_at?->equalTo(now()->addDays(5)));
        $this->assertNotNull($fresh->trial_extended_at);
        $this->assertSame(1, TenantLifecycleEvent::query()->where('tenant_id', $tenant->getKey())->where('to_status', 'trial')->count());
    }

    public function test_a_move_the_state_machine_does_not_have_is_409(): void
    {
        $tenant = $this->createTenant(['status' => 'active']);

        $this->toggle($tenant, ['status' => 'trial', 'extend_days' => 5])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'subscription.invalid_transition');

        $this->assertSame('active', Tenant::query()->findOrFail($tenant->getKey())->status);
    }
}
