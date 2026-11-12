<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Store;
use App\Models\StorePosSetting;
use App\Models\StoreStock;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;
use Tests\TenantTestCase;

/**
 * SET-01: POS sale settings (enabled payment methods, quick cash buttons, discount presets, auto print,
 * receipt copies) saved through POST /settings, delivered by GET /pos/bootstrap and enforced on checkout.
 */
final class PosSaleSettingsApiTest extends TenantTestCase
{
    private const DEFAULTS = [
        'payment_methods' => ['cash', 'instapay', 'e_wallet', 'visa', 'bank_transfer'],
        'cash_denominations' => ['50', '100', '200', '500'],
        'discount_presets' => ['0', '5', '10'],
        'auto_print' => 'none',
        'receipt_copies' => 1,
    ];

    /** @param array<string, mixed> $extra */
    private function save(Tenant $tenant, array $extra, $user = null)
    {
        return $this->postJson('/api/v1/settings', array_merge(['company_name' => 'Shop'], $extra), $this->tenantHeaders($tenant, $user));
    }

    public function test_admin_saves_every_key_and_the_pos_bootstrap_returns_them(): void
    {
        $tenant = $this->createTenant();

        $this->save($tenant, [
            'pos_enabled_payment_methods' => ['cash', 'visa'],
            'pos_cash_denominations' => [20, 50, 100],
            'pos_discount_presets' => [0, 5],
            'pos_auto_print' => 'thermal',
            'pos_receipt_copies' => 2,
        ])->assertStatus(200);

        $this->getJson('/api/v1/pos/bootstrap?include_items=0&include_customers=0', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('data.sale_settings.payment_methods', ['cash', 'visa'])
            ->assertJsonPath('data.sale_settings.cash_denominations', ['20', '50', '100'])
            ->assertJsonPath('data.sale_settings.discount_presets', ['0', '5'])
            ->assertJsonPath('data.sale_settings.auto_print', 'thermal')
            ->assertJsonPath('data.sale_settings.receipt_copies', 2);
    }

    public function test_a_shop_that_never_saved_anything_gets_the_defaults(): void
    {
        $tenant = $this->createTenant();

        $this->getJson('/api/v1/pos/bootstrap?include_items=0&include_customers=0', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('data.sale_settings', self::DEFAULTS);
    }

    public function test_cashier_cannot_save_these_settings(): void
    {
        $tenant = $this->createTenant();
        $cashier = $this->createTenantUser($tenant, 'cashier');

        $this->save($tenant, ['pos_receipt_copies' => 2], $cashier)->assertStatus(403);
    }

    public function test_saving_requires_a_token(): void
    {
        $tenant = $this->createTenant();

        $this->flushHeaders();
        $this->postJson('/api/v1/settings', ['pos_receipt_copies' => 2], ['X-Tenant' => (string) $tenant->getTenantKey(), 'Accept' => 'application/json'])
            ->assertStatus(401);
    }

    public function test_settings_do_not_leak_between_shops(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();

        $this->save($a, ['pos_receipt_copies' => 3, 'pos_auto_print' => 'thermal'])->assertStatus(200);

        $this->getJson('/api/v1/pos/bootstrap?include_items=0&include_customers=0', $this->tenantHeaders($b))
            ->assertStatus(200)
            ->assertJsonPath('data.sale_settings.receipt_copies', 1)
            ->assertJsonPath('data.sale_settings.auto_print', 'none');
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidPayloads(): array
    {
        return [
            'cash removed' => [['pos_enabled_payment_methods' => ['instapay']], 'pos_enabled_payment_methods'],
            'unknown method' => [['pos_enabled_payment_methods' => ['cash', 'bitcoin']], 'pos_enabled_payment_methods.1'],
            'zero denomination' => [['pos_cash_denominations' => [0, 50]], 'pos_cash_denominations.0'],
            'negative denomination' => [['pos_cash_denominations' => [-20]], 'pos_cash_denominations.0'],
            'seven denominations' => [['pos_cash_denominations' => [1, 2, 3, 4, 5, 6, 7]], 'pos_cash_denominations'],
            'preset over 100' => [['pos_discount_presets' => [5, 101]], 'pos_discount_presets.1'],
            'preset over the cap' => [['pos_discount_presets' => [5, 50]], 'pos_discount_presets.1'],
            'five presets' => [['pos_discount_presets' => [0, 1, 2, 3, 4]], 'pos_discount_presets'],
            'zero copies' => [['pos_receipt_copies' => 0], 'pos_receipt_copies'],
            'four copies' => [['pos_receipt_copies' => 4], 'pos_receipt_copies'],
            'bad print mode' => [['pos_auto_print' => 'laser'], 'pos_auto_print'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidPayloads')]
    public function test_invalid_values_are_rejected_with_422(array $payload, string $errorKey): void
    {
        $tenant = $this->createTenant();

        $this->save($tenant, $payload)->assertStatus(422)->assertJsonValidationErrors([$errorKey]);
    }

    public function test_a_higher_store_cap_allows_a_higher_preset(): void
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, function (): void {
            $store = Store::query()->firstOrFail();
            StorePosSetting::query()->updateOrCreate(['store_id' => $store->id], ['max_discount_percent' => '25.000']);
        });

        $this->save($tenant, ['pos_discount_presets' => [0, 5, 25]])->assertStatus(200);
    }

    // -------------------------------------------------------------------- enforcement on checkout

    /** @return array{0: Tenant, 1: Store, 2: Customer, 3: Item} */
    private function sellingShop(): array
    {
        $tenant = $this->createTenant();
        $this->useTenantForTest($tenant);
        $store = $this->adoptMainStore(['name' => 'Main', 'code' => 'MAIN-SS', 'type' => 'retail', 'is_main' => true, 'is_active' => true]);
        $customer = Customer::create(['name' => 'Retail', 'phone' => '01100000077', 'current_balance' => '0.000', 'price_tier' => 'retail', 'is_active' => true]);
        $item = Item::create(['name' => 'SS-1', 'code' => 'SS-1', 'unit' => 'قطعة', 'cost_price' => '50.000', 'selling_price' => '100.000', 'current_stock' => '100.000', 'min_stock_level' => '1.000', 'is_active' => true]);
        StoreStock::create(['store_id' => $store->id, 'item_id' => $item->id, 'quantity' => '100.000']);
        $values = Schema::hasColumn('store_pos_settings', 'require_shift_for_sales') ? ['require_shift_for_sales' => false] : [];
        StorePosSetting::query()->updateOrCreate(['store_id' => $store->id], $values);

        return [$tenant, $store, $customer, $item];
    }

    /** @param array<string, mixed> $extra */
    private function checkout(Tenant $tenant, Store $store, Customer $customer, Item $item, array $extra)
    {
        $headers = $this->tenantHeaders($tenant, null, $store);

        return $this->postJson('/api/v1/pos/checkout', array_merge([
            'customer_id' => $customer->id,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [['item_id' => $item->id, 'quantity' => '1.000']],
        ], $extra), $headers);
    }

    public function test_a_disabled_method_is_refused_and_an_enabled_one_sells(): void
    {
        [$tenant, $store, $customer, $item] = $this->sellingShop();
        $this->save($tenant, ['pos_enabled_payment_methods' => ['cash', 'visa']])->assertStatus(200);

        $this->checkout($tenant, $store, $customer, $item, ['payment_method' => 'instapay'])
            ->assertStatus(422)->assertJsonValidationErrors(['payment_method']);
        $this->assertSame(0, Invoice::count());

        $this->checkout($tenant, $store, $customer, $item, ['payment_method' => 'visa'])->assertCreated();
        $this->assertSame(1, Invoice::count());
    }

    public function test_a_split_payment_with_a_disabled_method_is_refused(): void
    {
        [$tenant, $store, $customer, $item] = $this->sellingShop();
        $this->save($tenant, ['pos_enabled_payment_methods' => ['cash']])->assertStatus(200);

        $this->checkout($tenant, $store, $customer, $item, [
            'payments' => [['method' => 'cash', 'amount' => '50.000'], ['method' => 'instapay', 'amount' => '50.000']],
        ])->assertStatus(422)->assertJsonValidationErrors(['payments.1.method']);
        $this->assertSame(0, Invoice::count());
    }

    public function test_a_shop_without_the_setting_keeps_accepting_every_method(): void
    {
        [$tenant, $store, $customer, $item] = $this->sellingShop();
        $this->assertNull(Setting::get('pos_enabled_payment_methods'));

        $this->checkout($tenant, $store, $customer, $item, ['payment_method' => 'instapay'])->assertCreated();
    }
}
