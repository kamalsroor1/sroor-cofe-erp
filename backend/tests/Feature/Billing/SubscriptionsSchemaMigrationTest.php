<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Contracts\TenantProvisionerInterface;
use App\DTOs\CreateTenantDTO;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * ENTI-1.3: the central `subscriptions` table becomes a billing-grade record.
 *
 * - `status` gains pending_payment / expired, `billing_cycle` gains biennial
 *   (both ENUM -> string(20), validated by the App\Enums\Billing casts);
 * - `amount` DECIMAL(10,2) -> DECIMAL(12,3);
 * - new: currency (EGP), price_locked, is_founder, founder_price_until;
 * - grace_ends_at / trial_extended_at are NOT here (they live on `tenants`, IDEN-3.2);
 * - down() maps the new values onto the old ones BEFORE narrowing the columns back.
 */
#[Group('billing')]
#[Group('mysql')]
final class SubscriptionsSchemaMigrationTest extends TenantTestCase
{
    private const MIGRATION = 'migrations/2026_10_10_200200_update_subscriptions_table_for_billing.php';

    private const NEW_COLUMNS = ['currency', 'price_locked', 'is_founder', 'founder_price_until'];

    public function test_subscriptions_table_has_the_billing_columns(): void
    {
        $schema = Schema::connection($this->centralConnectionName());

        foreach (self::NEW_COLUMNS as $column) {
            $this->assertTrue($schema->hasColumn('subscriptions', $column), "subscriptions.{$column} is missing.");
        }

        foreach (['grace_ends_at', 'trial_extended_at'] as $column) {
            $this->assertFalse($schema->hasColumn('subscriptions', $column), "subscriptions.{$column} belongs on tenants (IDEN-3.2), not here.");
        }

        $columns = collect($schema->getColumns('subscriptions'))->keyBy('name');

        $this->assertTrue((bool) $columns['founder_price_until']['nullable']);
        foreach (['currency', 'price_locked', 'is_founder', 'status', 'billing_cycle', 'amount'] as $column) {
            $this->assertFalse((bool) $columns[$column]['nullable'], "subscriptions.{$column} must be NOT NULL.");
        }

        if (in_array($schema->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->assertSame('decimal(12,3)', strtolower((string) $columns['amount']['type']));
            $this->assertSame('varchar(20)', strtolower((string) $columns['status']['type']));
            $this->assertSame('varchar(20)', strtolower((string) $columns['billing_cycle']['type']));
        }

        $this->assertTrue($schema->hasIndex('subscriptions', ['tenant_id', 'status']), 'tenant/status lookups need an index.');
        $this->assertTrue($schema->hasIndex('subscriptions', ['status', 'billing_cycle']), 'revenue reports filter on status + cycle.');
    }

    public function test_new_statuses_and_cycle_are_saved_and_cast_to_billing_enums(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->plan('cast');
        $until = now()->addYear()->startOfSecond();

        foreach ([SubscriptionStatus::PendingPayment, SubscriptionStatus::Expired] as $status) {
            $created = Subscription::query()->create($this->subscriptionAttributes($tenant, $plan, [
                'status' => $status,
                'billing_cycle' => BillingCycle::Biennial,
                'amount' => '26982.125',
                'currency' => 'EGP',
                'price_locked' => true,
                'is_founder' => true,
                'founder_price_until' => $until,
            ]));

            $fresh = Subscription::query()->findOrFail($created->id);

            $this->assertSame($status, $fresh->status);
            $this->assertSame(BillingCycle::Biennial, $fresh->billing_cycle);
            $this->assertSame('26982.125', $fresh->amount);
            $this->assertSame('EGP', $fresh->currency);
            $this->assertTrue($fresh->price_locked);
            $this->assertTrue($fresh->is_founder);
            $this->assertNotNull($fresh->founder_price_until);
            $this->assertTrue($until->equalTo($fresh->founder_price_until));

            $this->assertSame($status->value, DB::table('subscriptions')->where('id', $created->id)->value('status'));
            $this->assertSame('biennial', DB::table('subscriptions')->where('id', $created->id)->value('billing_cycle'));
        }
    }

    public function test_new_columns_have_safe_defaults(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->plan('defaults');

        $id = DB::table('subscriptions')->insertGetId([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'amount' => '449.000',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fresh = Subscription::query()->findOrFail($id);

        $this->assertSame(SubscriptionStatus::Trialing, $fresh->status);
        $this->assertSame(BillingCycle::Monthly, $fresh->billing_cycle);
        $this->assertSame('EGP', $fresh->currency);
        $this->assertFalse($fresh->price_locked);
        $this->assertFalse($fresh->is_founder);
        $this->assertNull($fresh->founder_price_until);
        $this->assertSame('449.000', $fresh->amount);
    }

    public function test_is_active_uses_the_enum_status(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->plan('active-check');

        $active = Subscription::query()->create($this->subscriptionAttributes($tenant, $plan, ['status' => SubscriptionStatus::Active]));
        $pending = Subscription::query()->create($this->subscriptionAttributes($tenant, $plan, ['status' => SubscriptionStatus::PendingPayment]));
        $lapsed = Subscription::query()->create($this->subscriptionAttributes($tenant, $plan, [
            'status' => SubscriptionStatus::Active,
            'ends_at' => now()->subDay(),
        ]));

        $this->assertTrue($active->fresh()?->isActive());
        $this->assertFalse($pending->fresh()?->isActive());
        $this->assertFalse($lapsed->fresh()?->isActive());
        $this->assertSame($active->id, $tenant->fresh()?->activeSubscription()->value('id'));
    }

    public function test_subscription_reads_the_central_db_inside_a_tenant(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->plan('central-pinned');
        $subscription = Subscription::query()->create($this->subscriptionAttributes($tenant, $plan, [
            'status' => SubscriptionStatus::PendingPayment,
        ]));
        $central = $this->centralConnectionName();

        $seen = $this->inTenant($tenant, fn (): array => [
            'default' => DB::getDefaultConnection(),
            'connection' => (new Subscription)->getConnectionName(),
            'status' => Subscription::query()->find($subscription->id)?->status,
            'plan_slug' => Subscription::query()->find($subscription->id)?->plan?->slug,
        ]);

        $this->assertNotSame($central, $seen['default'], 'Tenancy must switch the default connection for this test to mean anything.');
        $this->assertSame($central, $seen['connection']);
        $this->assertSame(SubscriptionStatus::PendingPayment, $seen['status']);
        $this->assertSame('central-pinned', $seen['plan_slug']);
    }

    public function test_down_maps_new_values_before_narrowing_and_up_round_trips(): void
    {
        // Central-only tenant row (no tenant DB): on MySQL the ALTERs below implicitly commit
        // the RefreshDatabase transaction, so every row created here is removed in finally.
        $tenant = $this->centralOnlyTenantId();
        $plan = $this->plan('rollback');

        try {
            $pending = Subscription::query()->create($this->subscriptionAttributes($tenant, $plan, [
                'status' => SubscriptionStatus::PendingPayment,
                'billing_cycle' => BillingCycle::Biennial,
                'amount' => '1799.500',
                'is_founder' => true,
                'price_locked' => true,
            ]));
            $expired = Subscription::query()->create($this->subscriptionAttributes($tenant, $plan, [
                'status' => SubscriptionStatus::Expired,
            ]));
            $active = Subscription::query()->create($this->subscriptionAttributes($tenant, $plan, [
                'status' => SubscriptionStatus::Active,
                'billing_cycle' => BillingCycle::Yearly,
            ]));

            $this->migrateDown();

            foreach (self::NEW_COLUMNS as $column) {
                $this->assertFalse(Schema::hasColumn('subscriptions', $column), "down() must drop subscriptions.{$column}.");
            }

            $rows = DB::table('subscriptions')->whereIn('id', [$pending->id, $expired->id, $active->id])->get()->keyBy('id');

            $this->assertSame('past_due', $rows[$pending->id]->status, 'pending_payment rolls back to past_due (unpaid).');
            $this->assertSame('yearly', $rows[$pending->id]->billing_cycle, 'biennial rolls back to yearly.');
            $this->assertSame(0, bccomp((string) $rows[$pending->id]->amount, '1799.500', 2));
            $this->assertSame('cancelled', $rows[$expired->id]->status, 'expired rolls back to cancelled.');
            $this->assertSame('active', $rows[$active->id]->status);
            $this->assertSame('yearly', $rows[$active->id]->billing_cycle);

            $this->migrateUp();

            foreach (self::NEW_COLUMNS as $column) {
                $this->assertTrue(Schema::hasColumn('subscriptions', $column), "up() must re-add subscriptions.{$column}.");
            }

            $roundTrip = Subscription::query()->findOrFail($pending->id);
            $this->assertSame(SubscriptionStatus::PastDue, $roundTrip->status);
            $this->assertSame('EGP', $roundTrip->currency);
            $this->assertFalse($roundTrip->is_founder, 'Founder flags are not recoverable after a rollback (documented).');

            // After up() again the widened values are accepted.
            $roundTrip->update(['status' => SubscriptionStatus::Expired, 'billing_cycle' => BillingCycle::Biennial]);
            $this->assertSame('expired', DB::table('subscriptions')->where('id', $pending->id)->value('status'));
        } finally {
            $this->ensureMigrated();
            DB::table('subscriptions')->where('tenant_id', $tenant)->delete();
            DB::table('tenants')->where('id', $tenant)->delete();
            DB::table('plans')->where('id', $plan->id)->delete();
        }
    }

    public function test_provisioner_records_the_initial_subscription_with_billing_fields(): void
    {
        $plan = $this->plan('provisioned', ['price_monthly' => '449.000']);
        $slug = self::$harnessTenantPrefix.Str::lower(Str::random(12));

        $tenant = null;

        try {
            $tenant = app(TenantProvisionerInterface::class)->provision(new CreateTenantDTO(
                name: 'مستأجر تجريبي',
                slug: $slug,
                email: $slug.'@harness.test',
                phone: null,
                planId: $plan->id,
                password: 'secret1234',
                trialDays: 14,
            ));

            $subscription = Subscription::query()->where('tenant_id', $tenant->id)->sole();

            $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
            $this->assertSame(BillingCycle::Monthly, $subscription->billing_cycle);
            $this->assertSame('449.000', $subscription->amount);
            $this->assertSame('EGP', $subscription->currency);
            $this->assertFalse($subscription->price_locked);
            $this->assertFalse($subscription->is_founder);
            $this->assertNull($subscription->founder_price_until);
        } finally {
            if ($tenant instanceof Tenant) {
                $this->dropProvisionedTenant($tenant);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function plan(string $slug, array $overrides = []): Plan
    {
        return Plan::query()->create(array_merge([
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
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function subscriptionAttributes(Tenant|string $tenant, Plan $plan, array $overrides = []): array
    {
        return array_merge([
            'tenant_id' => $tenant instanceof Tenant ? $tenant->id : $tenant,
            'plan_id' => $plan->id,
            'billing_cycle' => BillingCycle::Monthly,
            'status' => SubscriptionStatus::Active,
            'amount' => '449.000',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ], $overrides);
    }

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

    private function migrateUp(): void
    {
        $migration = $this->migration();
        if (! method_exists($migration, 'up')) {
            $this->fail('The ENTI-1.3 migration has no up().');
        }

        $migration->up();
    }

    private function migrateDown(): void
    {
        $migration = $this->migration();
        if (! method_exists($migration, 'down')) {
            $this->fail('The ENTI-1.3 migration has no down().');
        }

        $migration->down();
    }

    private function migration(): object
    {
        $migration = require database_path(self::MIGRATION);
        if (! is_object($migration)) {
            $this->fail('The ENTI-1.3 migration file must return a migration instance.');
        }

        return $migration;
    }

    private function ensureMigrated(): void
    {
        if (! Schema::hasColumn('subscriptions', 'currency')) {
            $this->migrateUp();
        }
    }
}
