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
 * PERF-01: the report index migration is a tenant migration that is safe to (re)run on
 * existing tenants: idempotent up/down, and it never duplicates an index that already exists
 * on the same columns under another name.
 */
#[Group('mysql')]
final class ReportPerformanceIndexesMigrationTest extends TenantTestCase
{
    private const MIGRATION = __DIR__.'/../../database/migrations/tenant/2026_11_14_100000_add_report_performance_indexes.php';

    /** name => [table, columns] (a sample of the list, one per table kind) */
    private const EXPECTED = [
        'invoices_store_status_date_idx' => ['invoices', ['store_id', 'status', 'invoice_date']],
        'invoices_status_date_idx' => ['invoices', ['status', 'invoice_date']],
        'invoices_customer_status_date_idx' => ['invoices', ['customer_id', 'status', 'invoice_date']],
        'stock_movements_item_store_created_idx' => ['stock_movements', ['item_id', 'store_id', 'created_at']],
        'payments_customer_date_idx' => ['payments', ['customer_id', 'payment_date']],
        'payments_store_date_idx' => ['payments', ['store_id', 'payment_date']],
        'cash_shifts_store_status_idx' => ['cash_shifts', ['store_id', 'status']],
        'returns_customer_date_idx' => ['returns', ['customer_id', 'return_date']],
        'expenses_store_date_idx' => ['expenses', ['store_id', 'expense_date']],
        'invoices_deleted_at_idx' => ['invoices', ['deleted_at']],
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
            foreach (self::EXPECTED as $name => [$table]) {
                $this->assertNotNull($this->columnsOf($table, $name));
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

            // A tenant that already has the same columns indexed under its own name.
            Schema::table('cash_shifts', fn (Blueprint $t) => $t->index(['store_id', 'status'], 'legacy_shift_store_status'));

            $migration->up();
            $this->assertNull($this->columnsOf('cash_shifts', 'cash_shifts_store_status_idx'));
            $this->assertSame(['store_id', 'status'], $this->columnsOf('cash_shifts', 'legacy_shift_store_status'));

            $migration->down();
            $this->assertSame(['store_id', 'status'], $this->columnsOf('cash_shifts', 'legacy_shift_store_status'), 'not ours: kept');
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
