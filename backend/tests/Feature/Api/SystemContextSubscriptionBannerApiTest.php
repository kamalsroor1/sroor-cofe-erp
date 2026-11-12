<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TenantTestCase;

/**
 * SELL-02: GET /api/v1/system/context carries `subscription_banner` (trial / expiring / grace /
 * read_only) for users with billing.manage only, null for a healthy paid subscription, and the
 * header bell lists the user's unread subscription reminder for the running period.
 */
final class SystemContextSubscriptionBannerApiTest extends TenantTestCase
{
    private const URL = '/api/v1/system/context';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-12 09:00:00', (string) config('app.timezone')));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-12 09:00:00', (string) config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_admin_on_a_trial_with_three_days_left_gets_the_trial_banner(): void
    {
        $tenant = $this->trialTenant(now()->addDays(3));

        $this->getJson(self::URL, $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.subscription_banner.state', 'trial')
            ->assertJsonPath('data.subscription_banner.kind', 'trial')
            ->assertJsonPath('data.subscription_banner.days_left', 3)
            ->assertJsonPath('data.subscription_banner.ends_at', now()->addDays(3)->toIso8601String())
            ->assertJsonPath('data.subscription_banner.grace_ends_at', now()->addDays(6)->toIso8601String())
            ->assertJsonPath('data.subscription_banner.reason', null)
            ->assertJsonPath('data.subscription_banner.action_path', '/billing');
    }

    public function test_a_long_trial_still_shows_the_banner(): void
    {
        $tenant = $this->trialTenant(now()->addDays(14));

        $this->getJson(self::URL, $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.subscription_banner.state', 'trial')
            ->assertJsonPath('data.subscription_banner.days_left', 14);
    }

    public function test_a_cashier_gets_null(): void
    {
        $tenant = $this->trialTenant(now()->addDays(3));
        $cashier = $this->createTenantUser($tenant, role: 'cashier');

        $this->getJson(self::URL, $this->tenantHeaders($tenant, $cashier))
            ->assertOk()
            ->assertJsonPath('data.subscription_banner', null);
    }

    public function test_a_user_with_only_the_billing_permission_gets_the_banner(): void
    {
        $tenant = $this->trialTenant(now()->addDays(3));
        $biller = $this->createTenantUser($tenant, permissions: ['billing.manage']);

        $this->getJson(self::URL, $this->tenantHeaders($tenant, $biller))
            ->assertOk()
            ->assertJsonPath('data.subscription_banner.state', 'trial');
    }

    public function test_a_quick_login_session_gets_no_banner_and_no_billing_reminder(): void
    {
        Http::fake();
        $tenant = $this->trialTenant(now()->addDays(3));
        $this->artisan('tenants:send-lifecycle-reminders')->assertSuccessful();
        $adminId = $this->tenantAdmin($tenant)->id;
        // Same token shape as the testing-only quick login: only the `quick-login` ability.
        $token = $this->inTenant($tenant, fn (): string => User::query()->findOrFail($adminId)->createToken('quick', ['quick-login'])->plainTextToken);
        $headers = ['Authorization' => 'Bearer '.$token] + $this->tenantHeaders($tenant);

        $response = $this->getJson(self::URL, $headers)
            ->assertOk()
            ->assertJsonPath('data.subscription_banner', null);
        $this->assertNull(collect($response->json('data.notifications'))->firstWhere('link', '/billing'));

        // /billing itself refuses this session, which is why nothing points there.
        $this->getJson('/api/v1/billing/subscription', $headers)->assertForbidden();
        // The same admin with a full token does get it.
        $this->getJson(self::URL, $this->tenantHeaders($tenant))->assertJsonPath('data.subscription_banner.state', 'trial');
    }

    public function test_a_healthy_paid_subscription_gets_null(): void
    {
        $tenant = $this->createTenant(['status' => 'active', 'subscription_ends_at' => now()->addDays(20)]);

        $this->getJson(self::URL, $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.subscription_banner', null);
    }

    public function test_a_paid_subscription_within_seven_days_is_expiring(): void
    {
        $tenant = $this->createTenant(['status' => 'active', 'trial_ends_at' => null, 'subscription_ends_at' => now()->addDays(7)]);

        $this->getJson(self::URL, $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.subscription_banner.state', 'expiring')
            ->assertJsonPath('data.subscription_banner.kind', 'subscription')
            ->assertJsonPath('data.subscription_banner.days_left', 7);
    }

    public function test_an_ended_trial_inside_its_grace_days_is_grace(): void
    {
        // Ended yesterday 15:00; grace = 3 days (TENANT_GRACE_DAYS) → view-only from 2026-11-14 15:00.
        $tenant = $this->trialTenant(now()->subDay()->setTime(15, 0));

        $this->getJson(self::URL, $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.subscription_banner.state', 'grace')
            ->assertJsonPath('data.subscription_banner.kind', 'trial')
            ->assertJsonPath('data.subscription_banner.days_left', 2)
            ->assertJsonPath('data.subscription_banner.grace_ends_at', now()->subDay()->setTime(15, 0)->addDays(3)->toIso8601String());
    }

    public function test_an_ended_paid_subscription_inside_its_grace_days_is_grace(): void
    {
        $tenant = $this->createTenant(['status' => 'active', 'trial_ends_at' => null, 'subscription_ends_at' => now()->subHours(2)]);

        $this->getJson(self::URL, $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.subscription_banner.state', 'grace')
            ->assertJsonPath('data.subscription_banner.kind', 'subscription')
            ->assertJsonPath('data.subscription_banner.days_left', 3);
    }

    public function test_a_read_only_tenant_gets_the_read_only_banner_with_its_reason(): void
    {
        $tenant = $this->trialTenant(now()->subDays(10));

        $this->getJson(self::URL, $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.subscription_banner.state', 'read_only')
            ->assertJsonPath('data.subscription_banner.days_left', null)
            ->assertJsonPath('data.subscription_banner.reason', 'trial_ended')
            ->assertJsonPath('data.subscription_banner.action_path', '/billing');
    }

    public function test_the_bell_lists_the_reminder_of_the_running_period_for_billing_users_only(): void
    {
        Http::fake();
        $tenant = $this->trialTenant(now()->addDays(3));
        $cashier = $this->createTenantUser($tenant, role: 'cashier');
        $this->artisan('tenants:send-lifecycle-reminders')->assertSuccessful();

        $notifications = $this->getJson(self::URL, $this->tenantHeaders($tenant))
            ->assertOk()
            ->json('data.notifications');
        $reminder = collect($notifications)->firstWhere('link', '/billing');
        $this->assertNotNull($reminder, 'The subscription reminder must be in the bell.');
        $this->assertSame((string) __('subscription.reminders.trial.days_left', ['days' => 3]), $reminder['title']);

        $cashierNotifications = $this->getJson(self::URL, $this->tenantHeaders($tenant, $cashier))->assertOk()->json('data.notifications');
        $this->assertNull(collect($cashierNotifications)->firstWhere('link', '/billing'));

        // Paid meanwhile: the trial reminder is stale and disappears.
        $this->endTenancy();
        Tenant::query()->whereKey($tenant->id)->update(['status' => 'active', 'subscription_ends_at' => now()->addMonth()]);

        $after = $this->getJson(self::URL, $this->tenantHeaders($tenant))->assertOk()->json('data.notifications');
        $this->assertNull(collect($after)->firstWhere('link', '/billing'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function trialTenant(Carbon $trialEndsAt, array $attributes = []): Tenant
    {
        return $this->createTenant(array_merge([
            'status' => 'trial',
            'status_changed_at' => now()->subDays(11),
            'trial_ends_at' => $trialEndsAt,
            'subscription_ends_at' => null,
        ], $attributes));
    }
}
