<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Contracts\TenantProvisionerInterface;
use App\DTOs\CreateTenantDTO;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantLifecycleEvent;
use App\Support\Tenancy\TenantSuspensionReason;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * IDEN-3.2: lifecycle-grade central `tenants` + `tenant_lifecycle_events`.
 *
 * - `tenants.status` ENUM -> string(20) (all seven App\Enums\TenantStatus values fit);
 * - lifecycle facts are REAL columns (stancl custom columns), never inside `data`;
 * - `tenant_lifecycle_events` has no FK on tenant_id (history outlives the tenant);
 * - down() maps the new statuses onto the legacy ENUM before narrowing it back;
 * - the provisioner: a trial has subscription_ends_at = null; trial_days = 0 starts
 *   read-only with a pending_payment subscription (CTO W1 Q4).
 */
#[Group('mysql')]
final class TenantLifecycleSchemaMigrationTest extends TenantTestCase
{
    private const MIGRATION = 'migrations/2026_10_10_110000_update_tenants_table_for_lifecycle.php';

    private const EVENTS_MIGRATION = 'migrations/2026_10_10_110010_create_tenant_lifecycle_events_table.php';

    private const LIFECYCLE_COLUMNS = [
        'status_changed_at',
        'grace_ends_at',
        'trial_extended_at',
        'read_only_since',
        'suspension_reason',
        'status_before_archive',
    ];

    public function test_tenants_table_has_the_lifecycle_columns_and_indexes(): void
    {
        $schema = Schema::connection($this->centralConnectionName());
        $columns = collect($schema->getColumns('tenants'))->keyBy('name');

        foreach (self::LIFECYCLE_COLUMNS as $column) {
            $this->assertTrue($columns->has($column), "tenants.{$column} is missing.");
            $this->assertTrue((bool) $columns[$column]['nullable'], "tenants.{$column} must be nullable (existing rows).");
        }

        $this->assertFalse((bool) $columns['status']['nullable']);
        $this->assertSame('trial', trim((string) $columns['status']['default'], "'\""));

        if (in_array($schema->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->assertSame('varchar(20)', strtolower((string) $columns['status']['type']));
            $this->assertSame('varchar(30)', strtolower((string) $columns['suspension_reason']['type']));
            $this->assertSame('varchar(20)', strtolower((string) $columns['status_before_archive']['type']));
        }

        $this->assertTrue($schema->hasIndex('tenants', ['status', 'status_changed_at']), 'the sweep selects by status + since.');
        $this->assertTrue($schema->hasIndex('tenants', ['trial_ends_at']), 'trial reminders / sweep filter on trial_ends_at.');
        $this->assertTrue($schema->hasIndex('tenants', ['subscription_ends_at']), 'renewal reminders / sweep filter on subscription_ends_at.');

        $this->assertContains('status_before_archive', Tenant::getCustomColumns());
        foreach (self::LIFECYCLE_COLUMNS as $column) {
            $this->assertContains($column, Tenant::getCustomColumns(), "{$column} must be a stancl custom column, not part of `data`.");
        }
    }

    public function test_lifecycle_events_table_has_no_foreign_key_and_is_indexed(): void
    {
        $schema = Schema::connection($this->centralConnectionName());

        $this->assertTrue($schema->hasTable('tenant_lifecycle_events'));
        foreach (['tenant_id', 'from_status', 'to_status', 'actor', 'central_user_id', 'reason', 'note', 'properties', 'created_at'] as $column) {
            $this->assertTrue($schema->hasColumn('tenant_lifecycle_events', $column), "tenant_lifecycle_events.{$column} is missing.");
        }
        $this->assertFalse($schema->hasColumn('tenant_lifecycle_events', 'updated_at'), 'append-only: no updated_at.');

        $this->assertSame([], $schema->getForeignKeys('tenant_lifecycle_events'), 'No FK (and no cascade): the history must outlive the tenant row.');
        $this->assertTrue($schema->hasIndex('tenant_lifecycle_events', ['tenant_id', 'created_at']));
        $this->assertTrue($schema->hasIndex('tenant_lifecycle_events', ['to_status', 'created_at']));
    }

    public function test_every_tenant_status_is_stored_and_lifecycle_values_live_in_real_columns(): void
    {
        $id = $this->centralOnlyTenantId();
        $at = CarbonImmutable::parse('2026-11-01 10:00:00');

        foreach (TenantStatus::cases() as $status) {
            DB::table('tenants')->where('id', $id)->update(['status' => $status->value]);
            $this->assertSame($status->value, DB::table('tenants')->where('id', $id)->value('status'));
        }

        $tenant = Tenant::query()->findOrFail($id);
        $tenant->forceFill([
            'status' => TenantStatus::Archived->value,
            'status_changed_at' => $at,
            'grace_ends_at' => $at->addDays(7),
            'trial_extended_at' => $at->subDay(),
            'read_only_since' => $at->subDays(40),
            'suspension_reason' => TenantSuspensionReason::Violation,
            'status_before_archive' => TenantStatus::Suspended,
        ])->save();

        $row = DB::table('tenants')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('violation', $row->suspension_reason);
        $this->assertSame('suspended', $row->status_before_archive);
        $this->assertNotNull($row->status_changed_at);
        $this->assertNotNull($row->grace_ends_at);

        $data = json_decode((string) ($row->data ?? '[]'), true) ?: [];
        foreach (self::LIFECYCLE_COLUMNS as $column) {
            $this->assertArrayNotHasKey($column, $data, "{$column} leaked into the stancl `data` JSON.");
        }

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(TenantStatus::Archived, $fresh->lifecycleStatus());
        $this->assertSame(TenantSuspensionReason::Violation, $fresh->suspension_reason);
        $this->assertSame(TenantStatus::Suspended, $fresh->status_before_archive);
        $this->assertTrue($at->equalTo($fresh->status_changed_at));
    }

    public function test_lifecycle_snapshot_maps_the_stored_columns(): void
    {
        $id = $this->centralOnlyTenantId();
        $changed = CarbonImmutable::parse('2026-10-01 00:00:00');

        DB::table('tenants')->where('id', $id)->update([
            'status' => TenantStatus::Suspended->value,
            'status_changed_at' => $changed,
            'trial_ends_at' => $changed->subDays(30),
            'trial_extended_at' => $changed->subDays(35),
            'grace_ends_at' => null,
            'suspension_reason' => TenantSuspensionReason::Violation->value,
        ]);

        $snapshot = Tenant::query()->findOrFail($id)->lifecycleSnapshot(hasEverPaid: true);

        $this->assertSame(TenantStatus::Suspended, $snapshot->status);
        $this->assertNotNull($snapshot->statusChangedAt);
        $this->assertTrue($changed->equalTo($snapshot->statusChangedAt));
        $this->assertNotNull($snapshot->trialEndsAt);
        $this->assertNotNull($snapshot->trialExtendedAt);
        $this->assertNull($snapshot->graceEndsAt);
        $this->assertNull($snapshot->subscriptionEndsAt);
        $this->assertTrue($snapshot->hasEverPaid);
        $this->assertSame(TenantSuspensionReason::Violation, $snapshot->suspensionReason);

        // An unknown legacy value never grants access: it is read as suspended.
        DB::table('tenants')->where('id', $id)->update(['status' => 'expired']);
        $legacy = Tenant::query()->findOrFail($id);
        $this->assertNull($legacy->lifecycleStatus());
        $this->assertSame(TenantStatus::Suspended, $legacy->lifecycleSnapshot()->status);
    }

    public function test_legacy_suspension_gate_stays_at_least_as_strict_without_trial_subscription_dates(): void
    {
        $id = $this->centralOnlyTenantId();
        $gate = function (array $columns) use ($id): bool {
            DB::table('tenants')->where('id', $id)->update(array_merge([
                'trial_ends_at' => null,
                'subscription_ends_at' => null,
            ], $columns));

            return Tenant::query()->findOrFail($id)->isSuspended();
        };

        $this->assertFalse($gate(['status' => 'trial', 'trial_ends_at' => now()->addDay()]));
        $this->assertTrue($gate(['status' => 'trial', 'trial_ends_at' => now()->subMinute()]), 'An ended trial is still blocked by the legacy resolver gate.');
        $this->assertFalse($gate(['status' => 'active', 'subscription_ends_at' => now()->addMonth()]));
        $this->assertTrue($gate(['status' => 'active', 'subscription_ends_at' => now()->subMinute()]));
        $this->assertFalse($gate(['status' => 'read_only']), 'read-only keeps reads; writes are refused by IDEN-3.5.');

        foreach (['suspended', 'cancelled', 'archived', 'expired'] as $blocked) {
            $this->assertTrue($gate(['status' => $blocked]), "{$blocked} must be blocked.");
        }
    }

    public function test_lifecycle_event_model_is_central_and_append_only(): void
    {
        $tenant = $this->createTenant();
        $central = $this->centralConnectionName();

        $event = TenantLifecycleEvent::query()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'from_status' => TenantStatus::Active->value,
            'to_status' => TenantStatus::Suspended->value,
            'actor' => TenantLifecycleActor::System->value,
            'reason' => TenantSuspensionReason::NonPayment->value,
        ]);

        $seen = $this->inTenant($tenant, fn (): array => [
            'default' => DB::getDefaultConnection(),
            'connection' => (new TenantLifecycleEvent)->getConnectionName(),
            'to' => TenantLifecycleEvent::query()->find($event->id)?->to_status,
            'count' => Tenant::query()->findOrFail($tenant->getKey())->lifecycleEvents()->count(),
        ]);

        $this->assertNotSame($central, $seen['default']);
        $this->assertSame($central, $seen['connection']);
        $this->assertSame(TenantStatus::Suspended, $seen['to']);
        $this->assertSame(1, $seen['count']);

        $this->expectException(LogicException::class);
        $event->update(['note' => 'changed']);
    }

    public function test_events_survive_the_tenant_row(): void
    {
        $id = $this->centralOnlyTenantId();

        TenantLifecycleEvent::query()->create([
            'tenant_id' => $id,
            'from_status' => TenantStatus::Cancelled->value,
            'to_status' => TenantStatus::Archived->value,
            'actor' => TenantLifecycleActor::System->value,
        ]);

        DB::table('tenants')->where('id', $id)->delete();

        $this->assertSame(1, TenantLifecycleEvent::query()->where('tenant_id', $id)->count());
    }

    public function test_down_maps_new_statuses_restores_the_enum_and_up_round_trips(): void
    {
        $ids = [
            'past_due' => $this->centralOnlyTenantId(),
            'read_only' => $this->centralOnlyTenantId(),
            'archived' => $this->centralOnlyTenantId(),
            'trial' => $this->centralOnlyTenantId(),
        ];

        try {
            foreach ($ids as $status => $id) {
                DB::table('tenants')->where('id', $id)->update([
                    'status' => $status,
                    'status_changed_at' => now(),
                    'suspension_reason' => 'violation',
                ]);
            }

            $this->migrateDown();

            foreach (self::LIFECYCLE_COLUMNS as $column) {
                $this->assertFalse(Schema::hasColumn('tenants', $column), "down() must drop tenants.{$column}.");
            }

            $rows = DB::table('tenants')->whereIn('id', array_values($ids))->pluck('status', 'id');
            $this->assertSame('active', $rows[$ids['past_due']], 'past_due rolls back to active (grace period).');
            $this->assertSame('suspended', $rows[$ids['read_only']], 'read_only rolls back to suspended (no writes).');
            $this->assertSame('cancelled', $rows[$ids['archived']], 'archived rolls back to cancelled.');
            $this->assertSame('trial', $rows[$ids['trial']]);

            // The legacy ENUM is back: a new status is rejected again.
            $rejected = false;
            try {
                DB::table('tenants')->where('id', $ids['trial'])->update(['status' => 'read_only']);
            } catch (QueryException) {
                $rejected = true;
            }
            $this->assertTrue($rejected, 'After down() the status column must be the legacy ENUM again.');

            $this->migrateUp();

            foreach (self::LIFECYCLE_COLUMNS as $column) {
                $this->assertTrue(Schema::hasColumn('tenants', $column), "up() must re-add tenants.{$column}.");
            }

            DB::table('tenants')->where('id', $ids['trial'])->update(['status' => 'read_only']);
            $this->assertSame('read_only', DB::table('tenants')->where('id', $ids['trial'])->value('status'));
        } finally {
            $this->ensureMigrated();
            DB::table('tenants')->whereIn('id', array_values($ids))->delete();
        }
    }

    public function test_events_table_migration_is_reversible(): void
    {
        try {
            $this->migrateDown(self::EVENTS_MIGRATION);
            $this->assertFalse(Schema::hasTable('tenant_lifecycle_events'));

            $this->migrateUp(self::EVENTS_MIGRATION);
            $this->assertTrue(Schema::hasTable('tenant_lifecycle_events'));
        } finally {
            if (! Schema::hasTable('tenant_lifecycle_events')) {
                $this->migrateUp(self::EVENTS_MIGRATION);
            }
        }
    }

    public function test_provisioned_trial_has_no_paid_period(): void
    {
        $plan = $this->plan('lifecycle-trial');

        $this->withProvisionedTenant($plan, 14, function (Tenant $tenant): void {
            $fresh = Tenant::query()->findOrFail($tenant->getKey());

            $this->assertSame(TenantStatus::Trial, $fresh->lifecycleStatus());
            $this->assertNull($fresh->subscription_ends_at, 'A trial has no paid period (IDEN-3.2).');
            $this->assertNotNull($fresh->trial_ends_at);
            $this->assertNotNull($fresh->status_changed_at, 'The lifecycle clock starts at provisioning.');
            $this->assertNull($fresh->read_only_since);

            $subscription = Subscription::query()->where('tenant_id', $tenant->getKey())->sole();
            $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        });
    }

    public function test_provisioned_tenant_without_trial_is_read_only_pending_payment(): void
    {
        $plan = $this->plan('lifecycle-pending');

        $this->withProvisionedTenant($plan, 0, function (Tenant $tenant): void {
            $fresh = Tenant::query()->findOrFail($tenant->getKey());

            $this->assertSame(TenantStatus::ReadOnly, $fresh->lifecycleStatus(), 'trial_days = 0 starts read-only (CTO W1 Q4).');
            $this->assertNull($fresh->trial_ends_at);
            $this->assertNull($fresh->subscription_ends_at, 'Nothing is paid yet.');
            $this->assertNotNull($fresh->read_only_since);
            $this->assertNotNull($fresh->status_changed_at);

            $subscription = Subscription::query()->where('tenant_id', $tenant->getKey())->sole();
            $this->assertSame(SubscriptionStatus::PendingPayment, $subscription->status);
            $this->assertFalse($subscription->isActive());
        });
    }

    /**
     * @param  callable(Tenant): void  $assertions
     */
    private function withProvisionedTenant(Plan $plan, int $trialDays, callable $assertions): void
    {
        $slug = self::$harnessTenantPrefix.Str::lower(Str::random(12));
        $tenant = null;

        try {
            $tenant = app(TenantProvisionerInterface::class)->provision(new CreateTenantDTO(
                name: 'مستأجر دورة حياة',
                slug: $slug,
                email: $slug.'@harness.test',
                phone: null,
                planId: $plan->id,
                password: 'secret1234',
                trialDays: $trialDays,
            ));

            $assertions($tenant);
        } finally {
            if ($tenant instanceof Tenant) {
                $this->dropProvisionedTenant($tenant);
            }
        }
    }

    private function plan(string $slug): Plan
    {
        return Plan::query()->create([
            'name' => 'Plan '.$slug,
            'slug' => $slug,
            'price_monthly' => '449.000',
            'price_yearly' => '4490.000',
            'max_users' => 3,
            'max_stores' => 1,
            'max_items' => 3000,
            'max_invoices_per_month' => 6000,
            'max_storage_mb' => 2048,
            'features' => ['pos.access' => true],
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 1,
        ]);
    }

    /** A central `tenants` row without a tenant database (raw insert: no TenantCreated pipeline). */
    private function centralOnlyTenantId(): string
    {
        $id = self::$harnessTenantPrefix.'central'.Str::lower(Str::random(8));

        DB::table('tenants')->insert([
            'id' => $id,
            'name' => $id,
            'slug' => $id,
            'email' => $id.'@harness.test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function dropProvisionedTenant(Tenant $tenant): void
    {
        $this->endTenancy();
        DB::purge('tenant');

        $manager = $tenant->database()->manager();
        $database = $tenant->database()->getName();

        for ($attempt = 0; $attempt < 2 && $manager->databaseExists($database); $attempt++) {
            gc_collect_cycles();
            $manager->deleteDatabase($tenant);
        }

        $storage = storage_path(config('tenancy.filesystem.suffix_base', 'tenant').$tenant->id);
        if (is_dir($storage)) {
            File::deleteDirectory($storage);
        }
    }

    private function migrateUp(string $path = self::MIGRATION): void
    {
        $migration = $this->migration($path);
        if (! method_exists($migration, 'up')) {
            $this->fail("{$path} has no up().");
        }

        $migration->up();
    }

    private function migrateDown(string $path = self::MIGRATION): void
    {
        $migration = $this->migration($path);
        if (! method_exists($migration, 'down')) {
            $this->fail("{$path} has no down().");
        }

        $migration->down();
    }

    private function migration(string $path): object
    {
        $migration = require database_path($path);
        if (! is_object($migration)) {
            $this->fail("{$path} must return a migration instance.");
        }

        return $migration;
    }

    private function ensureMigrated(): void
    {
        if (! Schema::hasColumn('tenants', 'status_changed_at')) {
            $this->migrateUp();
        }
    }
}
