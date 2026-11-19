<?php

declare(strict_types=1);

namespace Tests\Feature\Receipts;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\Tenant;
use App\Support\Receipts\ReceiptLinkTenantTag;
use Illuminate\Support\Facades\URL;
use Tests\TenantTestCase;

/**
 * PRD-22: POST /api/v1/invoices/{id}/receipt-link (auth, permission, store scope) and the public
 * signed receipt page GET /r/{invoice} on the shop's host (signature, expiry, tenant binding).
 */
final class InvoiceReceiptLinkTest extends TenantTestCase
{
    private Tenant $tenant;

    private Invoice $invoice;

    private Invoice $otherStoreInvoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);

        $main = $this->tenantStore($this->tenant);
        $branch = Store::create(['name' => 'فرع بعيد', 'code' => 'FAR-1', 'type' => 'retail', 'is_main' => false, 'is_active' => true]);

        $this->invoice = $this->makeInvoice($main, 'INV-RCPT-0001');
        $this->otherStoreInvoice = $this->makeInvoice($branch, 'INV-RCPT-0002');
    }

    private function makeInvoice(Store $store, string $number, ?Tenant $tenant = null, ?int $id = null): Invoice
    {
        $customer = Customer::create([
            'name' => 'عميل الإيصال',
            'phone' => '01000009'.random_int(100, 999),
            'current_balance' => '123.000',
            'is_active' => true,
        ]);
        $item = Item::create([
            'name' => 'بن يمني',
            'code' => 'RC-'.$number,
            'category' => 'coffee_beans',
            'cost_price' => '311.000',
            'selling_price' => '450.000',
            'current_stock' => '20.000',
            'min_stock_level' => '1.000',
            'is_active' => true,
        ]);
        $invoice = (new Invoice)->forceFill(array_filter(['id' => $id]))->fill([
            'invoice_number' => $number,
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'user_id' => $this->tenantAdmin($tenant ?? $this->tenant)->id,
            'invoice_date' => now()->toDateString(),
            'subtotal' => '900.000',
            'discount_amount' => '0.000',
            'net_total' => '900.000',
            'paid_amount' => '900.000',
            'remaining_amount' => '0.000',
            'total_cost' => '622.000',
            'status' => 'confirmed',
            'payment_method' => 'cash',
        ]);
        $invoice->save();
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_id' => $item->id,
            'quantity' => '2.000',
            'unit_price' => '450.000',
            'cost_price' => '311.000',
            'discount_amount' => '0.000',
            'total_price' => '900.000',
        ]);

        return $invoice;
    }

    private function linkUrl(int $invoiceId): string
    {
        return (string) $this->postJson("/api/v1/invoices/{$invoiceId}/receipt-link", [], $this->tenantHeaders($this->tenant))
            ->assertOk()
            ->json('data.url');
    }

    /** The link's path + query, requested on the given tenant's host. */
    private function onHost(Tenant $tenant, string $url): string
    {
        $parts = parse_url($url);

        return $this->tenantUrl($tenant, ($parts['path'] ?? '/').'?'.($parts['query'] ?? ''));
    }

    // ------------------------------------------------------------------ link endpoint

    public function test_guest_is_401(): void
    {
        $this->postJson("/api/v1/invoices/{$this->invoice->id}/receipt-link", [], $this->tenantGuestHeaders($this->tenant))
            ->assertStatus(401);
    }

    public function test_user_without_invoice_permission_is_403(): void
    {
        $user = $this->createTenantUser($this->tenant, null, []);

        $this->postJson("/api/v1/invoices/{$this->invoice->id}/receipt-link", [], $this->tenantHeaders($this->tenant, $user))
            ->assertStatus(403);
    }

    public function test_admin_gets_a_signed_link_on_the_shop_host_valid_for_the_configured_days(): void
    {
        config(['invoices.receipt_links.ttl_days' => 30]);

        $response = $this->postJson("/api/v1/invoices/{$this->invoice->id}/receipt-link", [], $this->tenantHeaders($this->tenant))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['url', 'expires_at']]);

        $url = (string) $response->json('data.url');
        $parts = parse_url($url);
        parse_str((string) ($parts['query'] ?? ''), $query);

        $this->assertSame($this->tenantDomain($this->tenant), $parts['host'] ?? null);
        $this->assertSame("/r/{$this->invoice->id}", $parts['path'] ?? null);
        $this->assertSame(['expires', 't', 'signature'], array_keys($query));
        $this->assertStringNotContainsString((string) $this->tenant->getTenantKey(), (string) $query['t']);

        $expires = (int) $query['expires'];
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $expires, 5);
    }

    public function test_a_cashier_with_pos_access_may_create_a_link(): void
    {
        $cashier = $this->createTenantUser($this->tenant, null, ['pos.access']);

        $this->postJson("/api/v1/invoices/{$this->invoice->id}/receipt-link", [], $this->tenantHeaders($this->tenant, $cashier))
            ->assertOk();
    }

    public function test_an_invoice_of_a_branch_the_user_cannot_reach_is_404(): void
    {
        $viewer = $this->createTenantUser($this->tenant, null, ['invoices.view']);

        $this->postJson("/api/v1/invoices/{$this->otherStoreInvoice->id}/receipt-link", [], $this->tenantHeaders($this->tenant, $viewer))
            ->assertStatus(404);
        $this->postJson('/api/v1/invoices/999999/receipt-link', [], $this->tenantHeaders($this->tenant))
            ->assertStatus(404);
    }

    public function test_a_cancelled_invoice_gets_no_link(): void
    {
        $this->invoice->forceFill(['status' => 'cancelled'])->save();

        $this->postJson("/api/v1/invoices/{$this->invoice->id}/receipt-link", [], $this->tenantHeaders($this->tenant))
            ->assertStatus(422);
    }

    // ------------------------------------------------------------------ public page

    public function test_the_signed_link_shows_the_receipt_without_internal_data(): void
    {
        $url = $this->linkUrl($this->invoice->id);

        $page = $this->get($this->onHost($this->tenant, $url))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('INV-RCPT-0001')
            ->assertSee('بن يمني')
            ->assertSee('900.00');

        $html = (string) $page->getContent();
        $this->assertStringNotContainsString('311', $html, 'cost price must never be shown');
        $this->assertStringNotContainsString('622', $html, 'invoice cost must never be shown');
        $this->assertStringNotContainsString('01000009', $html, 'customer phone must never be shown');
        $this->assertStringNotContainsString('123.00', $html, 'customer balance must never be shown');
    }

    public function test_a_tampered_link_is_403(): void
    {
        $url = $this->linkUrl($this->invoice->id);

        $otherInvoice = str_replace("/r/{$this->invoice->id}?", "/r/{$this->otherStoreInvoice->id}?", $url);
        $this->get($this->onHost($this->tenant, $otherInvoice))->assertStatus(403);

        $badSignature = preg_replace('/signature=[0-9a-f]+/', 'signature='.str_repeat('0', 64), $url);
        $this->get($this->onHost($this->tenant, (string) $badSignature))->assertStatus(403);

        $unsigned = $this->tenantUrl($this->tenant, "/r/{$this->invoice->id}");
        $this->get($unsigned)->assertStatus(403);
    }

    public function test_an_expired_link_is_403(): void
    {
        config(['invoices.receipt_links.ttl_days' => 30]);
        $url = $this->linkUrl($this->invoice->id);

        $this->travel(31)->days();

        $this->get($this->onHost($this->tenant, $url))->assertStatus(403);
    }

    public function test_a_link_of_one_shop_never_opens_another_shops_invoice(): void
    {
        $url = $this->linkUrl($this->invoice->id);

        $other = $this->createTenant();
        $sameId = (int) $this->invoice->id;
        $this->inTenant($other, function () use ($other, $sameId): void {
            // The other shop has an invoice with the very same id.
            $this->makeInvoice($this->tenantStore($other), 'INV-OTHER-SHOP', $other, $sameId);
        });

        $this->get($this->onHost($other, $url))->assertStatus(404)->assertDontSee('INV-OTHER-SHOP');
    }

    public function test_a_cancelled_invoice_link_stops_working(): void
    {
        $url = $this->linkUrl($this->invoice->id);
        $this->invoice->forceFill(['status' => 'cancelled'])->save();

        $this->get($this->onHost($this->tenant, $url))->assertStatus(404);
    }

    public function test_a_valid_signature_for_a_missing_invoice_is_404(): void
    {
        $path = URL::temporarySignedRoute('receipts.public', now()->addDay(), [
            'invoice' => 999999,
            't' => ReceiptLinkTenantTag::for((string) $this->tenant->getTenantKey()),
        ], absolute: false);

        $this->get($this->tenantUrl($this->tenant, $path))->assertStatus(404);
    }
}
