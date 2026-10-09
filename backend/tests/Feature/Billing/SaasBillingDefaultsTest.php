<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\BillingPaymentMethod;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\BillingSequenceService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * CTO W1 Q3/Q4 [2026-10-09] billing defaults:
 * - SaaS invoice numbers are SUB-YYYY-00001 (prefix SUB, 5-digit counter) and restart every
 *   year, from config/billing.php defaults (no env override needed);
 * - billing_payments: (gateway, gateway_reference) is unique; the same reference on another
 *   gateway is a different payment (migration 2026_10_10_200540).
 */
#[Group('billing')]
#[Group('mysql')]
final class SaasBillingDefaultsTest extends TenantTestCase
{
    private const GATEWAY_MIGRATION = 'migrations/2026_10_10_200540_scope_billing_payments_gateway_reference_unique_to_gateway.php';

    public function test_config_defaults_are_sub_yearly_with_five_digits(): void
    {
        // The defaults themselves (independent of a developer's local .env overrides).
        $source = (string) file_get_contents(config_path('billing.php'));

        $this->assertStringContainsString("env('BILLING_INVOICE_PREFIX', 'SUB')", $source);
        $this->assertStringContainsString("env('BILLING_INVOICE_NUMBER_PERIOD', 'yearly')", $source);
        $this->assertStringContainsString("env('BILLING_INVOICE_NUMBER_PAD', 5)", $source);
    }

    public function test_first_invoice_of_each_year_is_sub_year_00001(): void
    {
        // The approved defaults asserted above.
        config([
            'billing.invoice_number' => ['prefix' => 'SUB', 'period' => 'yearly', 'pad' => 5],
            'billing.timezone' => 'Africa/Cairo',
        ]);
        $sequences = app(BillingSequenceService::class);

        $numbers = DB::connection($this->centralConnectionName())->transaction(fn (): array => [
            $sequences->nextInvoiceNumber(Carbon::parse('2026-03-01 10:00:00', 'Africa/Cairo')),
            $sequences->nextInvoiceNumber(Carbon::parse('2026-12-31 23:59:59', 'Africa/Cairo')),
            // 2026-12-31 22:30 UTC is already 2027 in Cairo: the year follows the billing timezone.
            $sequences->nextInvoiceNumber(Carbon::parse('2026-12-31 22:30:00', 'UTC')),
            $sequences->nextInvoiceNumber(Carbon::parse('2027-01-02 09:00:00', 'Africa/Cairo')),
        ]);

        $this->assertSame(['SUB-2026-00001', 'SUB-2026-00002', 'SUB-2027-00001', 'SUB-2027-00002'], $numbers);
    }

    public function test_same_reference_is_allowed_across_gateways_and_refused_on_one_gateway(): void
    {
        $invoice = $this->invoice();

        $this->payment($invoice, BillingGateway::Paymob, 'ref-100');
        $this->payment($invoice, BillingGateway::Fawry, 'ref-100');
        $this->payment($invoice, BillingGateway::Manual, 'ref-100');
        $this->payment($invoice, BillingGateway::Manual, null);
        $this->payment($invoice, BillingGateway::Manual, null);

        $this->assertSame(3, BillingPayment::query()->where('gateway_reference', 'ref-100')->count());

        $this->expectException(QueryException::class);
        $this->payment($invoice, BillingGateway::Fawry, 'ref-100');
    }

    public function test_gateway_migration_round_trips_and_refuses_to_merge_shared_references(): void
    {
        $schema = Schema::connection($this->centralConnectionName());
        $invoice = $this->invoice();

        // down() on data that fits the global unique: restores it.
        $this->payment($invoice, BillingGateway::Paymob, 'only-once');
        try {
            $this->runGatewayMigration('down');
            $this->assertTrue($schema->hasIndex('billing_payments', ['gateway_reference'], 'unique'));
            $this->assertFalse($schema->hasIndex('billing_payments', ['gateway', 'gateway_reference'], 'unique'));
        } finally {
            $this->runGatewayMigration('up');
        }
        $this->runGatewayMigration('up');
        $this->assertTrue($schema->hasIndex('billing_payments', ['gateway', 'gateway_reference'], 'unique'));
        $this->assertFalse($schema->hasIndex('billing_payments', ['gateway_reference'], 'unique'));

        // down() over a reference shared by two gateways refuses before touching the schema.
        $this->payment($invoice, BillingGateway::Fawry, 'only-once');
        try {
            $this->runGatewayMigration('down');
            $this->fail('down() must refuse while two gateways share a reference.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('gateway_reference', $e->getMessage());
        }
        $this->assertTrue($schema->hasIndex('billing_payments', ['gateway', 'gateway_reference'], 'unique'));
        $this->assertSame(2, BillingPayment::query()->where('gateway_reference', 'only-once')->count(), 'No payment record is deleted or rewritten.');
    }

    private function invoice(): BillingInvoice
    {
        $tenant = $this->createTenant();
        $plan = Plan::query()->create([
            'name' => 'plan-'.$tenant->id, 'slug' => 'plan-'.$tenant->id, 'price_monthly' => '449.000',
            'price_yearly' => '4490.000', 'features' => [], 'is_active' => true, 'sort_order' => 1,
        ]);
        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'billing_cycle' => BillingCycle::Monthly,
            'status' => SubscriptionStatus::Active, 'amount' => '449.000', 'starts_at' => now(), 'ends_at' => now()->addMonth(),
        ]);

        return BillingInvoice::query()->create([
            'number' => 'TST-'.$tenant->id,
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'type' => BillingInvoiceType::Plan,
            'lines' => [['kind' => 'plan', 'key' => 'basic', 'quantity' => 1, 'unit_price' => '449.000', 'total' => '449.000']],
            'subtotal' => '449.000',
            'total' => '449.000',
        ]);
    }

    private function payment(BillingInvoice $invoice, BillingGateway $gateway, ?string $reference): BillingPayment
    {
        return BillingPayment::query()->create([
            'billing_invoice_id' => $invoice->id,
            'tenant_id' => $invoice->tenant_id,
            'amount' => '449.000',
            'method' => BillingPaymentMethod::Instapay,
            'gateway' => $gateway,
            'gateway_reference' => $reference,
        ]);
    }

    private function runGatewayMigration(string $direction): void
    {
        $migration = require database_path(self::GATEWAY_MIGRATION);
        if (! is_object($migration) || ! method_exists($migration, $direction)) {
            $this->fail(self::GATEWAY_MIGRATION." must return a migration with {$direction}().");
        }

        $migration->{$direction}();
    }
}
