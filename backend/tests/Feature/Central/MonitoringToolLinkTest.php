<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralAuditEvent;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * The console's monitoring links (Pulse, Horizon, Telescope) all go through the single-use
 * signed /telescope-access bridge, which opens the `central_web` session and lands on the
 * chosen dashboard. Pulse used to answer 403 to every operator because its gate read the
 * tenant-side default guard; it now reads `central_web` like Telescope and Horizon.
 */
final class MonitoringToolLinkTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const URL = '/api/v1/super-admin/telescope-link';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        // Production shape: Telescope off, Pulse on, Horizon on Redis queues.
        config(['telescope.enabled' => false, 'pulse.enabled' => true, 'queue.default' => 'redis']);
    }

    private function issue(CentralUser $operator, string $tool): string
    {
        $url = (string) $this->postJson(self::URL, ['tool' => $tool], $this->centralHeaders($operator))
            ->assertOk()
            ->json('data.url');

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $url;
    }

    public function test_a_pulse_link_opens_the_operator_session_and_lands_on_pulse(): void
    {
        $operator = $this->centralSuperAdmin();

        $url = $this->issue($operator, 'pulse');
        $this->assertStringContainsString('t=pulse', $url);

        $this->get($url)->assertRedirect('/pulse');
        $this->assertAuthenticatedAs($operator, 'central_web');
        $this->assertGuest('web');
    }

    public function test_a_horizon_link_lands_on_horizon(): void
    {
        $this->get($this->issue($this->centralSuperAdmin(), 'horizon'))->assertRedirect('/horizon');
    }

    public function test_the_link_records_which_tool_was_opened(): void
    {
        $this->issue($this->centralSuperAdmin(), 'pulse');

        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::TelescopeLinkIssued->value)->sole();
        $this->assertSame('pulse', $log->properties['tool'] ?? null);
    }

    public function test_a_telescope_link_is_refused_where_telescope_is_off(): void
    {
        $this->postJson(self::URL, ['tool' => 'telescope'], $this->centralHeaders($this->centralSuperAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors('tool');

        // An old console build that sends no tool asked for Telescope too.
        $this->postJson(self::URL, [], $this->centralHeaders($this->centralSuperAdmin()))
            ->assertStatus(422);
    }

    public function test_an_unknown_tool_is_a_validation_error(): void
    {
        $this->postJson(self::URL, ['tool' => 'phpmyadmin'], $this->centralHeaders($this->centralSuperAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors('tool');
    }

    public function test_the_tool_cannot_be_swapped_after_signing(): void
    {
        $url = $this->issue($this->centralSuperAdmin(), 'pulse');
        $swapped = str_replace('t=pulse', 't=horizon', $url);
        $this->assertNotSame($url, $swapped);

        $this->get($swapped)->assertStatus(403);
        $this->assertGuest('central_web');
    }

    public function test_support_and_guests_get_no_link(): void
    {
        $this->postJson(self::URL, ['tool' => 'pulse'], $this->centralHeaders($this->centralSupport()))->assertForbidden();
        $this->postJson(self::URL, ['tool' => 'pulse'])->assertUnauthorized();
    }

    public function test_me_lists_the_dashboards_this_environment_serves(): void
    {
        $this->getJson('/api/v1/super-admin/auth/me', $this->centralHeaders($this->centralSuperAdmin()))
            ->assertOk()
            ->assertJsonPath('data.monitoring_tools', ['pulse', 'horizon']);

        $this->getJson('/api/v1/super-admin/auth/me', $this->centralHeaders($this->centralSupport()))
            ->assertOk()
            ->assertJsonPath('data.monitoring_tools', []);
    }

    public function test_view_pulse_reads_the_operator_session_not_the_default_guard(): void
    {
        // Pulse's Authorize middleware asks the gate with the default guard's user (none here).
        $this->assertFalse(Gate::check('viewPulse'));

        // actingAs() also makes central_web the default guard; switch back to `web` so the
        // gate gets no user, as in production, and must read the central_web session itself.
        $this->actingAs($this->centralSuperAdmin(), 'central_web');
        Auth::shouldUse('web');
        $this->assertTrue(Gate::check('viewPulse'));
    }

    public function test_horizon_is_not_offered_where_queues_are_not_on_redis(): void
    {
        config(['queue.default' => 'database']);

        $this->getJson('/api/v1/super-admin/auth/me', $this->centralHeaders($this->centralSuperAdmin()))
            ->assertOk()
            ->assertJsonPath('data.monitoring_tools', ['pulse']);
    }

    public function test_view_pulse_denies_support_and_tenant_users(): void
    {
        $this->actingAs($this->centralSupport(), 'central_web');
        Auth::shouldUse('web');
        $this->assertFalse(Gate::check('viewPulse'));

        $admin = $this->tenantAdmin($this->createTenant());
        $this->assertFalse(Gate::forUser($admin)->check('viewPulse'));
    }
}
