<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Actions\Tenants\ToggleTenantStatusAction;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class LegacyToggleTenantStatusActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function tenant(array $attributes): Tenant
    {
        return Tenant::withoutEvents(fn () => Tenant::create(array_merge([
            'id' => 'toggle-'.uniqid(),
            'name' => 'Toggle shop',
            'slug' => 'toggle-'.uniqid(),
            'email' => 'owner@example.test',
        ], $attributes)));
    }

    public function test_extending_an_expired_trial_moves_trial_ends_at_and_unblocks_it(): void
    {
        Carbon::setTestNow('2026-10-09 12:00:00');
        $tenant = $this->tenant(['status' => 'trial', 'trial_ends_at' => now()->subDay(), 'subscription_ends_at' => null]);
        $this->assertTrue($tenant->isSuspended());

        $tenant = (new ToggleTenantStatusAction)->execute($tenant, 'trial', 7);

        $this->assertSame('2026-10-16 12:00:00', $tenant->fresh()->trial_ends_at->toDateTimeString());
        $this->assertNull($tenant->fresh()->subscription_ends_at);
        $this->assertFalse($tenant->fresh()->isSuspended());
    }

    public function test_activating_without_a_paid_period_sets_one_month(): void
    {
        Carbon::setTestNow('2026-10-09 12:00:00');
        $tenant = $this->tenant(['status' => 'read_only', 'subscription_ends_at' => null]);

        (new ToggleTenantStatusAction)->execute($tenant, 'active');

        $fresh = $tenant->fresh();
        $this->assertSame('active', $fresh->status);
        $this->assertSame('2026-11-09 12:00:00', $fresh->subscription_ends_at->toDateTimeString());
        $this->assertSame('2026-10-09 12:00:00', $fresh->status_changed_at->toDateTimeString());
    }

    public function test_extending_an_active_tenant_adds_to_the_future_end(): void
    {
        Carbon::setTestNow('2026-10-09 12:00:00');
        $tenant = $this->tenant(['status' => 'active', 'subscription_ends_at' => now()->addDays(10)]);

        (new ToggleTenantStatusAction)->execute($tenant, 'active', 30);

        $fresh = $tenant->fresh();
        $this->assertSame('2026-11-18 12:00:00', $fresh->subscription_ends_at->toDateTimeString());
        $this->assertNull($fresh->status_changed_at);
    }
}
