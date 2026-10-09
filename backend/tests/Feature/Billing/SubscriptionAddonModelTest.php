<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\AddonType;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\SubscriptionAddonStatus;
use App\Enums\Billing\SubscriptionStatus;
use App\Exceptions\Billing\AddonPricingException;
use App\Exceptions\Billing\SubscriptionAddonException;
use App\Models\Addon;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * ENTI-1.5: central `subscription_addons` — what a tenant actually bought.
 *
 * - `unit_price` is the stored per-unit price for the line's cycle (DECIMAL(12,3) string);
 * - `lineTotal()` = unit_price x quantity with bcmath, scale 3;
 * - `activeFor()` only returns lines of that tenant that are `active` AND inside their
 *   [starts_at, ends_at) window (NULL ends_at = open-ended, NULL starts_at = not started),
 *   and only while the parent subscription is active (CTO W1 Q3; covered in depth by
 *   SubscriptionAddonParentRuleTest).
 */
#[Group('billing')]
#[Group('mysql')]
final class SubscriptionAddonModelTest extends TenantTestCase
{
    private const MIGRATION = 'migrations/2026_10_10_200320_create_subscription_addons_table.php';

    /** Replaces the table's subscription FK with the tenant-scoped composite one. */
    private const COMPOSITE_FK_MIGRATION = 'migrations/2026_10_10_200550_add_tenant_scoped_foreign_key_to_subscription_addons.php';

    public function test_table_has_the_designed_columns_and_indexes(): void
    {
        $schema = Schema::connection($this->centralConnectionName());

        foreach ([
            'id', 'subscription_id', 'tenant_id', 'addon_id', 'quantity', 'unit_price',
            'billing_cycle', 'status', 'starts_at', 'ends_at', 'created_at', 'updated_at',
        ] as $column) {
            $this->assertTrue($schema->hasColumn('subscription_addons', $column), "subscription_addons.{$column} is missing.");
        }

        $columns = collect($schema->getColumns('subscription_addons'))->keyBy('name');
        foreach (['subscription_id', 'tenant_id', 'addon_id', 'quantity', 'unit_price', 'billing_cycle', 'status'] as $column) {
            $this->assertFalse((bool) $columns[$column]['nullable'], "subscription_addons.{$column} must be NOT NULL.");
        }
        $this->assertTrue((bool) $columns['starts_at']['nullable'], 'A pending_payment line has not started yet.');
        $this->assertTrue((bool) $columns['ends_at']['nullable'], 'NULL ends_at = open-ended / one-time service.');

        if (in_array($schema->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->assertSame('decimal(12,3)', strtolower((string) $columns['unit_price']['type']));
        }

        $this->assertTrue($schema->hasIndex('subscription_addons', ['tenant_id', 'status']), 'activeFor() filters on tenant + status.');
        $this->assertTrue($schema->hasIndex('subscription_addons', ['subscription_id']));
        $this->assertTrue($schema->hasIndex('subscription_addons', ['addon_id']));
        $this->assertTrue($schema->hasIndex('subscription_addons', ['status', 'ends_at']), 'expiry sweeps filter on status + ends_at.');
    }

    public function test_defaults_casts_and_relations(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant();
        $addon = $this->addon('addon.store', '249.000');

        $line = SubscriptionAddon::query()->create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $tenant->id,
            'addon_id' => $addon->id,
            'unit_price' => '249',
        ]);
        $line = SubscriptionAddon::query()->findOrFail($line->id);

        $this->assertSame(1, $line->quantity);
        $this->assertSame('249.000', $line->unit_price);
        $this->assertSame(BillingCycle::Monthly, $line->billing_cycle);
        $this->assertSame(SubscriptionAddonStatus::PendingPayment, $line->status, 'A new line is unpaid until activation.');
        $this->assertNull($line->starts_at);
        $this->assertNull($line->ends_at);
        $this->assertFalse($line->isActive());

        $this->assertSame($subscription->id, $line->subscription->id);
        $this->assertSame($addon->id, $line->addon->id);
        $this->assertSame($tenant->id, $line->tenant?->id);
    }

    public function test_line_total_is_unit_price_times_quantity_with_bcmath(): void
    {
        $line = new SubscriptionAddon(['quantity' => 3, 'unit_price' => '212.000']);
        $this->assertSame('636.000', $line->lineTotal());

        $line = new SubscriptionAddon(['quantity' => 7, 'unit_price' => '0.125']);
        $this->assertSame('0.875', $line->lineTotal());

        $line = new SubscriptionAddon(['quantity' => 1, 'unit_price' => '1500']);
        $this->assertSame('1500.000', $line->lineTotal());

        // Large values stay exact (no float drift).
        $line = new SubscriptionAddon(['quantity' => 1000, 'unit_price' => '999999.999']);
        $this->assertSame('999999999.000', $line->lineTotal());
    }

    public function test_line_total_rejects_a_quantity_below_one(): void
    {
        $line = new SubscriptionAddon(['quantity' => 0, 'unit_price' => '249.000']);

        try {
            $line->lineTotal();
            $this->fail('A quantity below one must not be priced.');
        } catch (AddonPricingException $e) {
            $this->assertSame('invalid_quantity', $e->reason());
            $this->assertSame(__('billing.addon_pricing.invalid_quantity', ['quantity' => '0']), $e->getMessage());
        }
    }

    public function test_active_for_excludes_lines_that_are_not_active(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant();
        [, $otherSubscription] = $this->subscribedTenant();
        $addon = $this->addon('addon.user', '79.000');

        $now = now()->startOfSecond();

        $expected = [
            $this->line($subscription, $addon, SubscriptionAddonStatus::Active, $now->copy()->subDay(), null),
            $this->line($subscription, $addon, SubscriptionAddonStatus::Active, $now->copy()->subDay(), $now->copy()->addMonth()),
            $this->line($subscription, $addon, SubscriptionAddonStatus::Active, $now->copy(), $now->copy()->addMonth()),
        ];

        // Excluded: every non-active status, even inside the window.
        $this->line($subscription, $addon, SubscriptionAddonStatus::PendingPayment, $now->copy()->subDay(), $now->copy()->addMonth());
        $this->line($subscription, $addon, SubscriptionAddonStatus::Cancelled, $now->copy()->subDay(), $now->copy()->addMonth());
        $this->line($subscription, $addon, SubscriptionAddonStatus::Expired, $now->copy()->subDay(), $now->copy()->addMonth());
        // Excluded: active but outside the window.
        $this->line($subscription, $addon, SubscriptionAddonStatus::Active, $now->copy()->subMonth(), $now->copy()->subSecond());
        $this->line($subscription, $addon, SubscriptionAddonStatus::Active, $now->copy()->subMonth(), $now->copy());
        $this->line($subscription, $addon, SubscriptionAddonStatus::Active, $now->copy()->addDay(), $now->copy()->addMonth());
        $this->line($subscription, $addon, SubscriptionAddonStatus::Active, null, null);
        // Excluded: another tenant's active line.
        $this->line($otherSubscription, $addon, SubscriptionAddonStatus::Active, $now->copy()->subDay(), null);

        $this->travelTo($now);

        $ids = SubscriptionAddon::query()->activeFor($tenant)->orderBy('id')->pluck('id')->all();
        $this->assertSame(array_map(static fn (SubscriptionAddon $line): int => $line->id, $expected), $ids);

        // A tenant id string works the same as the model.
        $this->assertSame($ids, SubscriptionAddon::query()->activeFor((string) $tenant->id)->orderBy('id')->pluck('id')->all());

        foreach (SubscriptionAddon::query()->where('tenant_id', $tenant->id)->get() as $line) {
            $this->assertSame(in_array($line->id, $ids, true), $line->isActive(), "isActive() disagrees with activeFor() for line {$line->id}.");
        }
    }

    public function test_active_for_accepts_a_reference_moment(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant();
        $addon = $this->addon('addon.van', '249.000');
        $start = now()->startOfSecond()->addMonth();

        $line = $this->line($subscription, $addon, SubscriptionAddonStatus::Active, $start, $start->copy()->addMonth());

        $this->assertSame([], SubscriptionAddon::query()->activeFor($tenant)->pluck('id')->all());
        $this->assertSame([$line->id], SubscriptionAddon::query()->activeFor($tenant, $start->copy()->addDay())->pluck('id')->all());
        $this->assertTrue($line->isActive($start->copy()->addDay()));
        $this->assertFalse($line->isActive($start->copy()->addMonth()));
    }

    public function test_status_and_cycle_columns_store_the_enum_values(): void
    {
        [, $subscription] = $this->subscribedTenant();
        $addon = $this->addon('service.onboarding', '1500.000', AddonType::Service);

        $line = $this->line($subscription, $addon, SubscriptionAddonStatus::Expired, now()->subYear(), now()->subDay(), BillingCycle::Yearly);

        $row = DB::connection($this->centralConnectionName())->table('subscription_addons')->where('id', $line->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('expired', $row->status);
        $this->assertSame('yearly', $row->billing_cycle);
    }

    public function test_addon_with_purchased_lines_cannot_be_deleted(): void
    {
        [, $subscription] = $this->subscribedTenant();
        $addon = $this->addon('addon.warehouse', '149.000');
        $this->line($subscription, $addon, SubscriptionAddonStatus::Active, now()->subDay(), null);

        $this->expectException(QueryException::class);
        $addon->delete();
    }

    public function test_model_reads_the_central_db_inside_a_tenant(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant();
        $addon = $this->addon('mixes.manage', '199.000');
        $line = $this->line($subscription, $addon, SubscriptionAddonStatus::Active, now()->subDay(), null);
        $central = $this->centralConnectionName();

        $seen = $this->inTenant($tenant, fn (): array => [
            'default' => DB::getDefaultConnection(),
            'connection' => (new SubscriptionAddon)->getConnectionName(),
            'active' => SubscriptionAddon::query()->activeFor($tenant)->pluck('id')->all(),
            'addon_key' => SubscriptionAddon::query()->findOrFail($line->id)->addon->key,
        ]);

        $this->assertNotSame($central, $seen['default'], 'Tenancy must switch the default connection for this test to mean anything.');
        $this->assertSame($central, $seen['connection']);
        $this->assertSame([$line->id], $seen['active']);
        $this->assertSame('mixes.manage', $seen['addon_key']);
    }

    public function test_migration_rolls_back_and_reapplies(): void
    {
        try {
            $this->runMigration('down', self::COMPOSITE_FK_MIGRATION);
            $this->runMigration('down');
            $this->assertFalse(Schema::hasTable('subscription_addons'));

            // Running down() twice is harmless.
            $this->runMigration('down');
        } finally {
            $this->runMigration('up');
            $this->runMigration('up', self::COMPOSITE_FK_MIGRATION);
        }

        $this->assertTrue(Schema::hasTable('subscription_addons'));

        // up() is idempotent too.
        $this->runMigration('up');
        $this->assertTrue(Schema::hasTable('subscription_addons'));
    }

    public function test_tenant_id_is_copied_from_the_subscription_when_not_given(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant();
        $addon = $this->addon('addon.copy', '79.000');

        $line = SubscriptionAddon::query()->create([
            'subscription_id' => $subscription->id,
            'addon_id' => $addon->id,
            'unit_price' => '79.000',
        ]);

        $this->assertSame((string) $tenant->id, $line->tenant_id);
        $this->assertSame((string) $tenant->id, $this->storedTenantId($line->id));
    }

    public function test_tenant_id_from_input_is_never_trusted(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant();
        [$attacker] = $this->subscribedTenant();
        $addon = $this->addon('addon.spoof', '79.000');

        // Mass assignment: tenant_id is not fillable, the parent's tenant wins.
        $line = SubscriptionAddon::query()->create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $attacker->id,
            'addon_id' => $addon->id,
            'unit_price' => '79.000',
            'status' => SubscriptionAddonStatus::Active,
            'starts_at' => now()->subDay(),
        ]);

        $this->assertSame((string) $tenant->id, $this->storedTenantId($line->id));
        $this->assertSame([], SubscriptionAddon::query()->activeFor($attacker)->pluck('id')->all(), 'The spoofed tenant must not gain the add-on.');
        $this->assertSame([$line->id], SubscriptionAddon::query()->activeFor($tenant)->pluck('id')->all());
    }

    public function test_a_forced_foreign_tenant_id_is_refused_before_anything_is_written(): void
    {
        [, $subscription] = $this->subscribedTenant();
        [$attacker] = $this->subscribedTenant();
        $addon = $this->addon('addon.force', '79.000');

        $line = new SubscriptionAddon(['subscription_id' => $subscription->id, 'addon_id' => $addon->id, 'unit_price' => '79.000']);
        $line->forceFill(['tenant_id' => $attacker->id]);

        $this->assertAddonFails('tenant_mismatch', fn () => $line->save());
        $this->assertSame(0, SubscriptionAddon::query()->count());
    }

    public function test_a_line_without_an_existing_subscription_is_refused(): void
    {
        $addon = $this->addon('addon.orphan', '79.000');

        $this->assertAddonFails('subscription_missing', fn () => SubscriptionAddon::query()->create([
            'addon_id' => $addon->id,
            'unit_price' => '79.000',
        ]));
        $this->assertAddonFails('subscription_missing', fn () => SubscriptionAddon::query()->create([
            'subscription_id' => 999999,
            'addon_id' => $addon->id,
            'unit_price' => '79.000',
        ]));

        $this->assertSame(0, SubscriptionAddon::query()->count());
    }

    public function test_moving_a_line_is_allowed_within_the_tenant_only(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant();
        [, $otherTenantSubscription] = $this->subscribedTenant();
        $addon = $this->addon('addon.move', '79.000');
        $line = $this->line($subscription, $addon, SubscriptionAddonStatus::Active, now()->subDay(), null);

        $renewal = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $subscription->plan_id,
            'billing_cycle' => BillingCycle::Monthly,
            'status' => SubscriptionStatus::Active,
            'amount' => '449.000',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $line->update(['subscription_id' => $renewal->id]);
        $this->assertSame((string) $tenant->id, $this->storedTenantId($line->id));

        $this->assertAddonFails('tenant_mismatch', fn () => $line->update(['subscription_id' => $otherTenantSubscription->id]));

        $row = DB::connection($this->centralConnectionName())->table('subscription_addons')->where('id', $line->id)->first();
        $this->assertNotNull($row);
        $this->assertSame($renewal->id, (int) $row->subscription_id, 'A refused move must not be written.');
        $this->assertSame((string) $tenant->id, $row->tenant_id);
    }

    public function test_tenant_is_copied_from_the_central_subscription_inside_a_tenant_context(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant();
        $addon = $this->addon('addon.ctx', '79.000');

        $lineId = $this->inTenant($tenant, fn (): int => (int) SubscriptionAddon::query()->create([
            'subscription_id' => $subscription->id,
            'addon_id' => $addon->id,
            'unit_price' => '79.000',
        ])->getKey());

        $this->assertSame((string) $tenant->id, $this->storedTenantId($lineId));
    }

    private function storedTenantId(int $lineId): ?string
    {
        $value = DB::connection($this->centralConnectionName())->table('subscription_addons')->where('id', $lineId)->value('tenant_id');

        return $value === null ? null : (string) $value;
    }

    private function assertAddonFails(string $reason, \Closure $callback): void
    {
        try {
            $callback();
            $this->fail("Saving the add-on line must fail with [{$reason}].");
        } catch (SubscriptionAddonException $e) {
            $this->assertSame($reason, $e->reason());
            $this->assertSame(__('billing.subscription_addon.'.$reason), $e->getMessage());
            $this->assertNotSame('billing.subscription_addon.'.$reason, $e->getMessage(), 'The message must be translated.');
        }
    }

    /**
     * @return array{0: Tenant, 1: Subscription}
     */
    private function subscribedTenant(): array
    {
        $tenant = $this->createTenant();
        $plan = Plan::query()->create([
            'name' => 'plan-'.$tenant->id,
            'slug' => 'plan-'.$tenant->id,
            'price_monthly' => '449.000',
            'price_yearly' => '4490.000',
            'features' => [],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => BillingCycle::Monthly,
            'status' => SubscriptionStatus::Active,
            'amount' => '449.000',
            'starts_at' => now()->subMonth(),
            // Long enough to cover the future reference moments used below: since CTO W1 Q3 a
            // line is only active while its parent subscription is (ends_at > moment).
            'ends_at' => now()->addYear(),
        ]);

        return [$tenant, $subscription];
    }

    private function addon(string $key, string $price, AddonType $type = AddonType::Recurring): Addon
    {
        return Addon::query()->create([
            'key' => $key,
            'name_key' => 'plans.addons.'.$key,
            'type' => $type,
            'unit_price' => $price,
        ]);
    }

    private function line(
        Subscription $subscription,
        Addon $addon,
        SubscriptionAddonStatus $status,
        ?\DateTimeInterface $startsAt,
        ?\DateTimeInterface $endsAt,
        BillingCycle $cycle = BillingCycle::Monthly,
    ): SubscriptionAddon {
        return SubscriptionAddon::query()->create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'addon_id' => $addon->id,
            'quantity' => 2,
            'unit_price' => $addon->unit_price,
            'billing_cycle' => $cycle,
            'status' => $status,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    private function runMigration(string $direction, string $path = self::MIGRATION): void
    {
        $migration = require database_path($path);
        if (! is_object($migration) || ! method_exists($migration, $direction)) {
            $this->fail($path." must return a migration with {$direction}().");
        }

        $migration->{$direction}();
    }
}
