<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\DTOs\CreateTenantDTO;
use App\Enums\CentralAuditEvent;
use App\Enums\CentralPermission;
use App\Enums\TenantProvisioningStatus;
use App\Http\Controllers\Api\Central\TenantProvisioningController;
use App\Http\Middleware\AuthenticateCentral;
use App\Http\Middleware\EnsureCentralContext;
use App\Http\Middleware\RequireRecentTwoFactor;
use App\Jobs\ProvisionTenantJob;
use App\Models\CentralAuditLog;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\ProvisionerMySQLDatabaseManager;
use App\Services\TenantProvisionerService;
use App\Support\PlatformHosts;
use App\Support\Tenancy\ProvisioningErrorCode;
use App\Support\Tenancy\ProvisioningFailure;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PDO;
use ReflectionParameter;
use RuntimeException;
use SensitiveParameter;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\Support\FakeProvisionerMySQLDatabaseManager;
use Tests\TenantTestCase;

/**
 * OPS-2: queued tenant provisioning.
 *
 * Tenants registered here go through TenantProvisionerService::provision() with the job
 * "parked" on a null queue, then the job is run explicitly with dispatchSync() (a real
 * SyncJob, so failed() runs exactly like on a worker). Their sqlite files under database/
 * and storage directories are removed after every test.
 */
final class ProvisionTenantJobTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const RETRY_ROUTE = 'api.super_admin.tenants.retry_provisioning';

    private const PASSWORD = 'secret-pass-1234';

    /** @var list<string> */
    private array $provisionedIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->beforeApplicationDestroyed(fn () => $this->dropProvisionedTenants());
    }

    public function test_store_tenant_commits_a_pending_tenant_and_queues_the_job_with_the_tenant_id_only(): void
    {
        Queue::fake();
        $plan = $this->plan();
        $slug = $this->newSlug();

        $this->postJson('/api/v1/super-admin/tenants', [
            'name' => 'Queued store',
            'slug' => $slug,
            'email' => $slug.'@queued.test',
            'phone' => '01000007301',
            'password' => self::PASSWORD,
            'plan_id' => $plan->id,
            'trial_days' => 14,
            'custom_domain' => $slug.'.custom.test',
        ], $this->centralHeaders($this->centralSuperAdmin()))
            ->assertStatus(201)
            ->assertJson(['success' => true]);

        $tenant = Tenant::query()->findOrFail($slug);
        $this->assertSame(TenantProvisioningStatus::Pending, $tenant->provisioning_status);
        $this->assertSame(0, $tenant->provisioning_attempts);
        $this->assertEqualsCanonicalizing(
            [PlatformHosts::tenantHost($slug), $slug.'.custom.test'],
            $tenant->domains()->pluck('domain')->all(),
        );
        $this->assertSame(1, Subscription::query()->where('tenant_id', $slug)->count());
        $this->assertSame(1, $this->auditCount(CentralAuditEvent::TenantProvisioningStarted, $slug));

        // No database yet: stancl's synchronous pipeline was switched off.
        $this->assertFalse($tenant->database()->manager()->databaseExists((string) $tenant->database()->getName()));

        // The hash stays on the tenant row (encrypted at rest); the job carries the id only.
        $hash = (string) TenantProvisionerService::storedPasswordHash($tenant);
        $this->assertTrue(Hash::check(self::PASSWORD, $hash));

        Queue::assertPushed(ProvisionTenantJob::class, function (ProvisionTenantJob $job) use ($slug, $hash): bool {
            $payload = serialize($job);

            return $job->tenantId === $slug
                && ! str_contains($payload, self::PASSWORD)
                && ! str_contains($payload, $hash);
        });
        Queue::assertPushed(ProvisionTenantJob::class, 1);

        // The central row never stores the plain password (nor the readable hash) either.
        $data = (string) DB::table('tenants')->where('id', $slug)->value('data');
        $this->assertStringNotContainsString(self::PASSWORD, $data);
        $this->assertStringNotContainsString($hash, $data);
    }

    public function test_job_creates_migrates_and_seeds_the_database_then_marks_the_tenant_ready(): void
    {
        $tenant = $this->registerParkedTenant();
        $id = (string) $tenant->getTenantKey();

        $this->runJob($tenant);

        $this->assertFalse(tenancy()->initialized, 'The job must end tenancy.');
        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantProvisioningStatus::Ready, $fresh->provisioning_status);
        $this->assertSame(1, $fresh->provisioning_attempts);
        $this->assertNull($fresh->provisioning_error_code);
        $this->assertNotNull($fresh->provisioning_started_at);
        $this->assertNotNull($fresh->provisioned_at);
        $this->assertSame($fresh->database()->getName(), $fresh->getInternal(TenantProvisionerService::CREATED_DATABASE_KEY), 'The created database is recorded by NAME.');
        $this->assertNull(TenantProvisionerService::storedPasswordHash($fresh), 'The retry seed is removed once ready.');
        $this->assertSame(1, $this->auditCount(CentralAuditEvent::TenantProvisioningSucceeded, $id));

        $state = $this->inTenant($fresh, function () use ($fresh): array {
            $admin = User::query()->where('email', $fresh->email)->firstOrFail();

            return [
                'admin_password_ok' => Hash::check(self::PASSWORD, (string) $admin->password),
                'admin_is_admin' => $admin->hasRole('admin', 'web'),
                'main_stores' => Store::query()->where('is_main', true)->count(),
                'role_guards' => Role::query()->distinct()->pluck('guard_name')->all(),
            ];
        });

        $this->assertTrue($state['admin_password_ok']);
        $this->assertTrue($state['admin_is_admin']);
        $this->assertSame(1, $state['main_stores']);
        $this->assertSame(['web'], $state['role_guards']);
    }

    public function test_job_is_idempotent_on_a_ready_tenant_and_on_a_redelivered_attempt(): void
    {
        $tenant = $this->registerParkedTenant();
        $id = (string) $tenant->getTenantKey();
        $this->runJob($tenant);
        $this->runJob($tenant); // ready: no-op

        $this->assertSame(1, Tenant::query()->findOrFail($id)->provisioning_attempts);

        // A redelivered attempt on a half-provisioned tenant reuses its own database.
        // (The seed is gone once ready: put it back as a crashed attempt would have left it.)
        $this->restoreSeed($id);
        Tenant::query()->whereKey($id)->update(['provisioning_status' => TenantProvisioningStatus::Running->value]);
        $this->runJob($tenant);

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantProvisioningStatus::Ready, $fresh->provisioning_status);
        $this->assertSame(2, $fresh->provisioning_attempts);

        $counts = $this->inTenant($fresh, static fn (): array => [
            'users' => User::query()->count(),
            'main_stores' => Store::query()->where('is_main', true)->count(),
            'admin_roles' => Role::query()->where('name', 'admin')->count(),
        ]);
        $this->assertSame(['users' => 1, 'main_stores' => 1, 'admin_roles' => 1], $counts);
    }

    public function test_an_existing_database_it_did_not_create_is_refused_and_never_touched(): void
    {
        $tenant = $this->registerParkedTenant();
        $id = (string) $tenant->getTenantKey();
        $path = database_path((string) $tenant->database()->getName());
        $this->createSqliteDatabase($path, withTable: true);

        $this->runJob($tenant); // permanent error: fails without retry, no exception

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantProvisioningStatus::Failed, $fresh->provisioning_status);
        $this->assertSame(ProvisioningErrorCode::DatabaseExists->value, $fresh->provisioning_error_code);
        $this->assertFileExists($path);
        $this->assertSame(['foreign_data'], $this->sqliteTables($path), 'A database we did not create is never modified.');
        $this->assertNull($fresh->getInternal(TenantProvisionerService::CREATED_DATABASE_KEY));
        $this->assertSame(1, $this->auditCount(CentralAuditEvent::TenantProvisioningFailed, $id));
    }

    public function test_an_explicitly_named_database_is_adopted_only_when_empty(): void
    {
        $empty = $this->registerParkedTenant(['tenancy_db_name' => 'tenant_'.$this->newSlug('qaadopt')]);
        $emptyPath = database_path((string) $empty->database()->getName());
        $this->createSqliteDatabase($emptyPath, withTable: false);

        $this->runJob($empty);

        $adopted = Tenant::query()->findOrFail($empty->getTenantKey());
        $this->assertSame(TenantProvisioningStatus::Ready, $adopted->provisioning_status);
        $this->assertNull($adopted->getInternal(TenantProvisionerService::CREATED_DATABASE_KEY), 'An adopted database is not ours to drop.');

        $used = $this->registerParkedTenant(['tenancy_db_name' => 'tenant_'.$this->newSlug('qaadopt')]);
        $usedPath = database_path((string) $used->database()->getName());
        $this->createSqliteDatabase($usedPath, withTable: true);

        $this->runJob($used);

        $refused = Tenant::query()->findOrFail($used->getTenantKey());
        $this->assertSame(TenantProvisioningStatus::Failed, $refused->provisioning_status);
        $this->assertSame(ProvisioningErrorCode::DatabaseExists->value, $refused->provisioning_error_code);
        $this->assertSame(['foreign_data'], $this->sqliteTables($usedPath));
    }

    public function test_a_failed_final_attempt_marks_failed_drops_only_its_own_database_and_keeps_the_central_row(): void
    {
        $this->app->instance(TenantProvisionerService::class, new class extends TenantProvisionerService
        {
            public function seedTenantDatabase(Tenant $tenant, string $passwordHash): void
            {
                throw new RuntimeException('seed exploded');
            }
        });

        $tenant = $this->registerParkedTenant();
        $id = (string) $tenant->getTenantKey();

        try {
            $this->runJob($tenant);
            $this->fail('The failing attempt must surface its exception.');
        } catch (ProvisioningFailure $failure) {
            $this->assertSame(ProvisioningErrorCode::SeedFailed, $failure->errorCode);
        }

        $this->assertFalse(tenancy()->initialized);
        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantProvisioningStatus::Failed, $fresh->provisioning_status);
        $this->assertSame(ProvisioningErrorCode::SeedFailed->value, $fresh->provisioning_error_code);
        $this->assertFalse($fresh->database()->manager()->databaseExists((string) $fresh->database()->getName()), 'The database the job created is dropped.');
        $this->assertNull($fresh->getInternal(TenantProvisionerService::CREATED_DATABASE_KEY));
        $this->assertNotNull(TenantProvisionerService::storedPasswordHash($fresh), 'The seed survives for a retry.');
        $this->assertSame(1, $fresh->domains()->count(), 'The central row and its domain stay.');

        $audit = CentralAuditLog::query()
            ->where('event', CentralAuditEvent::TenantProvisioningFailed->value)
            ->where('tenant_id', $id)
            ->sole();
        $this->assertSame('seed_failed', $audit->properties['error_code'] ?? null);
        $this->assertTrue($audit->properties['database_dropped'] ?? null);
        $this->assertStringNotContainsString('seed exploded', (string) json_encode($audit->properties));
    }

    public function test_a_workspace_that_is_not_ready_is_refused_with_503_on_header_and_host_resolution(): void
    {
        $pending = $this->registerParkedTenant();
        $host = PlatformHosts::tenantHost((string) $pending->slug);

        $this->getJson('/api/v1/ping', ['X-Tenant' => (string) $pending->getTenantKey()])
            ->assertStatus(503)
            ->assertHeader('Retry-After', '30')
            ->assertJson([
                'success' => false,
                'error_code' => 'provisioning.workspace_not_ready',
                'provisioning_status' => 'pending',
                'message' => __('provisioning.workspace_not_ready'),
            ]);

        $this->getJson('http://'.$host.'/api/v1/ping')
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'provisioning.workspace_not_ready');

        $this->postJson('http://'.$host.'/api/v1/auth/login', ['login' => 'x', 'password' => 'y'])
            ->assertStatus(503);

        Tenant::query()->whereKey($pending->getTenantKey())->update([
            'provisioning_status' => TenantProvisioningStatus::Failed->value,
            'provisioning_error_code' => ProvisioningErrorCode::MigrationFailed->value,
        ]);

        $failed = $this->getJson('http://localhost/api/v1/ping', ['X-Tenant' => (string) $pending->getTenantKey()])
            ->assertStatus(503)
            ->assertJsonPath('provisioning_status', 'failed');
        $this->assertFalse($failed->headers->has('Retry-After'));
        $this->assertStringNotContainsString('migration_failed', (string) $failed->getContent(), 'The error code is for operators only.');

        // Isolation: a ready tenant next to it is served normally.
        $ready = $this->createTenant();
        // Absolute central URL: after a request to a tenant host, url() keeps that host.
        $this->getJson('http://localhost/api/v1/ping', ['X-Tenant' => (string) $ready->getTenantKey()])->assertOk();
        $this->getJson($this->tenantUrl($ready, '/api/v1/ping'))->assertOk();
    }

    public function test_retry_is_only_allowed_after_a_failure_and_requeues_the_job(): void
    {
        $this->ensureRetryRoute();
        $tenant = $this->registerParkedTenant();
        $id = (string) $tenant->getTenantKey();
        $url = "/api/v1/super-admin/tenants/{$id}/retry-provisioning";
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $this->postJson($url)->assertStatus(401);
        $this->postJson($url, [], $this->centralHeaders($this->centralSupport()))->assertStatus(403);
        $this->postJson('/api/v1/super-admin/tenants/no-such-tenant/retry-provisioning', [], $headers)->assertStatus(404);

        // Pending (in flight): 409, nothing changes.
        $this->postJson($url, [], $headers)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'provisioning.retry_not_allowed');

        // A ready tenant (other tenant, isolation): 409 as well.
        $ready = $this->createTenant();
        $this->postJson("/api/v1/super-admin/tenants/{$ready->getTenantKey()}/retry-provisioning", [], $headers)
            ->assertStatus(409)
            ->assertJsonPath('provisioning_status', 'ready');

        Tenant::query()->whereKey($id)->update([
            'provisioning_status' => TenantProvisioningStatus::Failed->value,
            'provisioning_error_code' => ProvisioningErrorCode::MigrationFailed->value,
            'provisioning_attempts' => 3,
        ]);

        Queue::fake();
        $this->postJson($url, [], $headers)
            ->assertStatus(202)
            ->assertJson([
                'success' => true,
                'data' => ['tenant_id' => $id, 'provisioning_status' => 'pending', 'provisioning_attempts' => 3],
            ]);

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantProvisioningStatus::Pending, $fresh->provisioning_status);
        $this->assertNull($fresh->provisioning_error_code);
        Queue::assertPushed(ProvisionTenantJob::class, fn (ProvisionTenantJob $job): bool => $job->tenantId === $id);
        $this->assertTrue(Hash::check(self::PASSWORD, (string) TenantProvisionerService::storedPasswordHash($fresh)), 'The job reads the seed from the row.');

        $retryAudit = CentralAuditLog::query()
            ->where('event', CentralAuditEvent::TenantProvisioningStarted->value)
            ->where('tenant_id', $id)
            ->get()
            ->filter(static fn (CentralAuditLog $log): bool => ($log->properties['retry'] ?? false) === true);
        $this->assertCount(1, $retryAudit);
        $this->assertSame('migration_failed', $retryAudit->first()?->properties['previous_error_code'] ?? null);

        // Already pending again: a second retry is refused.
        $this->postJson($url, [], $headers)->assertStatus(409);
    }

    // ------------------------------------------------------------------------ W2 batch 4 review

    /** B1 scenario A: the database was renamed (to a foreign one) after attempt 1 created its own. */
    public function test_a_database_renamed_after_the_first_attempt_is_never_migrated_into_nor_dropped(): void
    {
        $tenant = $this->registerParkedTenant();
        $id = (string) $tenant->getTenantKey();

        // Attempt 1 created its database, then its worker died (the row stays `running`).
        $ownName = (string) $tenant->database()->getName();
        $ownPath = database_path($ownName);
        $this->createSqliteDatabase($ownPath, withTable: false);

        // Meanwhile the row was pointed at another, foreign database.
        $foreignName = 'tenant_'.$this->newSlug('qaforeign');
        $foreignPath = database_path($foreignName);
        $this->createSqliteDatabase($foreignPath, withTable: true);

        $row = Tenant::query()->findOrFail($id);
        $row->setInternal(TenantProvisionerService::CREATED_DATABASE_KEY, $ownName);
        $row->setInternal('db_name', $foreignName);
        $row->provisioning_status = TenantProvisioningStatus::Running;
        $row->save();

        $this->runJob($tenant); // permanent: the foreign database is not ours

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantProvisioningStatus::Failed, $fresh->provisioning_status);
        $this->assertSame(ProvisioningErrorCode::DatabaseExists->value, $fresh->provisioning_error_code);
        $this->assertSame(['foreign_data'], $this->sqliteTables($foreignPath), 'Never migrated into the foreign database.');
        $this->assertFileExists($foreignPath, 'Never dropped.');
        $this->assertFileExists($ownPath, 'Not the current name any more: left for an operator, never dropped blindly.');
        $this->assertSame($ownName, $fresh->getInternal(TenantProvisionerService::CREATED_DATABASE_KEY), 'The orphan stays recorded.');

        $audit = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantProvisioningFailed->value)->where('tenant_id', $id)->sole();
        $this->assertFalse($audit->properties['database_dropped'] ?? null);
    }

    /** B1 scenario B: the username was switched to a shared account after the job created its own user. */
    public function test_a_username_switched_to_a_shared_account_is_never_dropped(): void
    {
        $tenant = $this->registerParkedTenant(['tenancy_db_name' => 'tenant_'.$this->newSlug('qamysql')]);
        $id = (string) $tenant->getTenantKey();
        $name = (string) $tenant->database()->getName();
        $fake = $this->fakeMysqlProvisioner($id);
        $fake->databases[$name] = true;

        $row = Tenant::query()->findOrFail($id);
        $row->setInternal(TenantProvisionerService::CREATED_DATABASE_KEY, $name);
        $row->setInternal(TenantProvisionerService::CREATED_USER_KEY, ['username' => 'tu_created', 'host' => 'localhost']);
        $row->setInternal('db_username', 'sroor_app'); // the shared application account
        $row->setInternal('db_password', Tenant::sealDatabasePassword('shared-secret'));
        $row->provisioning_status = TenantProvisioningStatus::Running;
        $row->save();

        (new ProvisionTenantJob($id))->failed(new RuntimeException('worker lost'));

        $this->assertSame([], $fake->droppedUsers, 'Neither the shared account nor the no-longer-current user is dropped.');
        $this->assertSame([$name], $fake->deletedDatabases, 'The database is still ours (same name): dropped.');

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantProvisioningStatus::Failed, $fresh->provisioning_status);
        $this->assertSame('sroor_app', $fresh->getInternal('db_username'), 'The operator credentials are kept.');
        $this->assertSame('shared-secret', $fresh->getInternal('db_password'));
        $this->assertSame('tu_created', $fresh->getInternal(TenantProvisionerService::CREATED_USER_KEY)['username'] ?? null);
    }

    public function test_the_per_tenant_user_it_created_is_dropped_while_it_is_still_the_tenants_user(): void
    {
        $tenant = $this->registerParkedTenant(['tenancy_db_name' => 'tenant_'.$this->newSlug('qamysql')]);
        $id = (string) $tenant->getTenantKey();
        $name = (string) $tenant->database()->getName();
        $fake = $this->fakeMysqlProvisioner($id);
        $fake->databases[$name] = true;

        $row = Tenant::query()->findOrFail($id);
        $row->setInternal(TenantProvisionerService::CREATED_DATABASE_KEY, $name);
        $row->setInternal(TenantProvisionerService::CREATED_USER_KEY, ['username' => 'tu_created', 'host' => 'localhost']);
        $row->setInternal('db_username', 'tu_created');
        $row->setInternal('db_password', Tenant::sealDatabasePassword('generated'));
        $row->provisioning_status = TenantProvisioningStatus::Running;
        $row->save();

        (new ProvisionTenantJob($id))->failed(new RuntimeException('worker lost'));

        $this->assertSame(['tu_created@localhost'], $fake->droppedUsers);
        $fresh = Tenant::query()->findOrFail($id);
        $this->assertNull($fresh->getInternal('db_username'));
        $this->assertNull($fresh->getInternal('db_password'));
        $this->assertNull($fresh->getInternal(TenantProvisionerService::CREATED_USER_KEY));
        $this->assertNull($fresh->getInternal(TenantProvisionerService::CREATED_DATABASE_KEY));
    }

    /** S-sec: a failing CREATE USER never puts the generated password in the exception chain, trace or row. */
    public function test_a_failing_create_user_never_leaks_the_password(): void
    {
        $tenant = $this->registerParkedTenant(['tenancy_db_name' => 'tenant_'.$this->newSlug('qamysql')]);
        $id = (string) $tenant->getTenantKey();
        $fake = $this->fakeMysqlProvisioner($id);
        $fake->failCreateUser = true;

        $caught = null;
        try {
            $this->runJob($tenant);
        } catch (ProvisioningFailure $failure) {
            $caught = $failure;
        }

        $this->assertInstanceOf(ProvisioningFailure::class, $caught);
        $this->assertSame(ProvisioningErrorCode::DatabaseUserFailed, $caught->errorCode);
        $password = (string) $fake->seenPassword;
        $this->assertSame(40, strlen($password));

        $this->assertNull($caught->getPrevious(), 'The QueryException (SQL with the password) is never chained.');
        $this->assertStringNotContainsString($password, $caught->getMessage());
        $this->assertStringNotContainsString($password, $caught->getTraceAsString());
        $this->assertStringNotContainsString($password, (string) $caught);
        $this->assertStringContainsString('SQLSTATE HY000', $caught->getMessage());
        $this->assertStringContainsString('driver code 1396', $caught->getMessage());

        // S-sec3: while the user was being created, the row held the password ENCRYPTED only.
        $this->assertStringNotContainsString($password, (string) $fake->rowDataAtCreateUser);

        // failed(): the user it created (still the tenant's) and its database are dropped.
        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantProvisioningStatus::Failed, $fresh->provisioning_status);
        $this->assertSame(ProvisioningErrorCode::DatabaseUserFailed->value, $fresh->provisioning_error_code);
        $this->assertCount(1, $fake->droppedUsers);
        $this->assertStringStartsWith('tu_', $fake->droppedUsers[0]);
        $this->assertSame([(string) $fresh->database()->getName()], $fake->deletedDatabases);

        foreach ([
            [ProvisionerMySQLDatabaseManager::class, 'createUser', 'password'],
            [TenantProvisionerService::class, 'seedTenantDatabase', 'passwordHash'],
            [Tenant::class, 'sealDatabasePassword', 'password'],
        ] as [$class, $method, $parameter]) {
            $this->assertNotEmpty(
                (new ReflectionParameter([$class, $method], $parameter))->getAttributes(SensitiveParameter::class),
                "{$class}::{$method}(\${$parameter}) must be #[SensitiveParameter]",
            );
        }
    }

    /** B2: a worker killed on timeout never releases WithoutOverlapping; failed() does. */
    public function test_failed_force_releases_the_overlap_lock_a_killed_worker_left_behind(): void
    {
        $tenant = $this->registerParkedTenant();
        $id = (string) $tenant->getTenantKey();

        // Same key as the middleware: prefix + job class + ':' + tenant id.
        $key = 'laravel-queue-overlap:'.ProvisionTenantJob::class.':'.$id;
        $this->assertSame($key, (new WithoutOverlapping($id))->getLockKey(new ProvisionTenantJob($id)));
        $this->assertTrue(Cache::lock($key, 960)->get(), 'The dead worker holds the overlap lock.');

        (new ProvisionTenantJob($id))->failed(new MaxAttemptsExceededException('timed out'));

        $this->assertTrue(Cache::lock($key, 960)->get(), 'failed() released the lock.');
        Cache::lock($key)->forceRelease();

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantProvisioningStatus::Failed, $fresh->provisioning_status);
        $this->assertSame(ProvisioningErrorCode::Timeout->value, $fresh->provisioning_error_code);

        // The super-admin retry is now really executed (sync queue), not silently dropped.
        $this->ensureRetryRoute();
        $this->postJson("/api/v1/super-admin/tenants/{$id}/retry-provisioning", [], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(202);

        $this->assertSame(TenantProvisioningStatus::Ready, Tenant::query()->findOrFail($id)->provisioning_status);
    }

    /** B2: a pending/running provisioning idle for longer than the unique window may be retried. */
    public function test_a_stale_pending_or_running_provisioning_can_be_retried(): void
    {
        $this->ensureRetryRoute();
        $tenant = $this->registerParkedTenant();
        $id = (string) $tenant->getTenantKey();
        $url = "/api/v1/super-admin/tenants/{$id}/retry-provisioning";
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $window = TenantProvisionerService::provisioningWindowSeconds();
        $this->assertSame(3600, $window);

        // Running, last activity within the window: in flight, 409.
        DB::table('tenants')->where('id', $id)->update([
            'provisioning_status' => TenantProvisioningStatus::Running->value,
            'provisioning_started_at' => now()->subHours(3),
            'updated_at' => now()->subSeconds($window - 60),
        ]);
        $this->postJson($url, [], $headers)->assertStatus(409)->assertJsonPath('error_code', 'provisioning.retry_not_allowed');

        // A lost dispatch: idle for longer than the window, its unique lock still held.
        DB::table('tenants')->where('id', $id)->update(['updated_at' => now()->subSeconds($window + 60)]);
        $this->assertTrue((new UniqueLock(app(Repository::class)))->acquire(new ProvisionTenantJob($id)));

        Queue::fake();
        $this->postJson($url, [], $headers)
            ->assertStatus(202)
            ->assertJsonPath('data.provisioning_status', 'pending');

        Queue::assertPushed(ProvisionTenantJob::class, fn (ProvisionTenantJob $job): bool => $job->tenantId === $id);
        $retry = CentralAuditLog::query()
            ->where('event', CentralAuditEvent::TenantProvisioningStarted->value)
            ->where('tenant_id', $id)
            ->get()
            ->filter(static fn (CentralAuditLog $log): bool => ($log->properties['retry'] ?? false) === true)
            ->sole();
        $this->assertSame('running', $retry->properties['previous_status'] ?? null);

        // Pending again and fresh: refused.
        $this->postJson($url, [], $headers)->assertStatus(409);

        // A stale PENDING one (dispatch lost before any worker claimed it) is retryable too.
        DB::table('tenants')->where('id', $id)->update(['updated_at' => now()->subSeconds($window + 60), 'provisioning_started_at' => null]);
        $this->postJson($url, [], $headers)->assertStatus(202);
    }

    // ------------------------------------------------------------------------

    private function plan(): Plan
    {
        return Plan::query()->create([
            'name' => 'Provisioning plan',
            'slug' => 'prov-'.Str::lower(Str::random(6)),
            'price_monthly' => '100.000',
            'price_yearly' => '1000.000',
            'max_users' => 5,
            'max_stores' => 1,
            'max_items' => 100,
            'max_invoices_per_month' => 1000,
            'is_active' => true,
            'sort_order' => 1,
            'features' => [],
        ]);
    }

    private function newSlug(string $prefix = 'qaprov'): string
    {
        $slug = $prefix.Str::lower(Str::random(10));
        $this->provisionedIds[] = $slug;

        return $slug;
    }

    /**
     * Register through the real service, with the job parked on a null queue.
     *
     * @param  array<string, string>  $overrides  extra CreateTenantDTO fields (tenancy_db_name)
     */
    private function registerParkedTenant(array $overrides = []): Tenant
    {
        $slug = $this->newSlug();
        config([
            'queue.connections.qa_parked' => ['driver' => 'null'],
            'tenancy.provisioning.queue_connection' => 'qa_parked',
        ]);

        try {
            $tenant = app(TenantProvisionerService::class)->provision(CreateTenantDTO::fromArray(array_merge([
                'name' => 'Parked '.$slug,
                'slug' => $slug,
                'email' => $slug.'@parked.test',
                'phone' => '0100000'.random_int(1000, 9999),
                'plan_id' => $this->plan()->id,
                'password' => self::PASSWORD,
                'trial_days' => 14,
            ], $overrides)));
        } finally {
            config(['tenancy.provisioning.queue_connection' => null]);
        }

        // The parked job never runs, so its unique lock would block a later dispatch.
        (new UniqueLock(app(Repository::class)))->release(new ProvisionTenantJob($slug));

        $this->assertSame(TenantProvisioningStatus::Pending, $tenant->provisioning_status);

        return $tenant;
    }

    /**
     * A ProvisionerMySQLDatabaseManager that records instead of running DDL, and a template
     * connection with the mysql driver so the job picks it (never connected to).
     */
    private function fakeMysqlProvisioner(string $tenantId): FakeProvisionerMySQLDatabaseManager
    {
        $fake = new FakeProvisionerMySQLDatabaseManager;
        $fake->tenantId = $tenantId;
        $this->app->instance(ProvisionerMySQLDatabaseManager::class, $fake);

        config([
            'database.connections.qa_fake_mysql' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '1', 'database' => 'qa_unused', 'username' => 'qa', 'password' => ''],
            'tenancy.database.template_tenant_connection' => 'qa_fake_mysql',
            'tenancy.provisioning.per_tenant_db_user' => true,
        ]);

        return $fake;
    }

    private function runJob(Tenant $tenant): void
    {
        ProvisionTenantJob::dispatchSync((string) $tenant->getTenantKey());
    }

    /** Put a first-admin seed back on a row (as a crashed attempt leaves it). */
    private function restoreSeed(string $id): void
    {
        $tenant = Tenant::query()->findOrFail($id);
        $tenant->setInternal(TenantProvisionerService::SEED_KEY, Crypt::encryptString((string) json_encode(['password_hash' => Hash::make(self::PASSWORD)])));
        $tenant->save();
    }

    private function auditCount(CentralAuditEvent $event, string $tenantId): int
    {
        return CentralAuditLog::query()->where('event', $event->value)->where('tenant_id', $tenantId)->count();
    }

    private function createSqliteDatabase(string $path, bool $withTable): void
    {
        $this->assertFileDoesNotExist($path);
        touch($path);

        if ($withTable) {
            $pdo = new PDO('sqlite:'.$path);
            $pdo->exec('CREATE TABLE foreign_data (id INTEGER PRIMARY KEY)');
            $pdo = null;
        }
    }

    /** @return list<string> */
    private function sqliteTables(string $path): array
    {
        $pdo = new PDO('sqlite:'.$path);
        $statement = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        $this->assertNotFalse($statement);
        $tables = $statement->fetchAll(PDO::FETCH_COLUMN);
        $statement = null;
        $pdo = null;

        return array_values(array_map('strval', $tables));
    }

    /**
     * 4B owns routes/central.php and adds the retry line; if it is missing, register the
     * identical route (same group middleware, permission, limiter and step-up) for this test.
     */
    private function ensureRetryRoute(): void
    {
        if (Route::has(self::RETRY_ROUTE)) {
            return;
        }

        Route::middleware('api')->prefix('api/v1/super-admin')->name('api.super_admin.')
            ->middleware([EnsureCentralContext::class, AuthenticateCentral::class])
            ->group(function (): void {
                Route::post('/tenants/{id}/retry-provisioning', [TenantProvisioningController::class, 'retry'])
                    ->middleware(['can:'.CentralPermission::TenantsManage->value, 'throttle:6,1', RequireRecentTwoFactor::class])
                    ->name('tenants.retry_provisioning');
            });

        app('router')->getRoutes()->refreshNameLookups();
    }

    private function dropProvisionedTenants(): void
    {
        $this->endTenancy();
        DB::purge('tenant');
        gc_collect_cycles();

        foreach (array_unique($this->provisionedIds) as $id) {
            foreach (File::glob(database_path('tenant_'.$id.'*')) ?: [] as $file) {
                @unlink($file);
            }

            $storage = storage_path().'/'.config('tenancy.filesystem.suffix_base', 'tenant').$id;
            if (is_dir($storage)) {
                File::deleteDirectory($storage);
            }
        }
    }
}
