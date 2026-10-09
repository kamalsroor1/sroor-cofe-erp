<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Services\Billing\CreditBalanceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * W2 batch 2 review: rolling back 2026_10_10_200710 must never silently drop credit movements.
 * down() refuses while the ledger holds rows; an empty ledger is dropped (and up() recreates it).
 */
final class CreditLedgerMigrationDownGuardTest extends TenantTestCase
{
    private const MIGRATION = 'database/migrations/2026_10_10_200710_create_tenant_credit_ledger_table.php';

    private function migration(): Migration
    {
        $migration = require base_path(self::MIGRATION);
        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    public function test_down_refuses_to_drop_a_ledger_with_movements(): void
    {
        app(CreditBalanceService::class)->credit('tenant-down-guard', 'credits.messages', '5');

        try {
            $this->migration()->down();
            $this->fail('down() must refuse while the ledger holds rows.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tenant_credit_ledger', $e->getMessage());
        }

        $this->assertTrue(Schema::hasTable('tenant_credit_ledger'));
        $this->assertSame('5.000', app(CreditBalanceService::class)->balance('tenant-down-guard', 'credits.messages'));
    }

    public function test_down_drops_an_empty_ledger_and_up_recreates_it(): void
    {
        $this->migration()->down();
        $this->assertFalse(Schema::hasTable('tenant_credit_ledger'));

        $this->migration()->up();
        $this->assertTrue(Schema::hasTable('tenant_credit_ledger'));
    }
}
