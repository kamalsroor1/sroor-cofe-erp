<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\DocumentNumberService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * BE-09: document numbers come from locked counter rows (document_sequences), never from
 * MAX()+1 or uniqid(). sqlite serialises writers, so the real lock/race proof lives in
 * Tests\Feature\Concurrency\MysqlConcurrencyTest (group mysql-only).
 */
final class DocumentNumberServiceTest extends TenantTestCase
{
    private Tenant $tenant;

    private DocumentNumberService $numbers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        tenancy()->initialize($this->tenant);
        $this->numbers = app(DocumentNumberService::class);
    }

    public function test_numbers_are_sequential_per_scope(): void
    {
        $this->assertSame('INV-MAIN-20261110-0001', $this->numbers->next('INV-MAIN-20261110', 4));
        $this->assertSame('INV-MAIN-20261110-0002', $this->numbers->next('INV-MAIN-20261110', 4));
        $this->assertSame(2, (int) DB::table('document_sequences')->where('scope', 'INV-MAIN-20261110')->value('last_value'));
    }

    public function test_scopes_do_not_interfere(): void
    {
        $this->assertSame('INV-MAIN-20261110-0001', $this->numbers->next('INV-MAIN-20261110', 4));
        // Other store, other day, other document type: each starts at 1.
        $this->assertSame('INV-BR2-20261110-0001', $this->numbers->next('INV-BR2-20261110', 4));
        $this->assertSame('INV-MAIN-20261111-0001', $this->numbers->next('INV-MAIN-20261111', 4));
        $this->assertSame('PAY-INV-20261110-000001', $this->numbers->next('PAY-INV-20261110', 6));
        $this->assertSame('INV-MAIN-20261110-0002', $this->numbers->next('INV-MAIN-20261110', 4));
    }

    public function test_distinct_scope_key_keeps_a_shared_visible_prefix_apart(): void
    {
        $this->assertSame('TRF-20261110-0001', $this->numbers->next('TRF-20261110', 4, scope: 'TRF-20261110'));
        $this->assertSame('TRF-20261110-0001', $this->numbers->next('TRF-20261110', 4, scope: 'TTRF-20261110'));
        $this->assertSame('TRF-20261110-0002', $this->numbers->next('TRF-20261110', 4, scope: 'TRF-20261110'));
    }

    public function test_numbers_wider_than_the_pad_just_grow(): void
    {
        DB::table('document_sequences')->insert(['scope' => 'INV-X-20261110', 'last_value' => 9999, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame('INV-X-20261110-10000', $this->numbers->next('INV-X-20261110', 4));
    }

    public function test_first_use_is_seeded_from_documents_already_issued(): void
    {
        $customer = Customer::create(['name' => 'عميل', 'is_active' => true]);
        $prefix = 'INV-MAIN-20261110';
        foreach (['0003', '0007'] as $suffix) {
            Invoice::create(['invoice_number' => "{$prefix}-{$suffix}", 'customer_id' => $customer->id, 'invoice_date' => '2026-11-10', 'status' => 'confirmed']);
        }
        // A soft-deleted document still holds its number.
        $deleted = Invoice::create(['invoice_number' => "{$prefix}-0009", 'customer_id' => $customer->id, 'invoice_date' => '2026-11-10', 'status' => 'confirmed']);
        $deleted->delete();

        $this->assertSame("{$prefix}-0010", $this->numbers->nextFor($prefix, 4, Invoice::class, 'invoice_number'));
        $this->assertSame("{$prefix}-0011", $this->numbers->nextFor($prefix, 4, Invoice::class, 'invoice_number'));
    }

    public function test_seed_is_read_only_when_the_scope_row_is_missing(): void
    {
        $calls = 0;
        $seed = function () use (&$calls): int {
            $calls++;

            return 41;
        };

        $this->assertSame('X-20261110-0042', $this->numbers->next('X-20261110', 4, null, $seed));
        $this->assertSame('X-20261110-0043', $this->numbers->next('X-20261110', 4, null, $seed));
        $this->assertSame(1, $calls);
    }

    public function test_legacy_random_suffixes_do_not_break_the_seed(): void
    {
        $customer = Customer::create(['name' => 'عميل', 'is_active' => true]);
        foreach (['PAY-INV-20261110-AB12', 'PAY-INV-20261110-000004'] as $number) {
            Payment::create(['payment_number' => $number, 'customer_id' => $customer->id, 'amount' => '1.000', 'payment_method' => 'cash', 'payment_date' => '2026-11-10']);
        }

        $this->assertSame('PAY-INV-20261110-000005', $this->numbers->nextFor('PAY-INV-20261110', 6, Payment::class, 'payment_number'));
    }

    public function test_exists_check_skips_numbers_taken_by_hand(): void
    {
        Expense::create(['expense_number' => 'EXP-261110-0001', 'title' => 'يدوي', 'category' => 'other', 'amount' => '1.000', 'expense_date' => '2026-11-10', 'payment_method' => 'cash']);
        Expense::create(['expense_number' => 'EXP-261110-0003', 'title' => 'يدوي', 'category' => 'other', 'amount' => '1.000', 'expense_date' => '2026-11-10', 'payment_method' => 'cash']);

        // Counter already past the first manual number but not the second: 0002 is free, then 0003 is skipped.
        DB::table('document_sequences')->insert(['scope' => 'EXP-261110', 'last_value' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame('EXP-261110-0002', $this->numbers->nextFor('EXP-261110', 4, Expense::class, 'expense_number'));
        $this->assertSame('EXP-261110-0004', $this->numbers->nextFor('EXP-261110', 4, Expense::class, 'expense_number'));
        $this->assertSame(4, (int) DB::table('document_sequences')->where('scope', 'EXP-261110')->value('last_value'));
    }

    public function test_works_without_an_open_transaction_and_commits_the_counter(): void
    {
        $this->assertSame(0, DB::transactionLevel(), 'precondition: no open transaction');

        $this->assertSame('A-1-0001', $this->numbers->next('A-1', 4));
        $this->assertSame(1, (int) DB::table('document_sequences')->where('scope', 'A-1')->value('last_value'));
    }

    public function test_a_rolled_back_transaction_gives_the_number_back(): void
    {
        $this->assertSame('B-1-0001', $this->numbers->next('B-1', 4));

        try {
            DB::transaction(function (): void {
                $this->assertSame('B-1-0002', $this->numbers->next('B-1', 4));
                throw new RuntimeException('document failed');
            });
        } catch (RuntimeException) {
        }

        // The consumed number was rolled back with the document: not persisted, reused.
        $this->assertSame(1, (int) DB::table('document_sequences')->where('scope', 'B-1')->value('last_value'));
        $this->assertSame('B-1-0002', $this->numbers->next('B-1', 4));
    }

    public function test_a_committed_transaction_keeps_the_number(): void
    {
        DB::transaction(function (): void {
            $this->assertSame('C-1-0001', $this->numbers->next('C-1', 4));
            $this->assertSame('C-1-0002', $this->numbers->next('C-1', 4));
        });

        $this->assertSame('C-1-0003', $this->numbers->next('C-1', 4));
    }
}
