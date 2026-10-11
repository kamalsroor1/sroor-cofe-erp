<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\BillingPaymentMethod;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\BillingSequence;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * ENTI-1.6: central SaaS billing tables `billing_invoices`, `billing_payments`,
 * `billing_sequences` (completely separate from the tenant POS `invoices`).
 *
 * - money columns DECIMAL(12,3), read back as exact numeric strings;
 * - status / type / method / gateway are string(20|30) + App\Enums\Billing casts;
 * - `number` is unique; `gateway_reference` is unique PER GATEWAY and nullable (many
 *   manual payments without a reference are allowed; CTO W1 Q3, migration 200540);
 * - billing history survives the tenant row (no FK cascade on tenant_id).
 */
#[Group('billing')]
#[Group('mysql')]
final class BillingInvoicesSchemaTest extends TenantTestCase
{
    private const MIGRATIONS = [
        'billing_sequences' => 'migrations/2026_10_10_200500_create_billing_sequences_table.php',
        'billing_invoices' => 'migrations/2026_10_10_200510_create_billing_invoices_table.php',
        'billing_payments' => 'migrations/2026_10_10_200520_create_billing_payments_table.php',
    ];

    /**
     * Later migrations that alter or reference these tables, oldest first: rolled back
     * newest-first before the tables are dropped, re-applied in this order afterwards.
     * 200710 holds a FK to billing_invoices (MySQL 3730 "cannot drop referenced table").
     */
    private const ALTER_MIGRATIONS = [
        'migrations/2026_10_10_200540_scope_billing_payments_gateway_reference_unique_to_gateway.php',
        'migrations/2026_10_10_200710_create_tenant_credit_ledger_table.php',
    ];

    public function test_billing_invoices_table_has_the_designed_columns_and_indexes(): void
    {
        $schema = Schema::connection($this->centralConnectionName());

        $this->assertColumns('billing_invoices', [
            'id', 'number', 'tenant_id', 'subscription_id', 'type', 'status', 'billing_cycle',
            'period_start', 'period_end', 'lines', 'subtotal', 'discount', 'tax_rate', 'tax', 'total',
            'currency', 'issued_at', 'due_at', 'paid_at', 'voided_at', 'issued_by', 'notes',
            'created_at', 'updated_at',
        ]);

        $columns = collect($schema->getColumns('billing_invoices'))->keyBy('name');
        foreach (['number', 'tenant_id', 'type', 'status', 'lines', 'subtotal', 'discount', 'tax_rate', 'tax', 'total', 'currency'] as $column) {
            $this->assertFalse((bool) $columns[$column]['nullable'], "billing_invoices.{$column} must be NOT NULL.");
        }
        foreach (['subscription_id', 'billing_cycle', 'period_start', 'period_end', 'issued_at', 'due_at', 'paid_at', 'voided_at', 'issued_by', 'notes'] as $column) {
            $this->assertTrue((bool) $columns[$column]['nullable'], "billing_invoices.{$column} must be nullable.");
        }

        if ($this->isMysql()) {
            foreach (['subtotal', 'discount', 'tax', 'total'] as $money) {
                $this->assertSame('decimal(12,3)', strtolower((string) $columns[$money]['type']), "billing_invoices.{$money}");
            }
            $this->assertSame('decimal(6,3)', strtolower((string) $columns['tax_rate']['type']));
        }

        $this->assertTrue($schema->hasIndex('billing_invoices', ['number'], 'unique'));
        $this->assertTrue($schema->hasIndex('billing_invoices', ['tenant_id', 'status']), 'tenant invoice list filters on tenant + status.');
        $this->assertTrue($schema->hasIndex('billing_invoices', ['tenant_id', 'type', 'period_start']), 'ENTI-3.10 duplicate-cycle check.');
        $this->assertTrue($schema->hasIndex('billing_invoices', ['status', 'due_at']), 'overdue sweeps filter on status + due_at.');
        $this->assertTrue($schema->hasIndex('billing_invoices', ['subscription_id']));
    }

    public function test_billing_payments_table_has_the_designed_columns_and_indexes(): void
    {
        $schema = Schema::connection($this->centralConnectionName());

        $this->assertColumns('billing_payments', [
            'id', 'billing_invoice_id', 'tenant_id', 'amount', 'currency', 'method', 'gateway',
            'gateway_reference', 'status', 'proof_path', 'submitted_by', 'paid_at', 'verified_by',
            'verified_at', 'rejection_reason', 'raw_payload', 'created_at', 'updated_at',
        ]);

        $columns = collect($schema->getColumns('billing_payments'))->keyBy('name');
        foreach (['billing_invoice_id', 'tenant_id', 'amount', 'currency', 'method', 'gateway', 'status'] as $column) {
            $this->assertFalse((bool) $columns[$column]['nullable'], "billing_payments.{$column} must be NOT NULL.");
        }
        foreach (['gateway_reference', 'proof_path', 'submitted_by', 'paid_at', 'verified_by', 'verified_at', 'rejection_reason', 'raw_payload'] as $column) {
            $this->assertTrue((bool) $columns[$column]['nullable'], "billing_payments.{$column} must be nullable.");
        }

        if ($this->isMysql()) {
            $this->assertSame('decimal(12,3)', strtolower((string) $columns['amount']['type']));
        }

        $this->assertTrue($schema->hasIndex('billing_payments', ['gateway', 'gateway_reference'], 'unique'), '(gateway, gateway_reference) is the idempotency key.');
        $this->assertFalse($schema->hasIndex('billing_payments', ['gateway_reference'], 'unique'), 'A reference is unique per gateway, not globally (CTO W1 Q3).');
        $this->assertTrue($schema->hasIndex('billing_payments', ['billing_invoice_id']));
        $this->assertTrue($schema->hasIndex('billing_payments', ['tenant_id', 'status']));
        $this->assertTrue($schema->hasIndex('billing_payments', ['status', 'created_at']), 'super-admin review queue.');
    }

    public function test_billing_sequences_table_is_unique_per_key_and_period(): void
    {
        $schema = Schema::connection($this->centralConnectionName());

        $this->assertColumns('billing_sequences', ['id', 'key', 'period', 'last_value', 'created_at', 'updated_at']);
        $columns = collect($schema->getColumns('billing_sequences'))->keyBy('name');
        foreach (['key', 'period', 'last_value'] as $column) {
            $this->assertFalse((bool) $columns[$column]['nullable'], "billing_sequences.{$column} must be NOT NULL (a NULL period would bypass the unique index).");
        }
        $this->assertTrue($schema->hasIndex('billing_sequences', ['key', 'period'], 'unique'));

        BillingSequence::query()->create(['key' => 'billing_invoice', 'period' => '2026', 'last_value' => 3]);
        BillingSequence::query()->create(['key' => 'billing_invoice', 'period' => '2027']);

        $this->expectException(QueryException::class);
        BillingSequence::query()->create(['key' => 'billing_invoice', 'period' => '2026']);
    }

    public function test_invoice_casts_defaults_and_relations(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant();
        $superAdmin = $this->centralSuperAdmin();

        $invoice = $this->invoice($tenant, $subscription, [
            'lines' => [
                ['kind' => 'plan', 'key' => 'pro', 'quantity' => 1, 'unit_price' => '449.000', 'total' => '449.000'],
                ['kind' => 'addon', 'key' => 'addon.store', 'quantity' => 3, 'unit_price' => '212.000', 'total' => '636.000'],
            ],
            'subtotal' => '1085',
            'tax_rate' => '14',
            'tax' => '151.9',
            'total' => '1236.900',
            'billing_cycle' => BillingCycle::Monthly,
            'period_start' => now()->startOfDay(),
            'period_end' => now()->startOfDay()->addMonth(),
            'issued_at' => now(),
            'due_at' => now()->addDays(7),
            'issued_by' => $superAdmin->id,
        ]);

        $invoice = BillingInvoice::query()->findOrFail($invoice->id);

        $this->assertSame('1085.000', $invoice->subtotal);
        $this->assertSame('0.000', $invoice->discount);
        $this->assertSame('14.000', $invoice->tax_rate);
        $this->assertSame('151.900', $invoice->tax);
        $this->assertSame('1236.900', $invoice->total);
        $this->assertSame('EGP', $invoice->currency);
        $this->assertSame(BillingInvoiceStatus::Pending, $invoice->status, 'A new invoice awaits payment.');
        $this->assertSame(BillingInvoiceType::Renewal, $invoice->type);
        $this->assertSame(BillingCycle::Monthly, $invoice->billing_cycle);
        $this->assertSame('636.000', $invoice->lines[1]['total']);
        $this->assertNotNull($invoice->due_at);
        $this->assertNull($invoice->paid_at);

        $this->assertSame($tenant->id, $invoice->tenant?->id);
        $this->assertSame($subscription->id, $invoice->subscription?->id);
        // IDEN-1.8: the issuer is the central operator, never a (legacy or tenant) User row.
        $this->assertInstanceOf(CentralUser::class, $invoice->issuer);
        $this->assertSame($superAdmin->id, $invoice->issuer->id);

        $payment = $this->payment($invoice, ['amount' => '1236.9']);
        $this->assertSame([$payment->id], $invoice->payments()->pluck('id')->all());
    }

    public function test_payment_casts_defaults_and_relations(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant();
        $invoice = $this->invoice($tenant, $subscription);
        $verifier = $this->centralSuperAdmin();

        $payment = $this->payment($invoice, [
            'amount' => '449',
            'method' => BillingPaymentMethod::VodafoneCash,
            'gateway_reference' => 'manual:abc-123',
            'raw_payload' => ['note' => 'transfer at 10:00'],
            'verified_by' => $verifier->id,
            'verified_at' => now(),
            'status' => BillingPaymentStatus::Verified,
        ]);

        $payment = BillingPayment::query()->findOrFail($payment->id);

        $this->assertSame('449.000', $payment->amount);
        $this->assertSame('EGP', $payment->currency);
        $this->assertSame(BillingPaymentMethod::VodafoneCash, $payment->method);
        $this->assertSame(BillingGateway::Manual, $payment->gateway);
        $this->assertSame(BillingPaymentStatus::Verified, $payment->status);
        $this->assertSame(['note' => 'transfer at 10:00'], $payment->raw_payload);
        $this->assertSame($invoice->id, $payment->invoice->id);
        $this->assertSame($tenant->id, $payment->tenant?->id);
        $this->assertInstanceOf(CentralUser::class, $payment->verifier);
        $this->assertSame($verifier->id, $payment->verifier->id);

        $fresh = $this->payment($invoice);
        $fresh = BillingPayment::query()->findOrFail($fresh->id);
        $this->assertSame(BillingPaymentStatus::Pending, $fresh->status, 'A submitted payment awaits review.');
        $this->assertSame(BillingGateway::Manual, $fresh->gateway);
    }

    public function test_enum_columns_store_the_enum_values(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant();
        $invoice = $this->invoice($tenant, $subscription, ['type' => BillingInvoiceType::Service, 'status' => BillingInvoiceStatus::Void]);
        $payment = $this->payment($invoice, ['method' => BillingPaymentMethod::FawryReference, 'gateway' => BillingGateway::Fawry, 'status' => BillingPaymentStatus::Rejected]);

        $db = DB::connection($this->centralConnectionName());
        $invoiceRow = $db->table('billing_invoices')->where('id', $invoice->id)->first();
        $paymentRow = $db->table('billing_payments')->where('id', $payment->id)->first();

        $this->assertNotNull($invoiceRow);
        $this->assertNotNull($paymentRow);
        $this->assertSame('service', $invoiceRow->type);
        $this->assertSame('void', $invoiceRow->status);
        $this->assertSame('fawry_reference', $paymentRow->method);
        $this->assertSame('fawry', $paymentRow->gateway);
        $this->assertSame('rejected', $paymentRow->status);
    }

    public function test_invoice_number_is_unique(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant();
        $this->invoice($tenant, $subscription, ['number' => 'INV-2026-000001']);

        $this->expectException(QueryException::class);
        $this->invoice($tenant, $subscription, ['number' => 'INV-2026-000001']);
    }

    public function test_gateway_reference_is_unique_per_gateway_but_nullable(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant();
        $invoice = $this->invoice($tenant, $subscription);

        // Many payments without a gateway reference are fine.
        $this->payment($invoice);
        $this->payment($invoice);
        $this->payment($invoice, ['gateway' => BillingGateway::Paymob, 'gateway_reference' => 'txn-1']);
        // The same reference on another gateway is a different payment (CTO W1 Q3).
        $this->payment($invoice, ['gateway' => BillingGateway::Fawry, 'gateway_reference' => 'txn-1']);

        $this->assertSame(4, BillingPayment::query()->where('billing_invoice_id', $invoice->id)->count());

        // The same reference twice on one gateway is still refused.
        $this->expectException(QueryException::class);
        $this->payment($invoice, ['gateway' => BillingGateway::Paymob, 'gateway_reference' => 'txn-1']);
    }

    public function test_an_invoice_with_payments_cannot_be_deleted(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant();
        $invoice = $this->invoice($tenant, $subscription);
        $this->payment($invoice);

        $this->expectException(QueryException::class);
        $invoice->delete();
    }

    public function test_billing_history_survives_the_subscription_row(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant();
        $invoice = $this->invoice($tenant, $subscription);

        $subscription->delete();

        $invoice = BillingInvoice::query()->findOrFail($invoice->id);
        $this->assertNull($invoice->subscription_id, 'The invoice keeps its snapshot when the subscription row goes.');
        $this->assertSame($tenant->id, $invoice->tenant_id);
    }

    public function test_billing_history_is_not_cascaded_away_with_the_tenant_id(): void
    {
        // No FK on tenant_id: an archived/deleted tenant's invoices and payments stay
        // as the accounting record (the tenant row itself is handled by OPS-11).
        $foreignTables = collect(Schema::connection($this->centralConnectionName())->getForeignKeys('billing_invoices'))
            ->pluck('foreign_table')->all();
        $this->assertNotContains('tenants', $foreignTables);

        $foreignTables = collect(Schema::connection($this->centralConnectionName())->getForeignKeys('billing_payments'))
            ->pluck('foreign_table')->all();
        $this->assertNotContains('tenants', $foreignTables);
        $this->assertContains('billing_invoices', $foreignTables);
    }

    public function test_models_read_the_central_db_inside_a_tenant(): void
    {
        [$tenant, $subscription] = $this->subscribedTenant();
        $invoice = $this->invoice($tenant, $subscription);
        $payment = $this->payment($invoice, ['gateway_reference' => 'manual:central-check']);
        $central = $this->centralConnectionName();

        $seen = $this->inTenant($tenant, fn (): array => [
            'default' => DB::getDefaultConnection(),
            'connections' => [
                (new BillingInvoice)->getConnectionName(),
                (new BillingPayment)->getConnectionName(),
                (new BillingSequence)->getConnectionName(),
            ],
            'invoice_number' => BillingInvoice::query()->findOrFail($invoice->id)->number,
            'payment_reference' => BillingPayment::query()->findOrFail($payment->id)->gateway_reference,
            'payment_invoice' => BillingPayment::query()->findOrFail($payment->id)->invoice->number,
        ]);

        $this->assertNotSame($central, $seen['default'], 'Tenancy must switch the default connection for this test to mean anything.');
        $this->assertSame([$central, $central, $central], $seen['connections']);
        $this->assertSame($invoice->number, $seen['invoice_number']);
        $this->assertSame('manual:central-check', $seen['payment_reference']);
        $this->assertSame($invoice->number, $seen['payment_invoice']);
    }

    public function test_migrations_roll_back_and_reapply(): void
    {
        try {
            foreach (array_reverse(self::ALTER_MIGRATIONS) as $migration) {
                $this->runMigration($migration, 'down');
            }
            foreach (array_reverse(self::MIGRATIONS, true) as $table => $migration) {
                $this->runMigration($migration, 'down');
                $this->assertFalse(Schema::hasTable($table), "{$table} must be dropped by down().");
                // Running down() twice is harmless.
                $this->runMigration($migration, 'down');
            }
        } finally {
            foreach ([...array_values(self::MIGRATIONS), ...self::ALTER_MIGRATIONS] as $migration) {
                $this->runMigration($migration, 'up');
            }
        }

        foreach (self::MIGRATIONS as $table => $migration) {
            $this->assertTrue(Schema::hasTable($table));
            // up() is idempotent too.
            $this->runMigration($migration, 'up');
            $this->assertTrue(Schema::hasTable($table));
        }
    }

    /**
     * @param  list<string>  $expected
     */
    private function assertColumns(string $table, array $expected): void
    {
        $schema = Schema::connection($this->centralConnectionName());

        foreach ($expected as $column) {
            $this->assertTrue($schema->hasColumn($table, $column), "{$table}.{$column} is missing.");
        }
    }

    private function isMysql(): bool
    {
        return in_array(DB::connection($this->centralConnectionName())->getDriverName(), ['mysql', 'mariadb'], true);
    }

    /**
     * @return array{0: Tenant, 1: Subscription}
     */
    private function subscribedTenant(): array
    {
        $tenant = $this->createTenant();
        $plan = Plan::query()->create([
            'name' => 'plan-'.$tenant->id,
            'slug' => 'plan-'.$tenant->id,
            'price_monthly' => '449.000',
            'price_yearly' => '4490.000',
            'features' => [],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => BillingCycle::Monthly,
            'status' => SubscriptionStatus::Active,
            'amount' => '449.000',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ]);

        return [$tenant, $subscription];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function invoice(Tenant $tenant, Subscription $subscription, array $attributes = []): BillingInvoice
    {
        static $sequence = 0;
        $sequence++;

        return BillingInvoice::query()->create(array_merge([
            'number' => 'TST-'.$tenant->id.'-'.$sequence,
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'type' => BillingInvoiceType::Renewal,
            'lines' => [['kind' => 'plan', 'key' => 'basic', 'quantity' => 1, 'unit_price' => '449.000', 'total' => '449.000']],
            'subtotal' => '449.000',
            'total' => '449.000',
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function payment(BillingInvoice $invoice, array $attributes = []): BillingPayment
    {
        return BillingPayment::query()->create(array_merge([
            'billing_invoice_id' => $invoice->id,
            'tenant_id' => $invoice->tenant_id,
            'amount' => $invoice->total,
            'method' => BillingPaymentMethod::Instapay,
        ], $attributes));
    }

    private function runMigration(string $path, string $direction): void
    {
        $migration = require database_path($path);
        if (! is_object($migration) || ! method_exists($migration, $direction)) {
            $this->fail($path." must return a migration with {$direction}().");
        }

        $migration->{$direction}();
    }
}
