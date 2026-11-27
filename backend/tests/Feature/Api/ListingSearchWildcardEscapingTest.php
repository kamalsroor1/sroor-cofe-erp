<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\StockCountStatus;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\ReturnDocument;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Database\LikePattern;
use Illuminate\Support\Facades\Hash;
use Tests\TenantTestCase;

/**
 * GEN-181: listing search queries must escape LIKE wildcards (`%`, `_`, and the escape char `!`)
 * so searching `%` or `_` never acts as a wildcard on either MySQL or SQLite.
 */
final class ListingSearchWildcardEscapingTest extends TenantTestCase
{
    private Tenant $tenant;

    /** @var array<string, string> */
    private array $headers;

    private Store $store;

    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $admin = $this->tenantAdmin($this->tenant);
        $this->adminId = (int) $admin->id;
        $this->headers = $this->tenantHeaders($this->tenant);
        $this->store = $this->tenantStore($this->tenant);
    }

    public function test_like_pattern_helper_escapes_wildcards_and_escape_character(): void
    {
        $this->assertSame('!%', LikePattern::escape('%'));
        $this->assertSame('!_', LikePattern::escape('_'));
        $this->assertSame('!!', LikePattern::escape('!'));
        $this->assertSame('50!% Arabica!_01!!', LikePattern::escape('50% Arabica_01!'));
        $this->assertSame('%50!%!_!!%', LikePattern::contains('50%_!'));
        $this->assertSame('INV!_2026!%%', LikePattern::prefix('INV_2026%'));
    }

    public function test_customers_items_suppliers_and_users_listings_escape_wildcards(): void
    {
        $this->inTenant($this->tenant, function (): void {
            Customer::create(['name' => 'Regular Customer', 'phone' => '01011111111', 'is_active' => true]);
            Customer::create(['name' => 'VIP 50% Client', 'phone' => '01022222222', 'is_active' => true]);
            Customer::create(['name' => 'Tier_A Client', 'phone' => '01033333333', 'is_active' => true]);
            Customer::create(['name' => 'Alert!Client', 'phone' => '01044444444', 'is_active' => true]);

            $i1 = Item::create(['code' => 'REG-01', 'name' => 'Regular Beans', 'unit' => 'كجم', 'cost_price' => '100.000', 'selling_price' => '150.000', 'current_stock' => '5.000', 'is_active' => true]);
            $i2 = Item::create(['code' => 'PCT-50%', 'name' => 'Blend 50% Dark', 'unit' => 'كجم', 'cost_price' => '100.000', 'selling_price' => '150.000', 'current_stock' => '5.000', 'is_active' => true]);
            $i3 = Item::create(['code' => 'UND_01', 'name' => 'Blend_01 Light', 'unit' => 'كجم', 'cost_price' => '100.000', 'selling_price' => '150.000', 'current_stock' => '5.000', 'is_active' => true]);
            foreach ([$i1, $i2, $i3] as $item) {
                StoreStock::create(['store_id' => $this->store->id, 'item_id' => $item->id, 'quantity' => '5.000']);
            }

            Supplier::create(['name' => 'Normal Supplier', 'company_name' => 'Co A', 'phone' => '01111111111', 'is_active' => true]);
            Supplier::create(['name' => 'Wholesale 100%', 'company_name' => 'Co Pct', 'phone' => '01122222222', 'is_active' => true]);
            Supplier::create(['name' => 'Roast_Lab', 'company_name' => 'Co Und', 'phone' => '01133333333', 'is_active' => true]);

            User::factory()->create(['name' => 'Cashier One', 'phone' => '01211111111', 'password' => Hash::make('password'), 'is_active' => true, 'default_store_id' => $this->store->id]);
            User::factory()->create(['name' => 'User 20%', 'phone' => '01222222222', 'password' => Hash::make('password'), 'is_active' => true, 'default_store_id' => $this->store->id]);
            User::factory()->create(['name' => 'User_Two', 'phone' => '01233333333', 'password' => Hash::make('password'), 'is_active' => true, 'default_store_id' => $this->store->id]);
        });

        // Customers: %, _, !, normal
        $this->assertSearchMatchesOnly('/api/v1/customers', '%', ['VIP 50% Client']);
        $this->assertSearchMatchesOnly('/api/v1/customers', '_', ['Tier_A Client']);
        $this->assertSearchMatchesOnly('/api/v1/customers', '!', ['Alert!Client']);
        $this->assertSearchMatchesOnly('/api/v1/customers', 'Regular', ['Regular Customer']);

        // Items: %, _, normal
        $this->assertSearchMatchesOnly('/api/v1/items', '%', ['Blend 50% Dark']);
        $this->assertSearchMatchesOnly('/api/v1/items', '_', ['Blend_01 Light']);
        $this->assertSearchMatchesOnly('/api/v1/items', 'Regular', ['Regular Beans']);

        // Store stocks: %, _, normal
        $this->assertSearchMatchesOnly('/api/v1/stores/stocks', '%', ['Blend 50% Dark'], 'item_name');
        $this->assertSearchMatchesOnly('/api/v1/stores/stocks', '_', ['Blend_01 Light'], 'item_name');
        $this->assertSearchMatchesOnly('/api/v1/stores/stocks', 'Regular', ['Regular Beans'], 'item_name');

        // Suppliers: %, _, normal
        $this->assertSearchMatchesOnly('/api/v1/suppliers', '%', ['Wholesale 100%']);
        $this->assertSearchMatchesOnly('/api/v1/suppliers', '_', ['Roast_Lab']);
        $this->assertSearchMatchesOnly('/api/v1/suppliers', 'Normal', ['Normal Supplier']);

        // Users: %, _, normal
        $this->assertSearchMatchesOnly('/api/v1/users', '%', ['User 20%']);
        $this->assertSearchMatchesOnly('/api/v1/users', '_', ['User_Two']);
        $this->assertSearchMatchesOnly('/api/v1/users', 'Cashier', ['Cashier One']);
    }

    public function test_invoices_purchases_returns_expenses_transfers_stock_counts_trash_and_logs_escape_wildcards(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $customer = Customer::create(['name' => 'Plain Customer', 'phone' => '01055555555', 'is_active' => true]);
            $pctCustomer = Customer::create(['name' => 'Client 15%', 'phone' => '01066666666', 'is_active' => true]);
            $supplier = Supplier::create(['name' => 'Plain Supplier', 'phone' => '01155555555', 'is_active' => true]);
            $otherStore = Store::create(['name' => 'Branch_North', 'code' => 'BR-N', 'type' => 'retail_shop', 'is_main' => false, 'is_active' => true]);

            $invRegular = Invoice::create([
                'invoice_number' => 'INV-20261127-0001',
                'customer_id' => $customer->id,
                'user_id' => $this->adminId,
                'store_id' => $this->store->id,
                'invoice_date' => '2026-11-27',
                'payment_type' => 'cash',
                'payment_method' => 'cash',
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'subtotal' => '100.000',
                'net_total' => '100.000',
                'paid_amount' => '100.000',
                'remaining_amount' => '0.000',
            ]);
            Invoice::create([
                'invoice_number' => 'INV-20261127-0002',
                'customer_id' => $pctCustomer->id,
                'user_id' => $this->adminId,
                'store_id' => $this->store->id,
                'invoice_date' => '2026-11-27',
                'payment_type' => 'cash',
                'payment_method' => 'cash',
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'subtotal' => '100.000',
                'net_total' => '100.000',
                'paid_amount' => '100.000',
                'remaining_amount' => '0.000',
            ]);

            Purchase::create([
                'purchase_number' => 'PUR-20261127-0001',
                'supplier_id' => $supplier->id,
                'user_id' => $this->adminId,
                'store_id' => $this->store->id,
                'purchase_date' => '2026-11-27',
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'subtotal' => '100.000',
                'net_total' => '100.000',
                'paid_amount' => '100.000',
                'remaining_amount' => '0.000',
                'notes' => 'Standard delivery',
            ]);
            Purchase::create([
                'purchase_number' => 'PUR-20261127-0002',
                'supplier_id' => $supplier->id,
                'user_id' => $this->adminId,
                'store_id' => $this->store->id,
                'purchase_date' => '2026-11-27',
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'subtotal' => '100.000',
                'net_total' => '100.000',
                'paid_amount' => '100.000',
                'remaining_amount' => '0.000',
                'notes' => 'Promo 30% batch',
            ]);

            ReturnDocument::create([
                'return_number' => 'RET-20261127-0001',
                'return_type' => 'sales_return',
                'invoice_id' => $invRegular->id,
                'customer_id' => $customer->id,
                'user_id' => $this->adminId,
                'store_id' => $this->store->id,
                'total_amount' => '20.000',
                'return_date' => '2026-11-27',
                'reason' => 'Damaged pack',
                'refund_method' => 'cash',
            ]);
            ReturnDocument::create([
                'return_number' => 'RET-20261127-0002',
                'return_type' => 'sales_return',
                'invoice_id' => $invRegular->id,
                'customer_id' => $pctCustomer->id,
                'user_id' => $this->adminId,
                'store_id' => $this->store->id,
                'total_amount' => '20.000',
                'return_date' => '2026-11-27',
                'reason' => 'Shortage 10%',
                'refund_method' => 'cash',
            ]);

            Expense::create([
                'expense_number' => 'EXP-20261127-0001',
                'category' => 'utilities',
                'title' => 'Electricity Bill',
                'amount' => '50.000',
                'expense_date' => '2026-11-27',
                'payment_method' => 'cash',
                'user_id' => $this->adminId,
                'store_id' => $this->store->id,
            ]);
            Expense::create([
                'expense_number' => 'EXP-20261127-0002',
                'category' => 'utilities',
                'title' => 'Tax 14% Fee',
                'amount' => '50.000',
                'expense_date' => '2026-11-27',
                'payment_method' => 'cash',
                'user_id' => $this->adminId,
                'store_id' => $this->store->id,
            ]);

            StockTransfer::create([
                'transfer_number' => 'TRF-20261127-0001',
                'from_store_id' => $this->store->id,
                'to_store_id' => $otherStore->id,
                'user_id' => $this->adminId,
                'transfer_date' => '2026-11-27',
                'status' => 'confirmed',
                'notes' => 'Normal transfer',
            ]);
            StockTransfer::create([
                'transfer_number' => 'TRF-20261127-0002',
                'from_store_id' => $this->store->id,
                'to_store_id' => $otherStore->id,
                'user_id' => $this->adminId,
                'transfer_date' => '2026-11-27',
                'status' => 'confirmed',
                'notes' => 'Reserve 25%',
            ]);

            StockCount::create([
                'count_number' => 'CNT-20261127-0001',
                'store_id' => $this->store->id,
                'open_store_id' => null,
                'status' => StockCountStatus::Posted,
                'is_blind' => false,
                'notes' => 'Monthly audit',
                'created_by' => $this->adminId,
            ]);
            StockCount::create([
                'count_number' => 'CNT-20261127-0002',
                'store_id' => $this->store->id,
                'open_store_id' => null,
                'status' => StockCountStatus::Posted,
                'is_blind' => false,
                'notes' => 'Audit_50% variance',
                'created_by' => $this->adminId,
            ]);

            $trashedPlain = Item::create(['code' => 'TR-01', 'name' => 'Deleted Coffee', 'unit' => 'كجم', 'cost_price' => '10.000', 'selling_price' => '20.000', 'current_stock' => '0.000', 'is_active' => true]);
            $trashedPct = Item::create(['code' => 'TR-02', 'name' => 'Deleted 99%', 'unit' => 'كجم', 'cost_price' => '10.000', 'selling_price' => '20.000', 'current_stock' => '0.000', 'is_active' => true]);
            $trashedPlain->delete();
            $trashedPct->delete();

            ActivityLog::create([
                'user_id' => $this->adminId,
                'store_id' => $this->store->id,
                'module' => 'sales',
                'action' => 'created',
                'description' => 'Created regular order',
            ]);
            ActivityLog::create([
                'user_id' => $this->adminId,
                'store_id' => $this->store->id,
                'module' => 'sales',
                'action' => 'created',
                'description' => 'Applied 10% discount',
            ]);
        });

        // Invoices (%)
        $this->assertSearchMatchesOnly('/api/v1/invoices', '%', ['INV-20261127-0002'], 'invoice_number');
        $this->assertSearchMatchesOnly('/api/v1/invoices', '_', [], 'invoice_number');
        $this->assertSearchMatchesOnly('/api/v1/invoices', 'INV-20261127-0001', ['INV-20261127-0001'], 'invoice_number');

        // Return sources (%)
        $this->assertSearchMatchesOnly('/api/v1/return-sources/sales_return', '%', ['INV-20261127-0002'], 'number');
        $this->assertSearchMatchesOnly('/api/v1/return-sources/sales_return', '_', [], 'number');

        // Purchases (%)
        $this->assertSearchMatchesOnly('/api/v1/purchases', '%', ['PUR-20261127-0002'], 'purchase_number');
        $this->assertSearchMatchesOnly('/api/v1/purchases', '_', [], 'purchase_number');

        // Returns (%)
        $this->assertSearchMatchesOnly('/api/v1/returns', '%', ['RET-20261127-0002'], 'return_number');
        $this->assertSearchMatchesOnly('/api/v1/returns', '_', [], 'return_number');

        // Expenses (%)
        $this->assertSearchMatchesOnly('/api/v1/expenses', '%', ['Tax 14% Fee'], 'title');
        $this->assertSearchMatchesOnly('/api/v1/expenses', '_', [], 'title');

        // Transfers (% and _)
        $this->assertSearchMatchesOnly('/api/v1/transfers', '%', ['TRF-20261127-0002'], 'transfer_number');
        $this->assertSearchMatchesOnly('/api/v1/transfers', '_', ['TRF-20261127-0001', 'TRF-20261127-0002'], 'transfer_number');

        // Stock counts (% and _)
        $this->assertSearchMatchesOnly('/api/v1/stock-counts', '%', ['CNT-20261127-0002'], 'count_number');
        $this->assertSearchMatchesOnly('/api/v1/stock-counts', '_', ['CNT-20261127-0002'], 'count_number');

        // Trash (%)
        $this->getJson('/api/v1/trash?tab=items&search='.urlencode('%'), $this->headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Deleted 99%');

        $this->getJson('/api/v1/trash?tab=items&search='.urlencode('_'), $this->headers)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Activity logs (%)
        $this->assertSearchMatchesOnly('/api/v1/activity-logs', '%', ['Applied 10% discount'], 'description');
        $this->assertSearchMatchesOnly('/api/v1/activity-logs', '_', [], 'description');
    }

    /**
     * @param  list<string>  $expectedValues
     */
    private function assertSearchMatchesOnly(
        string $endpoint,
        string $search,
        array $expectedValues,
        string $field = 'name',
    ): void {
        $response = $this->getJson($endpoint.'?search='.urlencode($search), $this->headers)
            ->assertOk();

        /** @var list<array<string, mixed>> $rows */
        $rows = $response->json('data') ?? [];
        $actual = array_values(array_map(
            static fn (array $row): mixed => data_get($row, $field),
            $rows,
        ));

        sort($expectedValues);
        sort($actual);

        $this->assertSame(
            $expectedValues,
            $actual,
            "Failed asserting literal search '{$search}' on {$endpoint} matched only ".json_encode($expectedValues),
        );
    }
}
