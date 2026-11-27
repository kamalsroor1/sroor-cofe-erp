<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * GEN-180: Listing endpoints must validate `sort`, `sort_by`, `direction`, and `sort_dir`
 * against strict allowlists and reject injection / unknown values with 422.
 */
final class ListingSortValidationTest extends TenantTestCase
{
    private Tenant $tenant;

    private Store $store;

    private User $admin;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->store = $this->tenantStore($this->tenant);
        $this->admin = $this->tenantAdmin($this->tenant);
        $this->headers = $this->tenantHeaders($this->tenant, $this->admin);
    }

    /** @return array<string, array{0: string}> */
    public static function endpoints(): array
    {
        return [
            'customers' => ['/api/v1/customers'],
            'items' => ['/api/v1/items'],
            'invoices' => ['/api/v1/invoices'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_invalid_sort_and_direction_return_422(string $endpoint): void
    {
        $this->getJson($endpoint.'?sort='.urlencode('id;DROP TABLE'), $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sort']);

        $this->getJson($endpoint.'?sort_by='.urlencode('password'), $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sort_by']);

        $this->getJson($endpoint.'?direction='.urlencode('asc;DROP TABLE'), $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['direction']);

        $this->getJson($endpoint.'?sort_dir=sideways', $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sort_dir']);

        $this->getJson($endpoint.'?sort[]=id', $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sort']);
    }

    #[DataProvider('endpoints')]
    public function test_omitted_or_empty_sort_and_direction_default_normally(string $endpoint): void
    {
        $this->getJson($endpoint, $this->headers)->assertOk();
        $this->getJson($endpoint.'?sort=&sort_by=&direction=&sort_dir=', $this->headers)->assertOk();
    }

    public function test_invalid_sort_validation_error_is_localized(): void
    {
        $arMessage = (string) $this->getJson('/api/v1/customers?sort=id;DROP', array_merge($this->headers, [
            'X-Locale' => 'ar',
        ]))->assertStatus(422)->json('errors.sort.0');

        $enMessage = (string) $this->getJson('/api/v1/customers?sort=id;DROP', array_merge($this->headers, [
            'X-Locale' => 'en',
        ]))->assertStatus(422)->json('errors.sort.0');

        $this->assertStringContainsString('حقل الترتيب', $arMessage);
        $this->assertStringContainsString('sort field', $enMessage);
        $this->assertNotSame($arMessage, $enMessage);
    }

    public function test_customers_allowlisted_sort_orders_results(): void
    {
        $this->inTenant($this->tenant, function (): void {
            Customer::create(['name' => 'Zaid', 'phone' => '01000000101', 'current_balance' => '100.000', 'is_active' => true]);
            Customer::create(['name' => 'Amr', 'phone' => '01000000102', 'current_balance' => '200.000', 'is_active' => true]);
        });

        $asc = $this->getJson('/api/v1/customers?sort=name&direction=asc', $this->headers)
            ->assertOk()
            ->json('data');

        $this->assertSame(['Amr', 'Zaid'], array_column($asc, 'name'));

        $desc = $this->getJson('/api/v1/customers?sort_by=name&sort_dir=desc', $this->headers)
            ->assertOk()
            ->json('data');

        $this->assertSame(['Zaid', 'Amr'], array_column($desc, 'name'));
    }

    public function test_items_allowlisted_sort_orders_results(): void
    {
        $this->inTenant($this->tenant, function (): void {
            Item::create([
                'name' => 'Zebra Roast',
                'code' => 'ITM-9002',
                'unit' => 'كجم',
                'cost_price' => '100.000',
                'selling_price' => '200.000',
                'current_stock' => '5.000',
                'is_active' => true,
            ]);
            Item::create([
                'name' => 'Alpha Roast',
                'code' => 'ITM-9001',
                'unit' => 'كجم',
                'cost_price' => '100.000',
                'selling_price' => '150.000',
                'current_stock' => '15.000',
                'is_active' => true,
            ]);
        });

        $asc = $this->getJson('/api/v1/items?sort=name&direction=asc', $this->headers)
            ->assertOk()
            ->json('data');

        $this->assertSame(['Alpha Roast', 'Zebra Roast'], array_column($asc, 'name'));

        $desc = $this->getJson('/api/v1/items?sort_by=selling_price&sort_dir=desc', $this->headers)
            ->assertOk()
            ->json('data');

        $this->assertSame(['Zebra Roast', 'Alpha Roast'], array_column($desc, 'name'));
    }

    public function test_invoices_allowlisted_sort_orders_results(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $customer = Customer::create(['name' => 'Client', 'phone' => '01000000103', 'is_active' => true]);

            Invoice::create([
                'store_id' => $this->store->id,
                'invoice_number' => 'INV-0002',
                'customer_id' => $customer->id,
                'user_id' => $this->admin->id,
                'invoice_date' => '2026-11-26',
                'payment_type' => 'cash',
                'payment_method' => 'cash',
                'subtotal' => '300.000',
                'discount_amount' => '0.000',
                'net_total' => '300.000',
                'paid_amount' => '300.000',
                'remaining_amount' => '0.000',
                'status' => 'confirmed',
            ]);
            Invoice::create([
                'store_id' => $this->store->id,
                'invoice_number' => 'INV-0001',
                'customer_id' => $customer->id,
                'user_id' => $this->admin->id,
                'invoice_date' => '2026-11-25',
                'payment_type' => 'cash',
                'payment_method' => 'cash',
                'subtotal' => '100.000',
                'discount_amount' => '0.000',
                'net_total' => '100.000',
                'paid_amount' => '100.000',
                'remaining_amount' => '0.000',
                'status' => 'confirmed',
            ]);
        });

        $asc = $this->getJson('/api/v1/invoices?sort=net_total&direction=asc', $this->headers)
            ->assertOk()
            ->json('data');

        $this->assertSame(['INV-0001', 'INV-0002'], array_column($asc, 'invoice_number'));

        $desc = $this->getJson('/api/v1/invoices?sort_by=invoice_number&sort_dir=desc', $this->headers)
            ->assertOk()
            ->json('data');

        $this->assertSame(['INV-0002', 'INV-0001'], array_column($desc, 'invoice_number'));
    }
}
