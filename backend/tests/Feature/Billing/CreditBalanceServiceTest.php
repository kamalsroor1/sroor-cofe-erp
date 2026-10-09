<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\AddonType;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceType;
use App\Exceptions\Billing\CreditLedgerException;
use App\Models\Addon;
use App\Models\BillingInvoice;
use App\Models\TenantCreditLedgerEntry;
use App\Services\Billing\CreditBalanceService;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * ENTI-1.10 (CTO 2026-10-09 Q12): credits add-on type + append-only ledger.
 * Balance = Σ delta with bcmath (exact decimal strings), never below zero, central only.
 */
#[Group('billing')]
#[Group('mysql')]
final class CreditBalanceServiceTest extends TenantTestCase
{
    private const KEY = 'credits.messages';

    public function test_addon_type_accepts_credits_and_addons_carry_included_credits(): void
    {
        $this->assertSame(AddonType::Credits, AddonType::from('credits'));

        $addon = Addon::query()->create([
            'key' => 'credits.test_'.Str::lower(Str::random(6)),
            'name_key' => 'plans.addons.test.name',
            'type' => AddonType::Credits,
            'unit_price' => '150.000',
            'yearly_price' => '1500.000',
            'included_credits' => '1000.500',
            'is_public' => false,
        ])->fresh();

        $this->assertNotNull($addon);
        $this->assertSame(AddonType::Credits, $addon->type);
        $this->assertTrue($addon->isCredits());
        $this->assertFalse($addon->isRecurring());
        $this->assertSame('1000.500', $addon->included_credits);
        $this->assertSame('150.000', $addon->unitPriceFor(BillingCycle::Monthly), 'A credits pack is priced per cycle like a recurring add-on.');
        $this->assertSame('1500.000', $addon->unitPriceFor(BillingCycle::Yearly));
    }

    public function test_nothing_credits_related_is_seeded_or_for_sale(): void
    {
        $this->seed(PlansAndFeaturesSeeder::class);

        $this->assertSame(0, Addon::query()->where('type', AddonType::Credits->value)->count());
        $this->assertSame(0, DB::connection($this->centralConnectionName())->table('tenant_credit_ledger')->count());
    }

    public function test_balance_is_the_exact_sum_of_the_ledger(): void
    {
        $tenantId = $this->createTenant()->id;
        $service = $this->service();

        $this->assertSame('0.000', $service->balance($tenantId, self::KEY));

        $service->credit($tenantId, self::KEY, '1000');
        $service->credit($tenantId, self::KEY, '0.1', TenantCreditLedgerEntry::REASON_TOP_UP);
        $service->credit($tenantId, self::KEY, '0.2', TenantCreditLedgerEntry::REASON_TOP_UP);
        $service->debit($tenantId, self::KEY, '0.250');
        $service->debit($tenantId, self::KEY, '999.050', TenantCreditLedgerEntry::REASON_EXPIRY);

        // 1000 + 0.1 + 0.2 - 0.25 - 999.05 = 1.000 exactly (a float sum would drift).
        $this->assertSame('1.000', $service->balance($tenantId, self::KEY));

        $rows = TenantCreditLedgerEntry::query()->where('tenant_id', $tenantId)->orderBy('id')->get();
        $this->assertSame(['1000.000', '0.100', '0.200', '-0.250', '-999.050'], $rows->pluck('delta')->all());
        $this->assertSame(['allowance', 'top_up', 'top_up', 'usage', 'expiry'], $rows->pluck('reason')->all());
    }

    public function test_a_debit_larger_than_the_balance_is_refused_and_writes_nothing(): void
    {
        $tenantId = $this->createTenant()->id;
        $service = $this->service();
        $service->credit($tenantId, self::KEY, '5.500');

        try {
            $service->debit($tenantId, self::KEY, '5.501');
            $this->fail('An overdraft must be refused.');
        } catch (CreditLedgerException $e) {
            $this->assertSame('insufficient_balance', $e->reason());
        }

        $this->assertSame('5.500', $service->balance($tenantId, self::KEY));
        $this->assertSame(1, TenantCreditLedgerEntry::query()->where('tenant_id', $tenantId)->count());

        $service->debit($tenantId, self::KEY, '5.500');
        $this->assertSame('0.000', $service->balance($tenantId, self::KEY), 'Spending the exact balance is allowed.');
    }

    public function test_a_debit_on_an_empty_account_is_refused(): void
    {
        $this->expectException(CreditLedgerException::class);

        $this->service()->debit($this->createTenant()->id, self::KEY, '1');
    }

    public function test_balances_are_isolated_per_tenant_and_per_addon_key(): void
    {
        $a = $this->createTenant()->id;
        $b = $this->createTenant()->id;
        $service = $this->service();

        $service->credit($a, self::KEY, '10');
        $service->credit($a, 'credits.sms', '3');
        $service->credit($b, self::KEY, '7');
        $service->debit($b, self::KEY, '2');

        $this->assertSame('10.000', $service->balance($a, self::KEY));
        $this->assertSame('3.000', $service->balance($a, 'credits.sms'));
        $this->assertSame('5.000', $service->balance($b, self::KEY));
        $this->assertSame('0.000', $service->balance($b, 'credits.sms'));

        try {
            $service->debit($b, 'credits.sms', '1');
            $this->fail('Tenant B cannot spend tenant A\'s sms credits.');
        } catch (CreditLedgerException $e) {
            $this->assertSame('insufficient_balance', $e->reason());
        }
    }

    public function test_one_sentinel_row_per_tenant_and_addon_key(): void
    {
        $tenantId = $this->createTenant()->id;
        $service = $this->service();

        $service->credit($tenantId, self::KEY, '1');
        $service->credit($tenantId, self::KEY, '1');
        $service->debit($tenantId, self::KEY, '1');
        $service->credit($tenantId, 'credits.sms', '1');

        $accounts = DB::connection($this->centralConnectionName())->table('tenant_credit_accounts')->where('tenant_id', $tenantId);
        $this->assertSame(2, (clone $accounts)->count());
        $this->assertSame(1, (clone $accounts)->where('addon_key', self::KEY)->count());
    }

    public function test_a_top_up_can_reference_its_billing_invoice(): void
    {
        $tenantId = $this->createTenant()->id;
        $invoice = BillingInvoice::query()->create([
            'number' => 'TST-'.Str::lower(Str::random(10)),
            'tenant_id' => $tenantId,
            'type' => BillingInvoiceType::Addon,
            'lines' => [['kind' => 'addon', 'key' => self::KEY, 'quantity' => 1, 'unit_price' => '100.000', 'total' => '100.000']],
            'subtotal' => '100.000',
            'total' => '100.000',
        ]);

        $entry = $this->service()->credit($tenantId, self::KEY, '500', TenantCreditLedgerEntry::REASON_TOP_UP, $invoice->id);

        $this->assertSame($invoice->id, $entry->fresh()?->billing_invoice_id);
        $this->assertTrue($entry->billingInvoice?->is($invoice) ?? false);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidAmounts(): iterable
    {
        yield 'zero' => ['0'];
        yield 'zero with decimals' => ['0.000'];
        yield 'negative' => ['-5'];
        yield 'four decimals' => ['1.0001'];
        yield 'too many integer digits' => ['1234567890'];
        yield 'not a number' => ['abc'];
        yield 'exponent' => ['1e3'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_amounts_are_refused_for_credits_and_debits(string $amount): void
    {
        $tenantId = $this->createTenant()->id;
        $service = $this->service();
        $service->credit($tenantId, self::KEY, '100');

        foreach (['credit' => fn () => $service->credit($tenantId, self::KEY, $amount), 'debit' => fn () => $service->debit($tenantId, self::KEY, $amount)] as $name => $call) {
            try {
                $call();
                $this->fail("{$name}({$amount}) must be refused.");
            } catch (CreditLedgerException $e) {
                $this->assertSame('invalid_amount', $e->reason());
            }
        }

        $this->assertSame('100.000', $service->balance($tenantId, self::KEY));
    }

    public function test_reasons_must_match_the_direction(): void
    {
        $tenantId = $this->createTenant()->id;
        $service = $this->service();
        $service->credit($tenantId, self::KEY, '10');

        $attempts = [
            fn () => $service->credit($tenantId, self::KEY, '1', TenantCreditLedgerEntry::REASON_USAGE),
            fn () => $service->credit($tenantId, self::KEY, '1', 'gift'),
            fn () => $service->debit($tenantId, self::KEY, '1', TenantCreditLedgerEntry::REASON_ALLOWANCE),
            fn () => $service->debit($tenantId, self::KEY, '1', TenantCreditLedgerEntry::REASON_TOP_UP),
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('A reason in the wrong direction must be refused.');
            } catch (CreditLedgerException $e) {
                $this->assertSame('invalid_reason', $e->reason());
            }
        }

        $this->assertSame('10.000', $service->balance($tenantId, self::KEY));
    }

    public function test_tenant_and_addon_key_are_required(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->credit(' ', self::KEY, '1');
    }

    public function test_a_failure_inside_the_callers_transaction_rolls_the_movement_back(): void
    {
        $tenantId = $this->createTenant()->id;
        $service = $this->service();
        $service->credit($tenantId, self::KEY, '10');

        try {
            DB::connection($this->centralConnectionName())->transaction(function () use ($service, $tenantId): void {
                $service->debit($tenantId, self::KEY, '4');

                throw new \RuntimeException('caller failed after the debit');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame('10.000', $service->balance($tenantId, self::KEY));
    }

    public function test_it_writes_the_central_database_even_inside_a_tenant(): void
    {
        $tenant = $this->createTenant();

        $inside = $this->inTenant($tenant, function () use ($tenant): array {
            $this->service()->credit((string) $tenant->id, self::KEY, '2.5');

            return [
                'default' => DB::getDefaultConnection(),
                'tenant_has_table' => Schema::hasTable('tenant_credit_ledger'),
                'balance' => $this->service()->balance((string) $tenant->id, self::KEY),
            ];
        });

        $this->assertNotSame($this->centralConnectionName(), $inside['default']);
        $this->assertFalse($inside['tenant_has_table'], 'The ledger never exists in a tenant database.');
        $this->assertSame('2.500', $inside['balance']);
        $this->assertSame(1, DB::connection($this->centralConnectionName())->table('tenant_credit_ledger')->where('tenant_id', $tenant->id)->count());
    }

    public function test_migrations_roll_back_and_re_run_cleanly(): void
    {
        $schema = Schema::connection($this->centralConnectionName());
        $migrations = [
            'database/migrations/2026_10_10_200720_create_tenant_credit_accounts_table.php',
            'database/migrations/2026_10_10_200710_create_tenant_credit_ledger_table.php',
            'database/migrations/2026_10_10_200700_add_included_credits_to_addons_table.php',
        ];

        $this->assertTrue($schema->hasColumns('tenant_credit_ledger', ['id', 'tenant_id', 'addon_key', 'delta', 'reason', 'billing_invoice_id', 'created_at']));
        $this->assertFalse($schema->hasColumn('tenant_credit_ledger', 'updated_at'), 'Append-only rows have no updated_at.');
        $this->assertTrue($schema->hasColumn('addons', 'included_credits'));

        foreach ($migrations as $path) {
            (require base_path($path))->down();
        }
        $this->assertFalse($schema->hasTable('tenant_credit_ledger'));
        $this->assertFalse($schema->hasTable('tenant_credit_accounts'));
        $this->assertFalse($schema->hasColumn('addons', 'included_credits'));

        foreach (array_reverse($migrations) as $path) {
            (require base_path($path))->up();
        }
        $this->assertTrue($schema->hasTable('tenant_credit_ledger'));
        $this->assertTrue($schema->hasTable('tenant_credit_accounts'));
        $this->assertTrue($schema->hasColumn('addons', 'included_credits'));
    }

    private function service(): CreditBalanceService
    {
        return app(CreditBalanceService::class);
    }
}
