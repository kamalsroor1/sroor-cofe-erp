<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * DEC-SHIFT: returns.cash_shift_id is a tenant migration safe to (re)run: the column and its
 * (cash_shift_id, created_at) index; idempotent up/down; down() removes both.
 */
#[Group('mysql')]
final class ReturnCashShiftMigrationTest extends TenantTestCase
{
    private const MIGRATION = __DIR__.'/../../database/migrations/tenant/2026_11_19_100000_add_cash_shift_id_to_returns_table.php';

    private const INDEX = 'returns_cash_shift_created_idx';

    public function test_the_column_and_index_exist_and_up_down_are_idempotent(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            $this->assertApplied('after the tenant migrations');

            $migration = $this->migration();

            $migration->up();
            $this->assertApplied('second up');

            $migration->down();
            $migration->down();
            $this->assertFalse(Schema::hasColumn('returns', 'cash_shift_id'));
            $this->assertNull($this->indexColumns());

            $migration->up();
            $this->assertApplied('re-created');
        });
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

    private function assertApplied(string $when): void
    {
        $this->assertTrue(Schema::hasColumn('returns', 'cash_shift_id'), "returns.cash_shift_id ({$when})");
        $this->assertSame(['cash_shift_id', 'created_at'], $this->indexColumns(), "index ({$when})");
    }

    /**
     * @return list<string>|null
     */
    private function indexColumns(): ?array
    {
        foreach (Schema::getIndexes('returns') as $index) {
            if (strcasecmp((string) $index['name'], self::INDEX) === 0) {
                return $index['columns'];
            }
        }

        return null;
    }
}
