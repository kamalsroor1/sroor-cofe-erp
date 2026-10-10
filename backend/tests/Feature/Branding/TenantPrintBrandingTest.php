<?php

declare(strict_types=1);

namespace Tests\Feature\Branding;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * BRND-5: the Blade prints (thermal receipt, A4 invoice, daily journal) carry the CURRENT
 * shop's brand from TenantBranding: name, subtitle, receipt header/footer, legal lines and
 * the logo as a data: URI read from the tenant disk. Never public/logo*.png (shared by every
 * tenant), and every tenant-provided text is escaped.
 *
 * Like InvoicePrintRoutesTest, the real named routes are re-mounted (action + full
 * middleware, domain tenancy included) on probe URIs ahead of the SPA catch-all.
 */
#[Group('harness')]
final class TenantPrintBrandingTest extends TenantTestCase
{
    private Tenant $tenant;

    private Store $store;

    private User $admin;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);
        URL::forceRootUrl('http://'.$this->tenantDomain($this->tenant));

        $this->store = $this->adoptMainStore();
        $this->admin = User::query()->findOrFail($this->tenantAdmin($this->tenant)->getKey());
        $this->invoice = $this->makeInvoice($this->store, $this->admin);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invoicePrints(): array
    {
        return [
            'thermal' => ['invoices.print.thermal', '/__qa/brand/invoices/{id}/print/thermal'],
            'a4' => ['invoices.print.a4', '/__qa/brand/invoices/{id}/print/a4'],
        ];
    }

    #[DataProvider('invoicePrints')]
    public function test_invoice_print_uses_the_tenant_name_header_and_footer(string $name, string $probe): void
    {
        Setting::set('company_name', 'محمصة النيل');
        Setting::set('company_subtitle', 'بن طازج');
        Setting::set('receipt_header_lines', "فرع المعادي\nشارع 9");
        Setting::set('receipt_footer_text', 'شكرا لزيارتكم');

        $html = $this->printInvoice($name, $probe);

        $this->assertStringContainsString('محمصة النيل', $html);
        $this->assertStringContainsString('بن طازج', $html);
        $this->assertStringContainsString('فرع المعادي', $html);
        $this->assertStringContainsString('شارع 9', $html);
        $this->assertStringContainsString('شكرا لزيارتكم', $html);
        $this->assertStringNotContainsString('logo.png', $html);
        $this->assertStringNotContainsString('logo-light.png', $html);
    }

    #[DataProvider('invoicePrints')]
    public function test_invoice_print_embeds_the_tenant_logo_as_a_data_uri(string $name, string $probe): void
    {
        $binary = $this->uploadLogo($this->tenant);

        $html = $this->printInvoice($name, $probe);

        $this->assertStringContainsString('src="data:image/png;base64,'.base64_encode($binary).'"', $html);
        $this->assertStringNotContainsString('logo.png', $html);
    }

    #[DataProvider('invoicePrints')]
    public function test_no_logo_and_logo_hidden_print_no_image(string $name, string $probe): void
    {
        $this->assertStringNotContainsString('data:image/', $this->printInvoice($name, $probe));

        $this->uploadLogo($this->tenant);
        Setting::set('show_print_logo', '0');

        $this->assertStringNotContainsString('data:image/', $this->printInvoice($name, $probe));
    }

    #[DataProvider('invoicePrints')]
    public function test_tenant_text_is_escaped(string $name, string $probe): void
    {
        Setting::set('company_name', '<script>alert("x")</script>');
        Setting::set('company_subtitle', '<img src=x onerror=alert(1)>');
        Setting::set('invoice_footer_note', '<b>footer</b>');

        $html = $this->printInvoice($name, $probe);

        $this->assertStringNotContainsString('<script>alert("x")</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $html);
    }

    public function test_a4_prints_the_legal_lines(): void
    {
        Setting::set('commercial_register', 'CR-123456');
        Setting::set('tax_registration_no', 'TAX-987-654');

        $html = $this->printInvoice('invoices.print.a4', '/__qa/brand/invoices/{id}/print/a4');

        $this->assertStringContainsString('CR-123456', $html);
        $this->assertStringContainsString('TAX-987-654', $html);
    }

    public function test_thermal_footer_falls_back_to_invoice_footer_note(): void
    {
        Setting::set('invoice_footer_note', 'ملاحظة قديمة');

        $html = $this->printInvoice('invoices.print.thermal', '/__qa/brand/invoices/{id}/print/thermal');

        $this->assertStringContainsString('ملاحظة قديمة', $html);
    }

    public function test_daily_journal_print_uses_the_tenant_logo_and_name(): void
    {
        Setting::set('company_name', 'محمصة اليومية');
        $binary = $this->uploadLogo($this->tenant);
        $this->mountProbe('daily.journal.print', '/__qa/brand/daily-journal/print');

        $html = (string) $this->actingAs($this->admin)
            ->get('/__qa/brand/daily-journal/print?date='.now()->toDateString())
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('محمصة اليومية', $html);
        $this->assertStringContainsString('data:image/png;base64,'.base64_encode($binary), $html);
        $this->assertStringNotContainsString('logo.png', $html);
    }

    public function test_another_tenants_logo_never_appears_in_this_tenants_print(): void
    {
        $other = $this->createTenant();
        $foreignBinary = $this->uploadLogo($other);
        $this->useTenantForTest($this->tenant);
        URL::forceRootUrl('http://'.$this->tenantDomain($this->tenant));

        $html = $this->printInvoice('invoices.print.thermal', '/__qa/brand/invoices/{id}/print/thermal');

        $this->assertStringNotContainsString(base64_encode($foreignBinary), $html);
        $this->assertStringNotContainsString('data:image/', $html);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function printInvoice(string $name, string $probe): string
    {
        $this->mountProbe($name, $probe);

        return (string) $this->actingAs($this->admin)
            ->get(str_replace('{id}', (string) $this->invoice->id, $probe))
            ->assertOk()
            ->getContent();
    }

    /** Upload a PNG logo through the real endpoint; returns the stored (re-encoded) bytes. */
    private function uploadLogo(Tenant $tenant): string
    {
        // Absolute URL on the tenant's own host: forceRootUrl() points at $this->tenant.
        $this->post($this->tenantUrl($tenant, '/api/v1/settings/branding/logo/light'), [
            'file' => UploadedFile::fake()->image('logo.png', 120, 80),
        ], $this->tenantHeaders($tenant))->assertOk();

        return $this->inTenant($tenant, fn (): string => (string) file_get_contents(Media::query()->where('collection_name', 'logo_light')->sole()->getPath()));
    }

    private function makeInvoice(Store $store, User $admin): Invoice
    {
        $customer = Customer::create([
            'name' => 'عميل الطباعة',
            'phone' => '01000007048',
            'current_balance' => '0.000',
            'is_active' => true,
        ]);

        $item = Item::create([
            'name' => 'بن برازيلي',
            'code' => 'BN-BRAND-01',
            'category' => 'coffee_beans',
            'cost_price' => '300.000',
            'selling_price' => '500.000',
            'current_stock' => '50.000',
            'min_stock_level' => '1.000',
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-BRAND-QA-1',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'invoice_date' => now()->toDateString(),
            'subtotal' => '500.000',
            'discount_amount' => '0.000',
            'net_total' => '500.000',
            'paid_amount' => '500.000',
            'remaining_amount' => '0.000',
            'total_cost' => '300.000',
            'status' => 'confirmed',
            'payment_method' => 'cash',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_id' => $item->id,
            'quantity' => '1.000',
            'unit_price' => '500.000',
            'cost_price' => '300.000',
            'discount_amount' => '0.000',
            'total_price' => '500.000',
        ]);

        // The admin role (harness) carries invoices.view / daily_journal.view.
        $this->assertTrue($admin->hasRole(Role::findByName('admin')));

        return $invoice;
    }

    /**
     * Re-mount a real named route's action + full middleware on a probe URI ahead of the
     * SPA catch-all `/{any?}` (same technique as InvoicePrintRoutesTest).
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
            ->name('qa.brand_probe.'.str_replace('.', '_', $name))
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
