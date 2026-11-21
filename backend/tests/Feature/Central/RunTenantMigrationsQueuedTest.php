<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Actions\Tenants\RunTenantMigrationsAction;
use App\Enums\CentralAuditEvent;
use App\Jobs\RunTenantMigrationsJob;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * OPS-25 item 4: the super-admin "run migrations" button queues RunTenantMigrationsJob instead
 * of running `tenants:migrate` inside the HTTP request (120 s limit, killed mid-ALTER on a big
 * shop). 202 + run id, a `tenant_migrations_queued` audit line, and the job's
 * `tenant_migrations_run` line with the same run id in every outcome.
 */
final class RunTenantMigrationsQueuedTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private CentralUser $operator;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        $this->operator = $this->centralSuperAdmin();
        $this->tenant = $this->createTenant();
    }

    private function url(): string
    {
        return "/api/v1/super-admin/tenants/{$this->tenant->getTenantKey()}/run-migrations";
    }

    public function test_it_answers_202_with_a_run_id_and_dispatches_the_job_after_the_commit(): void
    {
        Queue::fake();

        $response = $this->postJson($this->url(), [], $this->steppedUpCentralHeaders($this->operator))
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('message', __('super.migrations_queued'));
        $runId = (string) $response->json('data.run_id');
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $runId);

        Queue::assertPushed(RunTenantMigrationsJob::class, function (RunTenantMigrationsJob $job) use ($runId): bool {
            return $job->tenantId === (string) $this->tenant->getTenantKey()
                && $job->centralUserId === (int) $this->operator->getKey()
                && $job->runId === $runId
                && $job->afterCommit === true;
        });

        $queued = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantMigrationsQueued->value)->sole();
        $this->assertSame((string) $this->tenant->getTenantKey(), $queued->tenant_id);
        $this->assertSame((int) $this->operator->getKey(), (int) $queued->causer_id);
        $this->assertSame($runId, $queued->properties['run_id'] ?? null);
        $this->assertSame(0, CentralAuditLog::query()->where('event', CentralAuditEvent::TenantMigrationsRun->value)->count(), 'Nothing ran inside the request.');
    }

    public function test_unauthenticated_is_401_and_queues_nothing(): void
    {
        Queue::fake();

        $this->postJson($this->url())->assertUnauthorized();

        Queue::assertNothingPushed();
    }

    public function test_an_operator_without_tenants_manage_is_403_and_queues_nothing(): void
    {
        Queue::fake();

        $this->postJson($this->url(), [], $this->steppedUpCentralHeaders($this->centralSupport()))->assertForbidden();

        Queue::assertNothingPushed();
        $this->assertSame(0, CentralAuditLog::query()->where('event', CentralAuditEvent::TenantMigrationsQueued->value)->count());
    }

    public function test_the_job_migrates_the_tenant_and_audits_the_outcome_with_the_run_id(): void
    {
        (new RunTenantMigrationsJob((string) $this->tenant->getTenantKey(), (int) $this->operator->getKey(), 'run-ok'))
            ->handle(app(RunTenantMigrationsAction::class));

        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantMigrationsRun->value)->sole();
        $this->assertSame('succeeded', $log->properties['status'] ?? null);
        $this->assertSame(0, $log->properties['exit_code'] ?? null);
        $this->assertSame('run-ok', $log->properties['run_id'] ?? null);
        $this->assertSame((int) $this->operator->getKey(), (int) $log->causer_id);
        $this->assertFalse(tenancy()->initialized, 'The job leaves no tenant initialised.');
    }

    public function test_a_second_run_while_one_holds_the_tenant_lock_is_skipped_and_audited(): void
    {
        $lock = Cache::lock(RunTenantMigrationsAction::lockKey((string) $this->tenant->getTenantKey()), 60);
        $this->assertTrue($lock->get());

        try {
            $ran = app(RunTenantMigrationsAction::class)->execute($this->tenant, $this->operator, 'run-busy');
        } finally {
            $lock->release();
        }

        $this->assertFalse($ran);
        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantMigrationsRun->value)->sole();
        $this->assertSame('skipped', $log->properties['status'] ?? null);
        $this->assertSame('already_running', $log->properties['reason'] ?? null);
        $this->assertSame('run-busy', $log->properties['run_id'] ?? null);
    }

    public function test_a_timed_out_or_killed_job_is_audited_as_failed(): void
    {
        (new RunTenantMigrationsJob((string) $this->tenant->getTenantKey(), (int) $this->operator->getKey(), 'run-killed'))
            ->failed(new RuntimeException('timed out'));

        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantMigrationsRun->value)->sole();
        $this->assertSame('failed', $log->properties['status'] ?? null);
        $this->assertSame('job_failed', $log->properties['reason'] ?? null);
        $this->assertSame('run-killed', $log->properties['run_id'] ?? null);
        $this->assertSame((string) $this->tenant->getTenantKey(), $log->tenant_id);
    }

    public function test_the_job_runs_once_on_the_long_lane_not_the_provisioning_worker(): void
    {
        config(['tenant_lifecycle.archive.queue_connection' => 'long', 'tenant_lifecycle.archive.queue' => 'long']);
        $job = new RunTenantMigrationsJob('t', null, 'r');

        $this->assertSame(1, $job->tries);
        $this->assertTrue($job->failOnTimeout);
        $this->assertSame('long', $job->connection);
        $this->assertSame('long', $job->queue);
        $this->assertGreaterThan($job->timeout, (int) config('queue.connections.long.retry_after'));
        $this->assertGreaterThan($job->timeout, (int) config('horizon.defaults.supervisor-long.timeout'));
    }

    public function test_a_tenant_deleted_before_the_run_is_audited_as_skipped(): void
    {
        (new RunTenantMigrationsJob('qa-missing-tenant', (int) $this->operator->getKey(), 'run-gone'))
            ->handle(app(RunTenantMigrationsAction::class));

        $log = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantMigrationsRun->value)->sole();
        $this->assertSame('skipped', $log->properties['status'] ?? null);
        $this->assertSame('tenant_missing', $log->properties['reason'] ?? null);
        $this->assertSame('qa-missing-tenant', $log->tenant_id);
    }

    public function test_a_failing_migration_leaves_no_tenant_initialised(): void
    {
        $tenant = $this->tenant;
        Artisan::shouldReceive('call')->once()->andReturnUsing(function () use ($tenant): never {
            tenancy()->initialize($tenant); // what stancl's runForMultiple leaves behind on a throw
            throw new RuntimeException('migration blew up');
        });

        $ran = app(RunTenantMigrationsAction::class)->execute($this->tenant, $this->operator, 'run-throws');

        $this->assertFalse($ran);
        $this->assertFalse(tenancy()->initialized);
        $this->assertSame('failed', CentralAuditLog::query()->where('event', CentralAuditEvent::TenantMigrationsRun->value)->sole()->properties['status'] ?? null);
    }

    public function test_an_unknown_tenant_is_404_and_queues_nothing(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/super-admin/tenants/qa-no-such-tenant/run-migrations', [], $this->steppedUpCentralHeaders($this->operator))
            ->assertNotFound();

        Queue::assertNothingPushed();
    }
}
