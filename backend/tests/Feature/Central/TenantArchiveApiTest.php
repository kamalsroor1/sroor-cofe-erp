<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Actions\Tenants\ArchiveTenantAction;
use App\Enums\CentralAuditEvent;
use App\Enums\TenantArchiveState;
use App\Enums\TenantBackupKind;
use App\Enums\TenantProvisioningStatus;
use App\Enums\TenantStatus;
use App\Jobs\ArchiveTenantJob;
use App\Models\CentralAuditLog;
use App\Models\Tenant;
use App\Models\TenantBackup;
use App\Support\Tenancy\TenantSuspensionReason;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * OPS-9 (CTO Q1): DELETE /api/v1/super-admin/tenants/{id} = asynchronous archive, and
 * POST /api/v1/super-admin/tenants/{id}/unarchive.
 *
 *  - 202 + archive_state queued + ArchiveTenantJob after the commit; the job takes and proves
 *    the final backup, then archives; nothing is dropped;
 *  - typed shop code + expected status (422), super_admin.tenants.manage (403), step-up (403),
 *    unknown tenant (404), throttle 6/min (429);
 *  - 409: archive_not_allowed (+ refusal audit), already_archived, archive_in_progress,
 *    tenant_purged, provisioning.workspace_not_ready, status_conflict;
 *  - the tenant page exposes the archive block; a purged tenant is 404 on the tenant API.
 */
final class TenantArchiveApiTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const PASSWORD = 'fixture-archive-password-not-a-secret-0000';

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        Storage::fake('google');
        Notification::fake();

        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sroor-archive-api-'.bin2hex(random_bytes(5));

        config([
            'backup.backup.password' => self::PASSWORD,
            'backup.tenants.disks' => ['google'],
            'backup.tenants.path_prefix' => 'sroor-backups',
            'backup.tenants.notify_mail' => null,
            'backup.tenants.temporary_directory' => $this->tmp,
            'backup.tenants.verify_after_upload' => true,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);

        parent::tearDown();
    }

    // ------------------------------------------------------------------ archive (DELETE)

    public function test_delete_queues_an_archive_and_answers_202(): void
    {
        Queue::fake();
        $id = $this->centralOnlyTenant(TenantStatus::Suspended);
        $operator = $this->centralSuperAdmin();

        $this->deleteJson($this->uri($id), $this->body($id, note: 'طلب العميل إنهاء الاشتراك'), $this->steppedUpCentralHeaders($operator))
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('super.tenant_archive_queued'))
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.status', TenantStatus::Suspended->value)
            ->assertJsonPath('data.archive.state', TenantArchiveState::Queued->value)
            ->assertJsonPath('data.archive.final_backup', null)
            ->assertJsonStructure(['data' => ['archive' => ['state', 'requested_at', 'error_code', 'archived_at', 'final_backup', 'purge_eligible_at', 'purged_at']]]);

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantStatus::Suspended->value, $fresh->status, 'The status only changes in the job, after the final backup.');
        $this->assertSame(TenantArchiveState::Queued, $fresh->archive_state);
        $this->assertNotNull($fresh->archive_requested_at);

        Queue::assertPushed(ArchiveTenantJob::class, fn (ArchiveTenantJob $job): bool => $job->tenantId === $id
            && $job->centralUserId === (int) $operator->getKey()
            && $job->expectedStatus === TenantStatus::Suspended->value
            && $job->note === 'طلب العميل إنهاء الاشتراك'
            && $job->tries === 1);

        $audit = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantArchiveRequested->value)->where('tenant_id', $id)->sole();
        $this->assertSame((int) $operator->getKey(), $audit->causer_id);
    }

    public function test_the_queued_job_proves_a_final_backup_then_archives_and_the_tenant_page_shows_it(): void
    {
        Queue::fake();
        $tenant = $this->createTenant(['status' => TenantStatus::Cancelled->value, 'suspension_reason' => TenantSuspensionReason::CustomerRequest->value]);
        $id = (string) $tenant->getKey();
        $operator = $this->centralSuperAdmin();

        $this->deleteJson($this->uri($id), $this->body($id, TenantStatus::Cancelled), $this->steppedUpCentralHeaders($operator))->assertStatus(202);

        $job = null;
        Queue::assertPushed(ArchiveTenantJob::class, function (ArchiveTenantJob $pushed) use (&$job): bool {
            $job = $pushed;

            return true;
        });
        $this->assertInstanceOf(ArchiveTenantJob::class, $job);
        $job->handle(app(ArchiveTenantAction::class));

        $fresh = Tenant::query()->findOrFail($id);
        $backup = TenantBackup::query()->where('tenant_id', $id)->sole();
        $this->assertSame(TenantStatus::Archived->value, $fresh->status);
        $this->assertSame(TenantStatus::Cancelled, $fresh->status_before_archive);
        $this->assertSame(TenantBackupKind::Final, $backup->kind);
        $this->assertSame((int) $backup->getKey(), $fresh->final_backup_id);
        $this->assertTrue($this->tenantDatabaseExists($tenant), 'The archive keeps the database.');

        $archived = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantArchived->value)->where('tenant_id', $id)->sole();
        $this->assertSame((int) $operator->getKey(), $archived->causer_id);

        $this->getJson($this->uri($id), $this->centralHeaders($operator))
            ->assertOk()
            ->assertJsonPath('data.archive.state', null)
            ->assertJsonPath('data.archive.final_backup.id', (int) $backup->getKey())
            ->assertJsonPath('data.archive.final_backup.disk', 'google')
            ->assertJsonPath('data.archive.final_backup.sha256_prefix', substr($backup->sha256, 0, 12))
            ->assertJsonPath('data.archive.archived_at', $fresh->archived_at?->toIso8601String())
            ->assertJsonPath('data.archive.purge_eligible_at', $fresh->archived_at?->copy()->addDays(30)->toIso8601String())
            ->assertJsonPath('data.archive.purged_at', null)
            ->assertJsonMissing(['path' => $backup->path]);
    }

    public function test_a_job_that_dies_marks_the_request_failed_without_touching_the_status(): void
    {
        $id = $this->centralOnlyTenant(TenantStatus::Suspended, ['archive_state' => TenantArchiveState::Running->value, 'archive_requested_at' => now()]);

        (new ArchiveTenantJob($id, null, TenantStatus::Suspended->value))->failed(new RuntimeException('worker killed'));

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantStatus::Suspended->value, $fresh->status);
        $this->assertSame(TenantArchiveState::Failed, $fresh->archive_state);
        $this->assertSame(ArchiveTenantAction::UNKNOWN, $fresh->archive_error_code);
    }

    public function test_validation_requires_the_typed_shop_code_and_the_expected_status(): void
    {
        Queue::fake();
        $id = $this->centralOnlyTenant(TenantStatus::Suspended);
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $this->deleteJson($this->uri($id), [], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['confirm_code', 'expected_status']);

        $this->deleteJson($this->uri($id), ['confirm_code' => strtoupper($id), 'expected_status' => 'suspended'], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['confirm_code'])
            ->assertJsonPath('errors.confirm_code.0', __('subscription.archive_errors.confirm_code_mismatch'));

        $this->deleteJson($this->uri($id), ['confirm_code' => $id, 'expected_status' => 'active'], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['expected_status']);

        $this->deleteJson($this->uri($id), $this->body($id, note: str_repeat('ن', 501)), $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['note']);

        $this->assertNull(Tenant::query()->findOrFail($id)->archive_state);
        Queue::assertNothingPushed();
    }

    public function test_guest_support_missing_step_up_and_unknown_tenant(): void
    {
        Queue::fake();
        $id = $this->centralOnlyTenant(TenantStatus::Suspended);

        $this->deleteJson($this->uri($id), $this->body($id))->assertStatus(401);

        $this->deleteJson($this->uri($id), $this->body($id), $this->steppedUpCentralHeaders($this->centralSupport()))
            ->assertStatus(403)
            ->assertJsonMissingPath('error_code');

        $this->deleteJson($this->uri($id), $this->body($id), $this->centralHeaders($this->centralSuperAdmin()))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'central_auth.step_up_required');

        $this->deleteJson($this->uri('qa-no-such-tenant'), $this->body('qa-no-such-tenant'), $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(404)
            ->assertJsonPath('message', __('super.tenant_not_found'));

        $this->assertNull(Tenant::query()->findOrFail($id)->archive_state);
        Queue::assertNothingPushed();
    }

    public function test_a_tenant_user_token_is_never_accepted(): void
    {
        Queue::fake();
        $tenant = $this->createTenant(['status' => TenantStatus::Suspended->value]);
        $id = (string) $tenant->getKey();

        $this->deleteJson($this->uri($id), $this->body($id), [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->tenantToken($tenant),
        ])->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_archive_from_a_working_status_is_409_and_the_refusal_is_audited(): void
    {
        Queue::fake();
        $id = $this->centralOnlyTenant(TenantStatus::Active);
        $operator = $this->centralSuperAdmin();

        $this->deleteJson($this->uri($id), $this->body($id), $this->steppedUpCentralHeaders($operator))
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'subscription.archive_not_allowed');

        $refusal = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantArchiveRefused->value)->where('tenant_id', $id)->sole();
        $this->assertSame((int) $operator->getKey(), $refusal->causer_id);
        $this->assertSame('active', $refusal->properties['from'] ?? null);
        $this->assertNull(Tenant::query()->findOrFail($id)->archive_state);
        Queue::assertNothingPushed();
    }

    public function test_conflicts_are_409_with_their_own_code(): void
    {
        Queue::fake();
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $archived = $this->centralOnlyTenant(TenantStatus::Archived);
        $this->deleteJson($this->uri($archived), $this->body($archived), $headers)
            ->assertStatus(409)->assertJsonPath('error_code', 'subscription.already_archived');

        $inFlight = $this->centralOnlyTenant(TenantStatus::Suspended, ['archive_state' => TenantArchiveState::Running->value, 'archive_requested_at' => now()->subMinutes(5)]);
        $this->deleteJson($this->uri($inFlight), $this->body($inFlight), $headers)
            ->assertStatus(409)->assertJsonPath('error_code', 'subscription.archive_in_progress');

        $purged = $this->centralOnlyTenant(TenantStatus::Suspended, ['purge_started_at' => now()]);
        $this->deleteJson($this->uri($purged), $this->body($purged), $headers)
            ->assertStatus(409)->assertJsonPath('error_code', 'subscription.tenant_purged');

        $pending = $this->centralOnlyTenant(TenantStatus::Suspended, ['provisioning_status' => TenantProvisioningStatus::Pending->value]);
        $this->deleteJson($this->uri($pending), $this->body($pending), $headers)
            ->assertStatus(409)->assertJsonPath('error_code', 'provisioning.workspace_not_ready');

        $stale = $this->centralOnlyTenant(TenantStatus::Suspended);
        $this->deleteJson($this->uri($stale), $this->body($stale, TenantStatus::Cancelled), $headers)
            ->assertStatus(409)->assertJsonPath('error_code', 'subscription.status_conflict');

        Queue::assertNothingPushed();
    }

    public function test_a_stale_in_flight_request_may_be_requested_again_and_a_failed_one_too(): void
    {
        Queue::fake();
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $staleAfter = (int) config('tenant_lifecycle.archive.stale_after_minutes');

        $stale = $this->centralOnlyTenant(TenantStatus::Suspended, ['archive_state' => TenantArchiveState::Queued->value, 'archive_requested_at' => now()->subMinutes($staleAfter + 1)]);
        $this->deleteJson($this->uri($stale), $this->body($stale), $headers)->assertStatus(202);

        $failed = $this->centralOnlyTenant(TenantStatus::Suspended, ['archive_state' => TenantArchiveState::Failed->value, 'archive_error_code' => ArchiveTenantAction::FINAL_BACKUP_FAILED]);
        $this->deleteJson($this->uri($failed), $this->body($failed), $headers)
            ->assertStatus(202)
            ->assertJsonPath('data.archive.error_code', null);

        Queue::assertPushed(ArchiveTenantJob::class, 2);
    }

    public function test_the_archive_endpoint_is_throttled(): void
    {
        Queue::fake();
        $id = $this->centralOnlyTenant(TenantStatus::Active);
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        for ($i = 0; $i < 6; $i++) {
            $this->deleteJson($this->uri($id), $this->body($id), $headers)->assertStatus(409);
        }

        $this->deleteJson($this->uri($id), $this->body($id), $headers)->assertStatus(429);
    }

    // ------------------------------------------------------------------ unarchive

    public function test_unarchive_restores_the_previous_status_and_clears_the_archive_block(): void
    {
        $tenant = $this->createTenant(['status' => TenantStatus::Archived->value]);
        $id = (string) $tenant->getKey();
        Tenant::query()->whereKey($id)->update([
            'status_before_archive' => TenantStatus::Cancelled->value,
            'archived_at' => now()->subDays(3),
            'final_backup_id' => 42,
        ]);
        $operator = $this->centralSuperAdmin();

        $this->postJson($this->uri($id).'/unarchive', ['note' => 'العميل رجع'], $this->steppedUpCentralHeaders($operator))
            ->assertOk()
            ->assertJsonPath('message', __('super.tenant_unarchived'))
            ->assertJsonPath('data.status', TenantStatus::Cancelled->value)
            ->assertJsonPath('data.archive.archived_at', null)
            ->assertJsonPath('data.archive.final_backup', null);

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantStatus::Cancelled->value, $fresh->status);
        $this->assertNull($fresh->archived_at);
        $this->assertNull($fresh->final_backup_id);

        $audit = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantUnarchived->value)->where('tenant_id', $id)->sole();
        $this->assertSame((int) $operator->getKey(), $audit->causer_id);
    }

    public function test_unarchive_refusals(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $active = $this->centralOnlyTenant(TenantStatus::Suspended);
        $this->postJson($this->uri($active).'/unarchive', [], $headers)
            ->assertStatus(409)->assertJsonPath('error_code', 'subscription.not_archived');

        $purged = $this->centralOnlyTenant(TenantStatus::Archived, ['purged_at' => now(), 'purge_started_at' => now()]);
        $this->postJson($this->uri($purged).'/unarchive', [], $headers)
            ->assertStatus(409)->assertJsonPath('error_code', 'subscription.tenant_purged');
        $this->assertSame(TenantStatus::Archived->value, Tenant::query()->findOrFail($purged)->status);

        $this->postJson($this->uri($active).'/unarchive', ['note' => str_repeat('x', 501)], $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['note']);

        $this->postJson($this->uri($purged).'/unarchive', [], $this->steppedUpCentralHeaders($this->centralSupport()))
            ->assertStatus(403);

        $this->postJson($this->uri($purged).'/unarchive', [], $this->centralHeaders($this->centralSuperAdmin()))
            ->assertStatus(403)->assertJsonPath('error_code', 'central_auth.step_up_required');

        $this->postJson($this->uri('qa-no-such-tenant').'/unarchive', [], $headers)->assertStatus(404);
    }

    // ------------------------------------------------------------------ purged tenants

    public function test_a_purged_tenant_is_not_found_on_the_tenant_api_and_never_provisioned(): void
    {
        // Active only so the request reaches a 200 first; the resolver refuses ANY purging tenant.
        $tenant = $this->createTenant();
        $other = $this->createTenant();
        $headers = $this->tenantHeaders($tenant);

        $this->getJson('/api/v1/auth/me', $headers)->assertOk();
        $this->assertTrue(Tenant::query()->provisioned()->whereKey($tenant->getKey())->exists());

        Tenant::query()->whereKey($tenant->getKey())->update(['purge_started_at' => now()]);

        $this->getJson('/api/v1/auth/me', $headers)
            ->assertStatus(404)
            ->assertJsonPath('message', __('auth.tenant_not_found'));
        $this->assertFalse(Tenant::query()->provisioned()->whereKey($tenant->getKey())->exists());
        $this->assertFalse(Tenant::query()->findOrFail($tenant->getKey())->isProvisioned());
        $this->assertTrue(Tenant::query()->provisioned()->whereKey($other->getKey())->exists(), 'Other tenants are unaffected.');
        $this->getJson('/api/v1/auth/me', $this->tenantHeaders($other))->assertOk();

        // Host-based resolution (domain record / subdomain) refuses it too. Last: the test
        // client keeps this host for later relative URLs.
        $this->getJson($this->tenantUrl($tenant, '/api/v1/auth/me'), ['Accept' => 'application/json', 'Authorization' => $headers['Authorization']])
            ->assertStatus(404);
    }

    public function test_the_tenants_list_rows_carry_the_archive_and_purge_state(): void
    {
        $plain = $this->centralOnlyTenant(TenantStatus::Suspended);
        $queued = $this->centralOnlyTenant(TenantStatus::Suspended, ['archive_state' => TenantArchiveState::Queued->value, 'archive_requested_at' => now()]);
        $archivedAt = now()->subDays(40)->startOfSecond();
        $purgedAt = now()->subDay()->startOfSecond();
        $purged = $this->centralOnlyTenant(TenantStatus::Archived, ['archived_at' => $archivedAt, 'purge_started_at' => $purgedAt, 'purged_at' => $purgedAt]);

        $rows = collect($this->getJson('/api/v1/super-admin/tenants?per_page=100', $this->centralHeaders($this->centralSupport()))
            ->assertOk()
            ->json('tenants.data'))->keyBy('id');

        foreach ([$plain, $queued, $purged] as $id) {
            $this->assertTrue($rows->has($id), "{$id} is listed.");
            foreach (['id', 'slug', 'status', 'provisioning_status', 'archive_state', 'archived_at', 'purged_at'] as $key) {
                $this->assertArrayHasKey($key, $rows[$id], "{$key} is on every row (existing keys unchanged).");
            }
        }

        $this->assertNull($rows[$plain]['archive_state']);
        $this->assertNull($rows[$plain]['purged_at']);
        $this->assertSame(TenantArchiveState::Queued->value, $rows[$queued]['archive_state']);
        $this->assertSame($archivedAt->toIso8601String(), $rows[$purged]['archived_at']);
        $this->assertSame($purgedAt->toIso8601String(), $rows[$purged]['purged_at']);
    }

    public function test_every_new_tenants_column_is_a_custom_column(): void
    {
        foreach (['archive_state', 'archive_requested_at', 'archive_error_code', 'archived_at', 'final_backup_id', 'purge_started_at', 'purged_at', 'purged_by'] as $column) {
            $this->assertContains($column, Tenant::getCustomColumns(), "{$column} must be a real column, never inside `data`.");
        }

        $id = $this->centralOnlyTenant(TenantStatus::Archived, ['archived_at' => now(), 'purged_by' => 'cli:ops']);
        $tenant = Tenant::query()->findOrFail($id);
        $tenant->forceFill(['archive_error_code' => 'unknown'])->save();

        $data = json_decode((string) Tenant::query()->toBase()->where('id', $id)->value('data'), true) ?: [];
        foreach (['archive_error_code', 'archived_at', 'purged_by'] as $column) {
            $this->assertArrayNotHasKey($column, $data, "{$column} leaked into `data`.");
        }
    }

    // ------------------------------------------------------------------ helpers

    private function uri(string $id): string
    {
        return '/api/v1/super-admin/tenants/'.$id;
    }

    /**
     * @return array<string, string>
     */
    private function body(string $id, TenantStatus $expected = TenantStatus::Suspended, ?string $note = null): array
    {
        return array_filter([
            'confirm_code' => $id,
            'expected_status' => $expected->value,
            'note' => $note,
        ], static fn (?string $value): bool => $value !== null);
    }

    /**
     * A central row only (no database), slug = id.
     *
     * @param  array<string, mixed>  $columns
     */
    private function centralOnlyTenant(TenantStatus $status, array $columns = []): string
    {
        $id = 'qa-api-'.Str::lower(Str::random(8));
        Tenant::query()->create([
            'id' => $id,
            'name' => 'محل '.$id,
            'slug' => $id,
            'email' => $id.'@sroor.test',
            'status' => $status->value,
            'status_changed_at' => now()->subDays(10),
            'suspension_reason' => $status === TenantStatus::Active ? null : TenantSuspensionReason::NonPayment->value,
            'tenancy_create_database' => false,
        ]);

        if ($columns !== []) {
            Tenant::query()->whereKey($id)->update($columns);
        }

        return $id;
    }
}
