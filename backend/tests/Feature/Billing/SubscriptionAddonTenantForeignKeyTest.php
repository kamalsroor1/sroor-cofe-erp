<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Addon;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * Migration 2026_10_10_200550: subscription_addons (subscription_id, tenant_id) is a composite
 * foreign key to subscriptions (id, tenant_id), so the DATABASE refuses an add-on line whose
 * tenant differs from its subscription's tenant, even when written with a raw query that
 * bypasses SubscriptionAddon's saving hook. The FK to tenants is kept.
 */
#[Group('billing')]
#[Group('mysql')]
final class SubscriptionAddonTenantForeignKeyTest extends TenantTestCase
{
    private const MIGRATION = 'migrations/2026_10_10_200550_add_tenant_scoped_foreign_key_to_subscription_addons.php';

    public function test_schema_has_the_composite_foreign_key_and_keeps_the_tenant_one(): void
    {
        $this->assertCompositeSchema();
    }

    public function test_a_raw_insert_with_another_tenant_is_refused_by_the_database(): void
    {
        [, $subscription] = $this->subscribedTenant();
        [$attacker] = $this->subscribedTenant();
        $addonId = $this->addonId();

        $this->expectException(QueryException::class);
        $this->db()->table('subscription_addons')->insert($this->rawLine($subscription->id, (string) $attacker->id, $addonId));
    }

    public function test_a_raw_update_moving_a_line_to_another_tenant_is_refused(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant();
        [$attacker] = $this->subscribedTenant();
        $this->db()->table('subscription_addons')->insert($this->rawLine($subscription->id, (string) $tenant->id, $this->addonId()));

        $this->expectException(QueryException::class);
        $this->db()->table('subscription_addons')->update(['tenant_id' => $attacker->id]);
    }

    public function test_changing_the_tenant_of_a_subscription_with_lines_is_refused(): void
    {
        [, $subscription] = $this->subscribedTenant();
        [$other] = $this->subscribedTenant();
        SubscriptionAddon::query()->create(['subscription_id' => $subscription->id, 'addon_id' => $this->addonId(), 'unit_price' => '79.000']);

        $this->expectException(QueryException::class);
        $this->db()->table('subscriptions')->where('id', $subscription->id)->update(['tenant_id' => $other->id]);
    }

    public function test_deleting_a_subscription_still_cascades_to_its_lines(): void
    {
        [, $subscription] = $this->subscribedTenant();
        SubscriptionAddon::query()->create(['subscription_id' => $subscription->id, 'addon_id' => $this->addonId(), 'unit_price' => '79.000']);

        $subscription->delete();

        $this->assertSame(0, SubscriptionAddon::query()->count());
    }

    public function test_migration_refuses_to_run_over_cross_tenant_lines(): void
    {
        [, $subscription] = $this->subscribedTenant();
        [$attacker] = $this->subscribedTenant();

        $this->runMigration('down');
        try {
            // Without the composite FK such a line can be written; up() must not accept it.
            $this->db()->table('subscription_addons')->insert($this->rawLine($subscription->id, (string) $attacker->id, $this->addonId()));

            try {
                $this->runMigration('up');
                $this->fail('up() must refuse cross-tenant add-on lines.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('subscription_addons', $e->getMessage());
            }
        } finally {
            $this->db()->table('subscription_addons')->delete();
            $this->runMigration('up');
        }

        $this->assertCompositeSchema();
    }

    public function test_migration_rolls_back_to_the_single_foreign_key_and_reapplies(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant();
        $lineId = (int) SubscriptionAddon::query()->create(['subscription_id' => $subscription->id, 'addon_id' => $this->addonId(), 'unit_price' => '79.000'])->getKey();

        try {
            $this->runMigration('down');
            $this->runMigration('down');

            $schema = Schema::connection($this->centralConnectionName());
            $this->assertSame([['subscription_id'], ['tenant_id'], ['addon_id']], $this->foreignKeyColumns(), 'down() restores the original FKs.');
            $this->assertFalse($schema->hasIndex('subscriptions', 'subscriptions_id_tenant_id_unique'));
            $this->assertFalse($schema->hasIndex('subscription_addons', ['subscription_id', 'tenant_id']));
            $this->assertTrue($schema->hasIndex('subscription_addons', ['subscription_id']));
        } finally {
            $this->runMigration('up');
        }

        $this->runMigration('up');
        $this->assertCompositeSchema();
        $this->assertSame((string) $tenant->id, (string) $this->db()->table('subscription_addons')->where('id', $lineId)->value('tenant_id'), 'Rows survive the round trip.');
    }

    private function assertCompositeSchema(): void
    {
        $schema = Schema::connection($this->centralConnectionName());

        $this->assertTrue($schema->hasIndex('subscriptions', ['id', 'tenant_id'], 'unique'), 'The FK target needs UNIQUE (id, tenant_id).');
        $this->assertTrue($schema->hasIndex('subscription_addons', ['subscription_id', 'tenant_id']));

        $byColumns = collect($schema->getForeignKeys('subscription_addons'))
            ->keyBy(fn (array $fk): string => implode(',', $fk['columns']));

        $this->assertArrayHasKey('subscription_id,tenant_id', $byColumns->all());
        $composite = $byColumns['subscription_id,tenant_id'];
        $this->assertSame('subscriptions', $composite['foreign_table']);
        $this->assertSame(['id', 'tenant_id'], $composite['foreign_columns']);
        $this->assertSame('cascade', strtolower((string) $composite['on_delete']));

        $this->assertArrayNotHasKey('subscription_id', $byColumns->all(), 'The single-column FK is replaced.');
        $this->assertArrayHasKey('tenant_id', $byColumns->all(), 'The FK to tenants is kept.');
        $this->assertSame('tenants', $byColumns['tenant_id']['foreign_table']);
        $this->assertArrayHasKey('addon_id', $byColumns->all());
    }

    /**
     * @return list<list<string>>
     */
    private function foreignKeyColumns(): array
    {
        $columns = array_map(static fn (array $fk): array => $fk['columns'], Schema::connection($this->centralConnectionName())->getForeignKeys('subscription_addons'));
        $order = ['subscription_id' => 0, 'tenant_id' => 1, 'addon_id' => 2];
        usort($columns, static fn (array $a, array $b): int => ($order[$a[0]] ?? 9) <=> ($order[$b[0]] ?? 9));

        return $columns;
    }

    /**
     * @return array<string, mixed>
     */
    private function rawLine(int $subscriptionId, string $tenantId, int $addonId): array
    {
        return [
            'subscription_id' => $subscriptionId,
            'tenant_id' => $tenantId,
            'addon_id' => $addonId,
            'quantity' => 1,
            'unit_price' => '79.000',
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function addonId(): int
    {
        return (int) Addon::query()->firstOrCreate(
            ['key' => 'addon.user'],
            ['name_key' => 'plans.addons.user.name', 'unit_price' => '79.000'],
        )->getKey();
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
            'ends_at' => now()->addMonth(),
        ]);

        return [$tenant, $subscription];
    }

    private function db(): Connection
    {
        return DB::connection($this->centralConnectionName());
    }

    private function runMigration(string $direction): void
    {
        $migration = require database_path(self::MIGRATION);
        if (! is_object($migration) || ! method_exists($migration, $direction)) {
            $this->fail(self::MIGRATION." must return a migration with {$direction}().");
        }

        $migration->{$direction}();
    }
}
