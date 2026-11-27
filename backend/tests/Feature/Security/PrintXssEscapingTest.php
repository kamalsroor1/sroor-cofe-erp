<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * GEN-183: Verify that user-controlled XSS payloads (<img src=x onerror=alert(1)>)
 * in item names, customer names, and notes are rendered as escaped plain text
 * across Blade invoice, public receipt, item movements, and daily journal print views.
 */
final class PrintXssEscapingTest extends TenantTestCase
{
    private const XSS_PAYLOAD = '<img src=x onerror=alert(1)>';

    private const ESCAPED_PAYLOAD = '&lt;img src=x onerror=alert(1)&gt;';

    private Tenant $tenant;

    private Store $store;

    private User $admin;

    private Customer $customer;

    private Item $item;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);
        URL::forceRootUrl('http://'.$this->tenantDomain($this->tenant));

        $this->store = $this->adoptMainStore();
        $this->admin = User::query()->findOrFail($this->tenantAdmin($this->tenant)->getKey());
        $this->admin->assignRole(Role::findByName('admin'));

        $this->customer = Customer::create([
            'name' => 'عميل '.self::XSS_PAYLOAD,
            'phone' => '01000008123',
            'address' => 'عنوان '.self::XSS_PAYLOAD,
            'current_balance' => '0.000',
            'is_active' => true,
        ]);

        $this->item = Item::create([
            'name' => 'صنف '.self::XSS_PAYLOAD,
            'code' => 'XSS-01',
            'category' => 'coffee_beans',
            'cost_price' => '100.000',
            'selling_price' => '150.000',
            'current_stock' => '25.000',
            'min_stock_level' => '1.000',
            'is_active' => true,
        ]);

        $this->invoice = Invoice::create([
            'invoice_number' => 'INV-XSS-1001',
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'invoice_date' => now()->toDateString(),
            'subtotal' => '150.000',
            'discount_amount' => '0.000',
            'net_total' => '150.000',
            'paid_amount' => '150.000',
            'remaining_amount' => '0.000',
            'total_cost' => '100.000',
            'status' => 'confirmed',
            'payment_method' => 'cash',
            'notes' => 'ملاحظة '.self::XSS_PAYLOAD,
        ]);

        InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'item_id' => $this->item->id,
            'quantity' => '1.000',
            'unit_price' => '150.000',
            'cost_price' => '100.000',
            'discount_amount' => '0.000',
            'total_price' => '150.000',
        ]);

        StockMovement::create([
            'item_id' => $this->item->id,
            'movement_type' => 'purchase_in',
            'quantity' => '5.000',
            'stock_before' => '20.000',
            'stock_after' => '25.000',
            'unit_cost' => '100.000',
            'source_type' => 'purchase',
            'source_id' => 1,
            'document_number' => 'DOC-XSS-1',
            'notes' => 'حركة '.self::XSS_PAYLOAD,
            'user_id' => $this->admin->id,
            'store_id' => $this->store->id,
        ]);

        Expense::create([
            'expense_number' => 'EXP-XSS-1',
            'category' => 'مصروفات',
            'title' => 'بند '.self::XSS_PAYLOAD,
            'amount' => '25.000',
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'notes' => 'ملاحظة '.self::XSS_PAYLOAD,
            'store_id' => $this->store->id,
            'user_id' => $this->admin->id,
        ]);
    }

    private function mountProbe(string $name, string $probeUri): void
    {
        $route = Route::getRoutes()->getByName($name);
        $this->assertNotNull($route, "Route [{$name}] is not registered.");

        $action = $route->getAction();
        unset($action['as'], $action['prefix'], $action['domain'], $action['where'], $action['middleware'], $action['excluded_middleware']);
        $action['middleware'] = $route->gatherMiddleware();

        $probe = (new RoutingRoute(['GET', 'HEAD'], ltrim($probeUri, '/'), $action))
            ->name('qa.xss.'.str_replace('.', '_', $name))
            ->setRouter(app('router'))
            ->setContainer(app());

        $existing = Route::getRoutes();
        $rebuilt = new RouteCollection;
        $rebuilt->add($probe);
        foreach ($existing->getRoutes() as $r) {
            $rebuilt->add($r);
        }
        Route::setRoutes($rebuilt);
    }

    private function assertEscapedHtml(string $html): void
    {
        $this->assertStringNotContainsString(self::XSS_PAYLOAD, $html);
        $this->assertStringContainsString(self::ESCAPED_PAYLOAD, $html);
    }

    /** @return array<string, array{string, string}> */
    public static function invoicePrintRoutes(): array
    {
        return [
            'thermal' => ['invoices.print.thermal', '/__qa/xss/invoices/{id}/print/thermal'],
            'a4' => ['invoices.print.a4', '/__qa/xss/invoices/{id}/print/a4'],
        ];
    }

    #[DataProvider('invoicePrintRoutes')]
    public function test_invoice_print_pages_escape_item_and_customer_names(string $name, string $probe): void
    {
        $this->mountProbe($name, $probe);

        $response = $this->actingAs($this->admin)
            ->get(str_replace('{id}', (string) $this->invoice->id, $probe))
            ->assertOk();

        $this->assertEscapedHtml((string) $response->getContent());
    }

    public function test_public_receipt_escapes_item_and_customer_names(): void
    {
        $signedUrl = (string) $this->postJson(
            "/api/v1/invoices/{$this->invoice->id}/receipt-link",
            [],
            $this->tenantHeaders($this->tenant, $this->admin),
        )->assertOk()->json('data.url');

        $parts = parse_url($signedUrl);
        $target = $this->tenantUrl($this->tenant, ($parts['path'] ?? '/').'?'.($parts['query'] ?? ''));

        $response = $this->get($target)->assertOk();
        $this->assertEscapedHtml((string) $response->getContent());
    }

    public function test_item_movements_print_escapes_item_name_and_movement_notes(): void
    {
        $response = $this->actingAs($this->admin)
            ->get("/items/{$this->item->id}/movements/print")
            ->assertOk();

        $this->assertEscapedHtml((string) $response->getContent());
    }

    public function test_daily_journal_print_escapes_customer_names_and_expense_notes(): void
    {
        $this->mountProbe('daily.journal.print', '/__qa/xss/daily-journal/print');

        $response = $this->actingAs($this->admin)
            ->get('/__qa/xss/daily-journal/print?date='.now()->toDateString())
            ->assertOk();

        $this->assertEscapedHtml((string) $response->getContent());
    }
}
