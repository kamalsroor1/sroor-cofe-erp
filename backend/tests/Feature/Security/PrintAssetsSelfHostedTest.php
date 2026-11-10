<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Support\QrCode;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TenantTestCase;

/**
 * SEC-07: Self-hosted OFL fonts and locally-generated QR in print layouts.
 *
 * Verifies that:
 * 1. Thermal invoice printing replaces api.qrserver.com with a local SVG QR code.
 * 2. Print layouts (thermal, A4, daily journal, item movements, reports) never load
 *    fonts.googleapis.com or fonts.gstatic.com and only reference self-hosted woff2 assets.
 * 3. Disabling print_show_qr cleanly suppresses the local QR section.
 */
final class PrintAssetsSelfHostedTest extends TenantTestCase
{
    private Tenant $tenant;

    private Store $store;

    private User $admin;

    private Invoice $invoice;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);
        URL::forceRootUrl('http://'.$this->tenantDomain($this->tenant));

        $this->store = $this->adoptMainStore();
        $this->admin = User::query()->findOrFail($this->tenantAdmin($this->tenant)->getKey());
        $this->admin->stores()->syncWithoutDetaching([$this->store->id]);

        $this->item = Item::create([
            'name' => 'بن برازيلي كولومبي',
            'code' => 'BN-SEC07-01',
            'category' => 'coffee_beans',
            'cost_price' => '150.000',
            'selling_price' => '250.000',
            'current_stock' => '20.000',
            'min_stock_level' => '1.000',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'عميل الطباعة',
            'phone' => '01000007048',
            'current_balance' => '0.000',
            'is_active' => true,
        ]);
        $this->invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'user_id' => $this->admin->id,
            'invoice_number' => 'INV-SEC07-001',
            'invoice_date' => now()->toDateString(),
            'subtotal' => '250.000',
            'tax_amount' => '0.000',
            'discount_amount' => '0.000',
            'total_price' => '250.000',
            'payment_status' => 'paid',
            'invoice_type' => 'sale',
        ]);

        InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'unit_price' => '250.000',
            'cost_price' => '150.000',
            'subtotal' => '250.000',
            'total_price' => '250.000',
        ]);
    }

    public function test_qr_code_helper_generates_valid_scannable_svg_and_data_uri(): void
    {
        $payload = 'https://tenant.example.test/invoices/42';
        $svg = QrCode::svg($payload, 90, 0);

        $this->assertStringStartsWith('<svg', trim($svg));
        $this->assertStringNotContainsString('<?xml', $svg);
        $this->assertStringContainsString('viewBox="0 0 90 90"', $svg);
        $this->assertStringContainsString('aria-label="QR Code"', $svg);
        $this->assertStringContainsString('<path', $svg);

        $dataUri = QrCode::dataUri($payload, 90, 0);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);
        $decoded = base64_decode(substr($dataUri, strlen('data:image/svg+xml;base64,')), true);
        $this->assertIsString($decoded);
        $this->assertStringContainsString('<svg', $decoded);
    }

    public function test_thermal_invoice_print_uses_local_svg_qr_and_no_qrserver_or_google_fonts(): void
    {
        $this->mountProbe('invoices.print.thermal', '/__qa/sec07/invoices/{id}/print/thermal');

        $html = (string) $this->actingAs($this->admin)
            ->get('/__qa/sec07/invoices/'.$this->invoice->id.'/print/thermal')
            ->assertOk()
            ->getContent();

        // Must never reach out to third-party QR services
        $this->assertStringNotContainsString('api.qrserver.com', $html);
        $this->assertStringNotContainsString('qrserver', $html);

        // Must never leak to Google Fonts
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('fonts.gstatic.com', $html);

        // Must render local vector SVG QR
        $this->assertStringContainsString('aria-label="QR Code"', $html);
        $this->assertStringContainsString('<svg', $html);

        // Must declare self-hosted Cairo
        $this->assertStringContainsString('@font-face', $html);
        $this->assertStringContainsString('cairo-arabic-wght-normal.woff2', $html);
    }

    public function test_thermal_invoice_qr_is_hidden_when_disabled_in_settings(): void
    {
        Setting::set('print_show_qr', '0');
        $this->mountProbe('invoices.print.thermal', '/__qa/sec07/invoices/{id}/print/thermal');

        $html = (string) $this->actingAs($this->admin)
            ->get('/__qa/sec07/invoices/'.$this->invoice->id.'/print/thermal')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('aria-label="QR Code"', $html);
        $this->assertStringNotContainsString('api.qrserver.com', $html);
    }

    public function test_a4_invoice_print_uses_self_hosted_fonts_and_no_google_fonts(): void
    {
        $this->mountProbe('invoices.print.a4', '/__qa/sec07/invoices/{id}/print/a4');

        $html = (string) $this->actingAs($this->admin)
            ->get('/__qa/sec07/invoices/'.$this->invoice->id.'/print/a4')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('fonts.gstatic.com', $html);
        $this->assertStringContainsString('cairo-arabic-wght-normal.woff2', $html);
        $this->assertStringContainsString('tajawal-arabic-400.woff2', $html);
    }

    public function test_daily_journal_print_uses_self_hosted_fonts_and_no_google_fonts(): void
    {
        $this->mountProbe('daily.journal.print', '/__qa/sec07/daily-journal/print');

        $html = (string) $this->actingAs($this->admin)
            ->get('/__qa/sec07/daily-journal/print?date='.now()->toDateString())
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('fonts.gstatic.com', $html);
        $this->assertStringContainsString('cairo-arabic-wght-normal.woff2', $html);
    }

    public function test_item_movements_print_uses_self_hosted_fonts_and_no_google_fonts(): void
    {
        $this->mountProbe('items.movements.print', '/__qa/sec07/items/{id}/movements/print');

        $html = (string) $this->actingAs($this->admin)
            ->get('/__qa/sec07/items/'.$this->item->id.'/movements/print')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('fonts.gstatic.com', $html);
        $this->assertStringContainsString('cairo-arabic-wght-normal.woff2', $html);
        $this->assertStringContainsString('jetbrains-mono-latin-variable.woff2', $html);
    }

    public function test_reports_print_uses_self_hosted_fonts_and_no_google_fonts(): void
    {
        $this->mountProbe('reports.print', '/__qa/sec07/reports/print');

        $html = (string) $this->actingAs($this->admin)
            ->get('/__qa/sec07/reports/print?tab=sales')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('fonts.gstatic.com', $html);
        $this->assertStringContainsString('cairo-arabic-wght-normal.woff2', $html);
        $this->assertStringContainsString('jetbrains-mono-latin-variable.woff2', $html);
    }

    /**
     * Re-mount a real named route's action + full middleware on a probe URI ahead of the
     * SPA catch-all `/{any?}`.
     */
    private function mountProbe(string $name, string $probeUri): void
    {
        $route = Route::getRoutes()->getByName($name);
        $this->assertInstanceOf(RoutingRoute::class, $route);

        $middleware = $route->gatherMiddleware();
        $action = $route->getAction();
        unset($action['as'], $action['prefix'], $action['domain'], $action['where'], $action['middleware'], $action['excluded_middleware']);
        $action['middleware'] = $middleware;

        $probe = (new RoutingRoute(['GET', 'HEAD'], ltrim($probeUri, '/'), $action))
            ->name('qa.sec07_probe.'.str_replace('.', '_', $name))
            ->setRouter(app('router'))
            ->setContainer(app());

        $rebuilt = new RouteCollection;
        $rebuilt->add($probe);
        foreach (Route::getRoutes()->getRoutes() as $existing) {
            $rebuilt->add($existing);
        }
        Route::setRoutes($rebuilt);
    }
}
