<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * DATE-01: the cash_shift_id migration is a tenant migration that is safe to (re)run on existing
 * tenants: the column and its (cash_shift_id, created_at) index on payments, expenses and
 * invoices; idempotent up/down; down() removes both.
 */
#[Group('mysql')]
final class CashShiftStampMigrationTest extends TenantTestCase
{
    private const MIGRATION = __DIR__.'/../../database/migrations/tenant/2026_11_18_100000_add_cash_shift_id_to_payments_expenses_and_invoices.php';

    private const EXPECTED = [
        'payments_cash_shift_created_idx' => 'payments',
        'expenses_cash_shift_created_idx' => 'expenses',
        'invoices_cash_shift_created_idx' => 'invoices',
    ];

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
    }

    public function test_the_columns_and_indexes_exist_after_the_tenant_migrations(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->assertApplied('after the tenant migrations');
        });
    }

    public function test_up_and_down_are_idempotent(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $migration = $this->migration();

            $migration->up(); // already applied: no-op, no duplicate
            $this->assertApplied('second up');

            $migration->down();
            $migration->down(); // nothing left to drop: no-op
            foreach (self::EXPECTED as $name => $table) {
                $this->assertFalse(Schema::hasColumn($table, 'cash_shift_id'), "{$table}.cash_shift_id dropped");
                $this->assertNull($this->columnsOf($table, $name), "{$name} dropped");
            }

            $migration->up();
            $this->assertApplied('re-created');
        });
    }

    private function assertApplied(string $when): void
    {
        foreach (self::EXPECTED as $name => $table) {
            $this->assertTrue(Schema::hasColumn($table, 'cash_shift_id'), "{$table}.cash_shift_id ({$when})");
            $this->assertSame(['cash_shift_id', 'created_at'], $this->columnsOf($table, $name), "{$table}.{$name} ({$when})");

            $matching = array_filter(
                Schema::getIndexes($table),
                fn (array $index): bool => array_map('strtolower', $index['columns']) === ['cash_shift_id', 'created_at'],
            );
            $this->assertCount(1, $matching, "{$table}: one (cash_shift_id, created_at) index ({$when})");
        }
    }

    /**
     * The anonymous migration class (up() and down() are its own methods, not Migration's).
     */
    private function migration(): object
    {
        $migration = require self::MIGRATION;
        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertTrue(method_exists($migration, 'up') && method_exists($migration, 'down'));

        return $migration;
    }

    /**
     * @return list<string>|null
     */
    private function columnsOf(string $table, string $name): ?array
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (strcasecmp((string) $index['name'], $name) === 0) {
                return $index['columns'];
            }
        }

        return null;
    }
}
