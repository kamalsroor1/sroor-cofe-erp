<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Actions\Tenants\ApplyTenantLifecycleAction;
use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Http\Middleware\EnsureTenantActive;
use App\Models\Tenant;
use App\Models\TenantLifecycleEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TenantTestCase;

/**
 * SEC-01 + BE-05 + PRD-02: the subscription lifecycle is enforced on the tenant API
 * (EnsureTenantActive inside ResolveApiTenancy, header AND host path), and the daily
 * `tenants:apply-lifecycle` sweep persists the time-driven moves.
 *
 * Owner decision: trial/active full access; 3 grace days after the end date; then
 * read-only (GET ok, writes 423); suspended / cancelled / archived fully blocked (403).
 */
final class TenantLifecycleEnforcementTest extends TenantTestCase
{
    // ── enforcement ───────────────────────────────────────────────────────

    public function test_active_tenant_reads_and_writes(): void
    {
        $tenant = $this->createTenant(['status' => 'active', 'subscription_ends_at' => now()->addMonth()]);

        $this->getAs($tenant, '/api/v1/customers')->assertOk();
        $this->assertWriteReachesTheController($this->postAs($tenant, '/api/v1/customers'));
    }

    public function test_running_trial_reads_and_writes(): void
    {
        $tenant = $this->createTenant(['status' => 'trial', 'subscription_ends_at' => null, 'trial_ends_at' => now()->addDays(5)]);

        $this->getAs($tenant, '/api/v1/customers')->assertOk();
        $this->assertWriteReachesTheController($this->postAs($tenant, '/api/v1/customers'));
    }

    public function test_expired_trial_inside_the_grace_period_keeps_full_access(): void
    {
        $tenant = $this->createTenant(['status' => 'trial', 'subscription_ends_at' => null, 'trial_ends_at' => now()->subDays(2)]);

        $this->getAs($tenant, '/api/v1/customers')->assertOk();
        $this->assertWriteReachesTheController($this->postAs($tenant, '/api/v1/customers'));
    }

    public function test_expired_subscription_inside_the_grace_period_keeps_full_access(): void
    {
        $tenant = $this->createTenant(['status' => 'active', 'subscription_ends_at' => now()->subDays(2)]);

        $this->getAs($tenant, '/api/v1/customers')->assertOk();
        $this->assertWriteReachesTheController($this->postAs($tenant, '/api/v1/customers'));
    }

    public function test_grace_period_length_comes_from_config(): void
    {
        config(['tenant_lifecycle.trial_grace_days' => 0]);
        $tenant = $this->createTenant(['status' => 'trial', 'subscription_ends_at' => null, 'trial_ends_at' => now()->subHour()]);

        $this->assertReadOnlyRefusal($this->postAs($tenant, '/api/v1/customers'));
    }

    public function test_expired_trial_after_the_grace_period_is_read_only(): void
    {
        $tenant = $this->createTenant(['status' => 'trial', 'subscription_ends_at' => null, 'trial_ends_at' => now()->subDays(4)]);

        $this->getAs($tenant, '/api/v1/customers')->assertOk();
        $this->assertReadOnlyRefusal($this->postAs($tenant, '/api/v1/customers'));
        $this->assertReadOnlyRefusal($this->putAs($tenant, '/api/v1/stores/1'));
        $this->assertReadOnlyRefusal($this->deleteAs($tenant, '/api/v1/stores/1'));
    }

    public function test_expired_subscription_after_the_grace_period_is_read_only(): void
    {
        $tenant = $this->createTenant(['status' => 'active', 'subscription_ends_at' => now()->subDays(4)]);

        $this->getAs($tenant, '/api/v1/customers')->assertOk();
        $this->assertReadOnlyRefusal($this->postAs($tenant, '/api/v1/customers'));
    }

    public function test_stored_read_only_tenant_can_still_log_out_and_switch_but_not_write(): void
    {
        $tenant = $this->createTenant(['status' => 'read_only', 'status_changed_at' => now()->subDay()]);

        $this->postAs($tenant, '/api/v1/auth/logout')->assertOk();
        $this->assertReadOnlyRefusal($this->postAs($tenant, '/api/v1/customers'));
    }

    public function test_read_only_tenant_can_still_log_in(): void
    {
        $tenant = $this->createTenant(['status' => 'read_only', 'status_changed_at' => now()->subDay()]);
        $admin = $this->tenantAdmin($tenant);

        $this->postJson('/api/v1/auth/login', ['login' => $admin->email, 'password' => 'password'], ['X-Tenant' => (string) $tenant->getTenantKey()])
            ->assertOk();
    }

    public function test_suspended_tenant_is_blocked_on_get_and_post(): void
    {
        $tenant = $this->createTenant(['status' => 'suspended', 'status_changed_at' => now()->subDay()]);

        $this->assertBlocked($this->getAs($tenant, '/api/v1/customers'), 'suspended');
        $this->assertBlocked($this->postAs($tenant, '/api/v1/customers'), 'suspended');
    }

    public function test_cancelled_and_archived_tenants_are_blocked(): void
    {
        foreach (['cancelled', 'archived'] as $status) {
            $tenant = $this->createTenant(['status' => $status, 'status_changed_at' => now()->subDay()]);

            $this->assertBlocked($this->getAs($tenant, '/api/v1/customers'), $status);
            $this->assertBlocked($this->postAs($tenant, '/api/v1/customers'), $status);
        }
    }

    public function test_read_only_past_its_30_days_is_blocked_without_the_sweep(): void
    {
        $tenant = $this->createTenant(['status' => 'read_only', 'status_changed_at' => now()->subDays(31)]);

        $this->assertBlocked($this->getAs($tenant, '/api/v1/customers'), 'suspended');
    }

    public function test_blocked_tenant_cannot_log_in(): void
    {
        $tenant = $this->createTenant(['status' => 'suspended', 'status_changed_at' => now()->subDay()]);
        $admin = $this->tenantAdmin($tenant);

        $this->assertBlockedGeneric(
            $this->postJson('/api/v1/auth/login', ['login' => $admin->email, 'password' => 'password'], ['X-Tenant' => (string) $tenant->getTenantKey()]),
        );
    }

    public function test_blocked_tenant_can_still_log_out_and_read_its_profile(): void
    {
        $tenant = $this->createTenant(['status' => 'suspended', 'status_changed_at' => now()->subDay()]);

        $this->getAs($tenant, '/api/v1/auth/me')->assertOk();
        $this->postAs($tenant, '/api/v1/auth/logout')->assertOk();
        $this->getJson('/api/v1/auth/options', ['X-Tenant' => (string) $tenant->getTenantKey()])->assertOk();
    }

    public function test_blocked_tenant_is_refused_on_the_host_path_too(): void
    {
        $tenant = $this->createTenant(['status' => 'suspended', 'status_changed_at' => now()->subDay()]);
        $host = $tenant->getTenantKey().'.'.parse_url((string) config('app.url'), PHP_URL_HOST);
        $headers = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($tenant)];

        $this->assertBlocked($this->getJson('http://'.$host.'/api/v1/customers', $headers), 'suspended');
        $this->assertBlocked($this->postJson('http://'.$host.'/api/v1/customers', [], $headers), 'suspended');
    }

    public function test_read_only_tenant_is_refused_on_the_host_path_too(): void
    {
        $tenant = $this->createTenant(['status' => 'read_only', 'status_changed_at' => now()->subDay()]);
        $host = $tenant->getTenantKey().'.'.parse_url((string) config('app.url'), PHP_URL_HOST);
        $headers = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($tenant)];

        $this->getJson('http://'.$host.'/api/v1/customers', $headers)->assertOk();
        $this->assertReadOnlyRefusal($this->postJson('http://'.$host.'/api/v1/customers', [], $headers));
    }

    public function test_one_blocked_tenant_does_not_affect_another(): void
    {
        $blocked = $this->createTenant(['status' => 'suspended', 'status_changed_at' => now()->subDay()]);
        $healthy = $this->createTenant(['status' => 'active', 'subscription_ends_at' => now()->addMonth()]);

        $this->assertBlocked($this->getAs($blocked, '/api/v1/customers'), 'suspended');
        $this->getAs($healthy, '/api/v1/customers')->assertOk();
    }

    public function test_central_super_admin_routes_are_not_affected(): void
    {
        $this->createTenant(['status' => 'suspended', 'status_changed_at' => now()->subDay()]);
        $super = $this->centralSuperAdmin();

        $this->getJson('/api/v1/super-admin/tenants', $this->centralHeaders($super))->assertOk();
    }

    public function test_public_resolver_only_hides_blocked_workspaces(): void
    {
        $this->assertFalse($this->createTenant(['status' => 'trial', 'subscription_ends_at' => null, 'trial_ends_at' => now()->subDays(1)])->isSuspended());
        $this->assertFalse($this->createTenant(['status' => 'trial', 'subscription_ends_at' => null, 'trial_ends_at' => now()->subDays(10)])->isSuspended());
        $this->assertTrue($this->createTenant(['status' => 'suspended', 'status_changed_at' => now()])->isSuspended());
    }

    // ── sweep ─────────────────────────────────────────────────────────────

    public function test_sweep_moves_an_expired_trial_to_read_only_only_after_the_grace_days(): void
    {
        $tenant = $this->createTenant(['status' => 'trial', 'subscription_ends_at' => null, 'trial_ends_at' => now()->subDays(2)]);

        $this->runSweep()->assertSuccessful();
        $this->assertSame('trial', $this->storedStatus($tenant));

        $this->travel(2)->days();
        $this->runSweep()->assertSuccessful();
        $this->assertSame('read_only', $this->storedStatus($tenant));
        $this->assertNotNull(DB::table('tenants')->where('id', $tenant->getKey())->value('read_only_since'));
    }

    public function test_sweep_walks_active_through_past_due_read_only_and_suspended(): void
    {
        $tenant = $this->createTenant(['status' => 'active', 'subscription_ends_at' => now()->addDay()]);

        $this->travel(2)->days();
        $this->runSweep();
        $this->assertSame('past_due', $this->storedStatus($tenant));

        $this->travel(4)->days();
        $this->runSweep();
        $this->assertSame('read_only', $this->storedStatus($tenant));

        $this->travel(31)->days();
        $this->runSweep();
        $this->assertSame('suspended', $this->storedStatus($tenant));

        $moves = TenantLifecycleEvent::query()->where('tenant_id', $tenant->getKey())->orderBy('id')->pluck('to_status')->map(fn ($s) => $s instanceof TenantStatus ? $s->value : $s)->all();
        $this->assertSame(['past_due', 'read_only', 'suspended'], $moves);
    }

    public function test_sweep_is_idempotent_and_audits_each_move_as_system(): void
    {
        $tenant = $this->createTenant(['status' => 'trial', 'subscription_ends_at' => null, 'trial_ends_at' => now()->subDays(5)]);

        $first = app(ApplyTenantLifecycleAction::class)->execute();
        $second = app(ApplyTenantLifecycleAction::class)->execute();

        $this->assertSame([['tenant_id' => (string) $tenant->getKey(), 'from' => 'trial', 'to' => 'read_only']], $first->applied);
        $this->assertSame([], $second->applied);
        $this->assertSame([], $first->failed);

        $events = TenantLifecycleEvent::query()->where('tenant_id', $tenant->getKey())->get();
        $this->assertCount(1, $events);
        $this->assertSame(TenantLifecycleActor::System, $events->first()->actor);
        $this->assertSame(1, DB::table('central_audit_logs')->where('tenant_id', $tenant->getKey())->count());
    }

    public function test_sweep_dry_run_changes_nothing(): void
    {
        $tenant = $this->createTenant(['status' => 'trial', 'subscription_ends_at' => null, 'trial_ends_at' => now()->subDays(5)]);

        $result = app(ApplyTenantLifecycleAction::class)->execute(dryRun: true);

        $this->assertCount(1, array_filter($result->applied, fn (array $m): bool => $m['tenant_id'] === (string) $tenant->getKey()));
        $this->assertSame('trial', $this->storedStatus($tenant));
        $this->assertSame(0, TenantLifecycleEvent::query()->where('tenant_id', $tenant->getKey())->count());
    }

    public function test_sweep_never_auto_archives_and_reports_it_as_due(): void
    {
        $tenant = $this->createTenant(['status' => 'suspended', 'status_changed_at' => now()->subDays(100)]);

        $result = app(ApplyTenantLifecycleAction::class)->execute();

        $this->assertContains((string) $tenant->getKey(), $result->archiveDue);
        $this->assertSame('suspended', $this->storedStatus($tenant));
    }

    public function test_sweep_leaves_healthy_and_blocked_tenants_alone_and_is_scheduled_daily(): void
    {
        $healthy = $this->createTenant(['status' => 'active', 'subscription_ends_at' => now()->addMonth()]);
        $suspended = $this->createTenant(['status' => 'suspended', 'status_changed_at' => now()->subDay()]);

        $result = app(ApplyTenantLifecycleAction::class)->execute();

        $this->assertSame([], array_filter($result->applied, fn (array $m): bool => in_array($m['tenant_id'], [(string) $healthy->getKey(), (string) $suspended->getKey()], true)));

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e): bool => str_contains((string) $e->command, 'tenants:apply-lifecycle'));
        $this->assertNotNull($event, 'tenants:apply-lifecycle must be scheduled.');
        $this->assertStringContainsString('1', $event->expression);
    }

    // ── follow-up: allowlist, info leak, /me, web, spoofing, ?tenant= ─────

    public function test_read_only_tenant_keeps_shift_close_profile_update_calculator_and_quick_login_reachable(): void
    {
        $tenant = $this->createTenant(['status' => 'read_only', 'status_changed_at' => now()->subDay()]);

        foreach ([
            ['post', '/api/v1/shifts/close'],
            ['put', '/api/v1/profile'],
            ['post', '/api/v1/coffee-blender/calculate'],
        ] as [$method, $uri]) {
            $response = $this->{$method.'Json'}($uri, [], $this->tenantHeaders($tenant));
            $this->assertNotSame(423, $response->status(), "{$method} {$uri} must not be refused as read-only.");
            $response->assertJsonMissingPath('error_code');
        }

        // quick-login is only routed outside production with the flag; the gate itself must allow it.
        $this->assertContains('api.auth.quick_login', EnsureTenantActive::WRITE_ALLOWLIST);
        $this->assertReadOnlyRefusal($this->postAs($tenant, '/api/v1/coffee-blender/invoice'));
    }

    public function test_unauthenticated_callers_get_a_generic_403_without_the_status(): void
    {
        foreach (['suspended', 'cancelled', 'archived'] as $status) {
            $tenant = $this->createTenant(['status' => $status, 'status_changed_at' => now()->subDay()]);

            $this->assertBlockedGeneric($this->getJson('/api/v1/customers', ['X-Tenant' => (string) $tenant->getTenantKey()]));
            $this->assertBlockedGeneric($this->postJson('/api/v1/customers', [], ['X-Tenant' => (string) $tenant->getTenantKey()]));
        }
    }

    public function test_tenant_query_parameter_identification_is_gated_too(): void
    {
        $blocked = $this->createTenant(['status' => 'suspended', 'status_changed_at' => now()->subDay()]);
        $readOnly = $this->createTenant(['status' => 'read_only', 'status_changed_at' => now()->subDay()]);

        $this->assertBlocked(
            $this->getJson('/api/v1/customers?tenant='.$blocked->getTenantKey(), ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($blocked)]),
            'suspended',
        );
        $this->assertReadOnlyRefusal(
            $this->postJson('/api/v1/customers?tenant='.$readOnly->getTenantKey(), [], ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($readOnly)]),
        );
    }

    public function test_method_spoofing_cannot_turn_a_write_into_a_read_for_a_read_only_tenant(): void
    {
        $tenant = $this->createTenant(['status' => 'read_only', 'status_changed_at' => now()->subDay()]);
        $headers = $this->tenantHeaders($tenant);

        $this->assertReadOnlyRefusal($this->postJson('/api/v1/stores/1', ['_method' => 'PUT'], $headers));
        $this->assertReadOnlyRefusal($this->postJson('/api/v1/stores/1', [], $headers + ['X-HTTP-Method-Override' => 'PUT']));
        $this->assertReadOnlyRefusal($this->postJson('/api/v1/stores/1', ['_method' => 'DELETE'], $headers));
    }

    public function test_me_of_a_blocked_tenant_returns_only_user_system_and_the_status_block(): void
    {
        $tenant = $this->createTenant(['status' => 'suspended', 'status_changed_at' => now()->subDay()]);

        $response = $this->getAs($tenant, '/api/v1/auth/me')->assertOk();
        $data = $response->json('data') ?? $response->json();

        $this->assertEqualsCanonicalizing(['user', 'system', 'subscription'], array_keys($data));
        $this->assertSame('suspended', $data['subscription']['status']);
        $this->assertSame('blocked', $data['subscription']['access']);
    }

    public function test_me_of_a_healthy_tenant_keeps_stores_and_reports_the_subscription_state(): void
    {
        $tenant = $this->createTenant(['status' => 'trial', 'subscription_ends_at' => null, 'trial_ends_at' => now()->addDays(5)]);

        $response = $this->getAs($tenant, '/api/v1/auth/me')->assertOk();
        $data = $response->json('data') ?? $response->json();

        $this->assertArrayHasKey('stores', $data);
        $this->assertArrayHasKey('active_shift', $data);
        $this->assertSame('trial', $data['subscription']['status']);
        $this->assertSame('full', $data['subscription']['access']);
    }

    public function test_tenant_web_pages_are_blocked_for_blocked_tenants_but_the_spa_shell_stays(): void
    {
        $blocked = $this->createTenant(['status' => 'suspended', 'status_changed_at' => now()->subDay()]);
        $active = $this->createTenant(['status' => 'active', 'subscription_ends_at' => now()->addMonth()]);
        $this->createTenantDomain($blocked);
        $this->createTenantDomain($active);

        $this->get('http://'.$this->domainOf($blocked).'/login')->assertOk();
        $this->get('http://'.$this->domainOf($blocked).'/spa')->assertOk();
        $admin = $this->tenantAdmin($blocked);
        $this->actingAs($admin)->get('http://'.$this->domainOf($blocked).'/activity-logs/export-csv')->assertForbidden();
        $this->actingAs($admin)->get('http://'.$this->domainOf($blocked).'/spa/dashboard')->assertOk();

        $this->get('http://'.$this->domainOf($active).'/login')->assertOk();
        $this->actingAs($this->tenantAdmin($active))->get('http://'.$this->domainOf($active).'/activity-logs/export-csv')->assertOk();
    }

    public function test_sweep_isolates_a_failing_tenant_and_still_moves_the_others(): void
    {
        $bad = $this->createTenant(['status' => 'trial', 'subscription_ends_at' => null, 'trial_ends_at' => now()->subDays(9)]);
        $good = $this->createTenant(['status' => 'trial', 'subscription_ends_at' => null, 'trial_ends_at' => now()->subDays(9)]);

        self::$failingTenantId = (string) $bad->getKey();
        Tenant::updating(static function (Tenant $tenant): void {
            if (self::$failingTenantId !== null && (string) $tenant->getKey() === self::$failingTenantId) {
                throw new \RuntimeException('simulated failure');
            }
        });

        try {
            $result = app(ApplyTenantLifecycleAction::class)->execute();
        } finally {
            self::$failingTenantId = null;
        }

        $this->assertSame('trial', $this->storedStatus($bad));
        $this->assertSame('read_only', $this->storedStatus($good));
        $this->assertSame([(string) $bad->getKey()], array_column($result->failed, 'tenant_id'));
        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();
    }

    public function test_sweep_reports_trial_and_past_due_tenants_without_deadlines_as_needing_attention(): void
    {
        $noDeadline = $this->createTenant(['status' => 'trial', 'subscription_ends_at' => null, 'trial_ends_at' => null]);
        $dated = $this->createTenant(['status' => 'trial', 'subscription_ends_at' => null, 'trial_ends_at' => now()->addDays(3)]);

        $result = app(ApplyTenantLifecycleAction::class)->execute();

        $this->assertContains(['tenant_id' => (string) $noDeadline->getKey(), 'status' => 'trial'], $result->needsAttention);
        $this->assertNotContains(['tenant_id' => (string) $dated->getKey(), 'status' => 'trial'], $result->needsAttention);
        // behaviour unchanged: the undated tenant keeps full access.
        $this->assertSame('trial', $this->storedStatus($noDeadline));
        $this->getAs($noDeadline, '/api/v1/customers')->assertOk();
    }

    private static ?string $failingTenantId = null;

    private function domainOf(Tenant $tenant): string
    {
        return $tenant->getTenantKey().'.'.parse_url((string) config('app.url'), PHP_URL_HOST);
    }

    private function createTenantDomain(Tenant $tenant): void
    {
        $tenant->domains()->firstOrCreate(['domain' => $this->domainOf($tenant)]);
    }

    /** @param TestResponse<Response> $response */
    private function assertBlockedGeneric(TestResponse $response): void
    {
        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'subscription.access_blocked')
            ->assertJsonPath('message', __('subscription.errors.access_blocked_generic'))
            ->assertJsonMissingPath('details');
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function runSweep(): PendingCommand
    {
        return $this->artisan('tenants:apply-lifecycle');
    }

    private function storedStatus(Tenant $tenant): string
    {
        return (string) DB::table('tenants')->where('id', $tenant->getKey())->value('status');
    }

    /** @return TestResponse<Response> */
    private function getAs(Tenant $tenant, string $uri): TestResponse
    {
        return $this->getJson($uri, $this->tenantHeaders($tenant));
    }

    /** @return TestResponse<Response> */
    private function postAs(Tenant $tenant, string $uri): TestResponse
    {
        return $this->postJson($uri, [], $this->tenantHeaders($tenant));
    }

    /** @return TestResponse<Response> */
    private function putAs(Tenant $tenant, string $uri): TestResponse
    {
        return $this->putJson($uri, [], $this->tenantHeaders($tenant));
    }

    /** @return TestResponse<Response> */
    private function deleteAs(Tenant $tenant, string $uri): TestResponse
    {
        return $this->deleteJson($uri, [], $this->tenantHeaders($tenant));
    }

    /** @param TestResponse<Response> $response */
    private function assertWriteReachesTheController(TestResponse $response): void
    {
        $this->assertNotContains($response->status(), [403, 423], 'A write of a tenant with full access must not be refused by the lifecycle gate.');
        $response->assertJsonMissingPath('error_code');
    }

    /** @param TestResponse<Response> $response */
    private function assertReadOnlyRefusal(TestResponse $response): void
    {
        $response->assertStatus(423)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'subscription.read_only')
            ->assertJsonPath('details.status', 'read_only')
            // SELL-06: the message names the reason and the next step; the SPA links to /billing.
            ->assertJsonPath('details.action_path', '/billing');

        $reason = (string) $response->json('details.reason');
        $action = (string) $response->json('details.action');
        $this->assertContains($reason, ['trial_ended', 'payment_pending', 'subscription_ended', 'operator']);
        // An operator-set read-only (term still running) is a support matter, never "pay".
        $this->assertContains($action, $reason === 'operator' ? ['contact_support'] : ['pay_invoice', 'request_invoice']);
        $response->assertJsonPath('message', __('subscription.errors.read_only_guidance.'.$reason.'.'.$action));
    }

    /** @param TestResponse<Response> $response */
    private function assertBlocked(TestResponse $response, string $status): void
    {
        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'subscription.access_blocked')
            ->assertJsonPath('details.status', $status);
    }
}
