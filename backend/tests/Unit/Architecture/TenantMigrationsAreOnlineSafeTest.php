<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\OnlineMigrationLint;

/**
 * MIG-01 CI rule: a tenant migration may not add a foreign key to a big table with the schema
 * builder, or backfill a big table with one UPDATE, unless it goes through
 * App\Support\Database\OnlineMigration (see Tests\Support\OnlineMigrationLint for the rules).
 *
 * Shipped migrations are never edited to satisfy this rule: a reviewed exception goes into
 * ALLOWLIST with the reason. Every tenant migration in the tree passes today, so the list is empty.
 */
final class TenantMigrationsAreOnlineSafeTest extends TestCase
{
    private const MIGRATIONS = __DIR__.'/../../../database/migrations/tenant';

    private const FIXTURES = __DIR__.'/../../Fixtures/OnlineMigrationLint';

    /** @var array<string, string> migration file name => why it is accepted as is */
    private const ALLOWLIST = [];

    public function test_tenant_migrations_on_big_tables_go_through_online_migration(): void
    {
        $files = glob(self::MIGRATIONS.'/*.php') ?: [];
        $this->assertNotEmpty($files, 'No tenant migrations found: the path is wrong.');

        $problems = [];
        foreach ($files as $file) {
            if (array_key_exists(basename($file), self::ALLOWLIST)) {
                continue;
            }
            foreach (OnlineMigrationLint::violations((string) file_get_contents($file)) as $violation) {
                $problems[] = basename($file).' — '.$violation;
            }
        }

        $this->assertSame([], $problems, "Tenant migrations that would stall selling on a large shop (MIG-01):\n".implode("\n", $problems));
    }

    public function test_allowlisted_migrations_still_exist(): void
    {
        foreach (array_keys(self::ALLOWLIST) as $name) {
            $this->assertFileExists(self::MIGRATIONS.'/'.$name, "Stale ALLOWLIST entry {$name}.");
        }
        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{string, string}> the four migrations as they were before MIG-01 */
    public static function preMig01Migrations(): array
    {
        return [
            'payments.return_id FK via constrained()' => ['2026_11_10_130000_add_refund_columns_for_sales_returns', 'payments: foreign key'],
            'payments.reverses_payment_id FK via constrained()' => ['2026_11_10_140000_add_reverses_payment_id_to_payments', 'payments: foreign key'],
            'payments.store_id one-statement backfill' => ['2026_11_10_120000_backfill_payments_store_id', 'payments: UPDATE over the whole table'],
            'documents store_id backfill over a variable table' => ['2026_11_13_100400_backfill_null_store_id_on_documents', 'invoices: UPDATE over the whole table'],
        ];
    }

    #[DataProvider('preMig01Migrations')]
    public function test_the_lint_flags_the_migrations_mig01_had_to_fix(string $fixture, string $expected): void
    {
        $violations = OnlineMigrationLint::violations((string) file_get_contents(self::FIXTURES."/{$fixture}.php.txt"));

        $this->assertNotEmpty($violations);
        $this->assertStringStartsWith($expected, $violations[0]);
    }

    /** @return array<string, array{string}> */
    public static function unsafeSnippets(): array
    {
        return [
            'constrained() on payments' => [<<<'PHP'
                <?php
                Schema::table('payments', function (Blueprint $table) {
                    $table->foreignId('return_id')->nullable()->constrained('returns')->nullOnDelete();
                });
                PHP],
            'constrained() behind a hasColumn guard' => [<<<'PHP'
                <?php
                Schema::table('invoice_items', function (Blueprint $table) {
                    if (! Schema::hasColumn('invoice_items', 'batch_id')) {
                        $table->foreignId('batch_id')->nullable()->constrained();
                    }
                });
                PHP],
            'foreign() on stock_movements' => [<<<'PHP'
                <?php
                Schema::table("stock_movements", function (Blueprint $table) {
                    $table->foreign('shift_id')->references('id')->on('cash_shifts');
                });
                PHP],
            'raw ALTER … FOREIGN KEY' => [<<<'PHP'
                <?php
                DB::statement('ALTER TABLE `invoices` ADD CONSTRAINT x FOREIGN KEY (a) REFERENCES b (id)');
                PHP],
            'builder UPDATE over store_stocks' => [<<<'PHP'
                <?php
                DB::table('store_stocks')->whereNull('store_id')->update(['store_id' => 1]);
                PHP],
            'raw UPDATE over invoices' => [<<<'PHP'
                <?php
                DB::statement('UPDATE invoices SET store_id = 1 WHERE store_id IS NULL');
                PHP],
            'raw UPDATE whose only id filter is in a sub-select' => [<<<'PHP'
                <?php
                DB::update('UPDATE payments SET store_id = (SELECT i.store_id FROM invoices i WHERE i.id = payments.invoice_id)');
                PHP],
        ];
    }

    #[DataProvider('unsafeSnippets')]
    public function test_unsafe_snippets_are_flagged(string $php): void
    {
        $this->assertNotEmpty(OnlineMigrationLint::violations($php));
    }

    /** @return array<string, array{string}> */
    public static function safeSnippets(): array
    {
        return [
            'OnlineMigration FK helper' => [<<<'PHP'
                <?php
                OnlineMigration::addNullableForeignKey('payments', 'return_id', 'returns', 'set null');
                PHP],
            'FK from a brand-new table to payments' => [<<<'PHP'
                <?php
                Schema::create('additional_expenses', function (Blueprint $table) {
                    $table->foreignId('payment_id')->nullable()->constrained('payments');
                });
                PHP],
            'FK on a small table' => [<<<'PHP'
                <?php
                Schema::table('cash_shifts', function (Blueprint $table) {
                    $table->foreignId('store_id')->nullable()->constrained();
                });
                PHP],
            'chunked backfill' => [<<<'PHP'
                <?php
                OnlineMigration::forEachIdRange('payments', function (int $from, int $to): void {
                    DB::table('payments')->whereBetween('id', [$from, $to])->whereNull('store_id')->update(['store_id' => 1]);
                });
                PHP],
            'row-by-row raw update' => [<<<'PHP'
                <?php
                DB::update('update store_stocks set quantity = quantity + ? where id = ?', [$q, $id]);
                PHP],
            'key-list builder update' => [<<<'PHP'
                <?php
                DB::table('invoices')->whereIn('id', $ids)->update(['store_id' => 1]);
                PHP],
            'only mentioned in a comment' => [<<<'PHP'
                <?php
                // was: DB::table('payments')->update(['store_id' => 1]); and ->constrained() on Schema::table('payments')
                Schema::table('items', fn ($t) => $t->string('x')->nullable());
                PHP],
        ];
    }

    #[DataProvider('safeSnippets')]
    public function test_safe_snippets_pass(string $php): void
    {
        $this->assertSame([], OnlineMigrationLint::violations($php));
    }
}
