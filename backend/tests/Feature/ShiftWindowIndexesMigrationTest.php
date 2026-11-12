<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * PERF-02: the shift-window index migration is a tenant migration that is safe to (re)run on
 * existing tenants: idempotent up/down, and it never duplicates an index that already exists
 * on the same columns under another name.
 */
#[Group('mysql')]
final class ShiftWindowIndexesMigrationTest extends TenantTestCase
{
    private const MIGRATION = __DIR__.'/../../database/migrations/tenant/2026_11_16_100000_add_shift_window_indexes.php';

    private const EXPECTED = [
        'invoices_store_created_idx' => ['invoices', ['store_id', 'created_at']],
        'payments_created_at_idx' => ['payments', ['created_at']],
    ];

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
    }

    public function test_the_indexes_exist_after_the_tenant_migrations(): void
    {
        $this->inTenant($this->tenant, function (): void {
            foreach (self::EXPECTED as $name => [$table, $columns]) {
                $this->assertSame($columns, $this->columnsOf($table, $name), "{$table}.{$name}");
            }
        });
    }

    public function test_up_and_down_are_idempotent(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $migration = $this->migration();

            $migration->up(); // already applied: no-op, no duplicate
            foreach (self::EXPECTED as $name => [$table, $columns]) {
                $this->assertSame($columns, $this->columnsOf($table, $name));
            }

            $migration->down();
            $migration->down(); // nothing left to drop: no-op
            foreach (self::EXPECTED as $name => [$table]) {
                $this->assertNull($this->columnsOf($table, $name), "{$name} dropped");
            }

            $migration->up();
            foreach (self::EXPECTED as $name => [$table, $columns]) {
                $this->assertSame($columns, $this->columnsOf($table, $name), "{$name} re-created");
            }
        });
    }

    public function test_an_equivalent_index_under_another_name_is_reused_and_left_alone(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $migration = $this->migration();
            $migration->down();

            Schema::table('payments', fn (Blueprint $t) => $t->index(['created_at'], 'legacy_payments_created'));

            $migration->up();
            $this->assertNull($this->columnsOf('payments', 'payments_created_at_idx'));
            $this->assertSame(['created_at'], $this->columnsOf('payments', 'legacy_payments_created'));

            $migration->down();
            $this->assertSame(['created_at'], $this->columnsOf('payments', 'legacy_payments_created'), 'not ours: kept');
        });
    }

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = require self::MIGRATION;

        return $migration;
    }

    /**
     * @return list<string>|null
     */
    private function columnsOf(string $table, string $name): ?array
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (strcasecmp((string) $index['name'], $name) === 0) {
                return array_values($index['columns']);
            }
        }

        return null;
    }
}
