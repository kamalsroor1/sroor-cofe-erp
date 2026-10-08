<?php

declare(strict_types=1);

namespace Tests\Feature\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TenantTestCase;

/**
 * POSB-2: `items.is_weighted` is added to every tenant DB and backfilled from the weight
 * units the app used to infer weighing from (POSCartItem/POSItemCard: كجم / جم / *كيلو*)
 * plus the common gram/kilo spellings. Runs on a real tenant database.
 */
final class ItemsWeightedBackfillMigrationTest extends TenantTestCase
{
    private const MIGRATION = 'migrations/tenant/2026_10_10_600000_add_is_weighted_to_items_table.php';

    /** Run the migration file's up() or down() directly against the current (tenant) connection. */
    private function runMigration(string $direction): void
    {
        $migration = require database_path(self::MIGRATION);
        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertTrue(method_exists($migration, $direction));

        $migration->{$direction}();
    }

    public function test_column_and_index_exist_on_a_freshly_provisioned_tenant(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            $this->assertTrue(Schema::hasColumn('items', 'is_weighted'));

            $indexed = collect(Schema::getIndexes('items'))
                ->contains(fn (array $index): bool => $index['columns'] === ['is_weighted']);
            $this->assertTrue($indexed, 'items.is_weighted must be indexed.');
        });
    }

    public function test_backfill_flags_weight_units_only_and_reports_affected_items(): void
    {
        $tenant = $this->createTenant();

        /** @var list<MessageLogged> $logged */
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            if (str_contains($event->message, 'POSB-2')) {
                $logged[] = $event;
            }
        });

        $ids = $this->inTenant($tenant, function (): array {
            $this->runMigration('down');
            $this->assertFalse(Schema::hasColumn('items', 'is_weighted'));

            $units = [
                'kg_ar' => 'كجم',
                'kilo_ar' => 'كيلو',
                'kilo_gram_ar' => 'كيلو جرام',
                'gm_short_ar' => 'جم',
                'gram_ar' => 'جرام',
                'kg_en_padded_upper' => ' KG ',
                'g_en' => 'g',
                'piece' => 'قطعة',
                'box' => 'علبة',
                'litre' => 'لتر',
                'carton' => 'كرتونة',
                'dozen' => 'دستة',
            ];

            $ids = [];
            $n = 0;
            foreach ($units as $key => $unit) {
                $ids[$key] = (int) DB::table('items')->insertGetId([
                    'code' => 'BF-'.(++$n),
                    'name' => 'صنف '.$key,
                    'unit' => $unit,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Soft-deleted rows are backfilled too (they can be restored later).
            $ids['deleted_kg'] = (int) DB::table('items')->insertGetId([
                'code' => 'BF-DEL',
                'name' => 'صنف محذوف',
                'unit' => 'كجم',
                'deleted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->runMigration('up');

            return $ids;
        });

        $flags = $this->inTenant($tenant, fn (): array => DB::table('items')
            ->pluck('is_weighted', 'id')
            ->map(fn ($v): bool => (bool) $v)
            ->all());

        foreach (['kg_ar', 'kilo_ar', 'kilo_gram_ar', 'gm_short_ar', 'gram_ar', 'kg_en_padded_upper', 'g_en', 'deleted_kg'] as $key) {
            $this->assertTrue($flags[$ids[$key]], "{$key} should be weighted");
        }
        foreach (['piece', 'box', 'litre', 'carton', 'dozen'] as $key) {
            $this->assertFalse($flags[$ids[$key]], "{$key} should not be weighted");
        }

        $this->assertCount(1, $logged, 'The backfill must log one report line per tenant.');
        $this->assertSame('info', $logged[0]->level);
        $this->assertSame(8, $logged[0]->context['affected_count'] ?? null);
        $this->assertCount(8, $logged[0]->context['affected_item_ids'] ?? []);
    }

    public function test_up_is_idempotent_and_down_is_reversible(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            $this->runMigration('up'); // already applied by provisioning: must be a no-op
            $this->assertTrue(Schema::hasColumn('items', 'is_weighted'));

            $this->runMigration('down');
            $this->assertFalse(Schema::hasColumn('items', 'is_weighted'));

            $this->runMigration('down'); // guarded: second down is a no-op
            $this->runMigration('up');
            $this->assertTrue(Schema::hasColumn('items', 'is_weighted'));
        });
    }

    public function test_new_items_default_to_not_weighted(): void
    {
        $tenant = $this->createTenant();

        $flag = $this->inTenant($tenant, function (): bool {
            $id = DB::table('items')->insertGetId([
                'code' => 'NEW-1',
                'name' => 'صنف جديد',
                'unit' => 'كجم',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (bool) DB::table('items')->where('id', $id)->value('is_weighted');
        });

        $this->assertFalse($flag, 'Only the one-off backfill infers weighing from the unit; new rows default to false.');
    }
}
