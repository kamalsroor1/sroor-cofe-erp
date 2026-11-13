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
use PHPUnit\Framework\Attributes\DataProvider;
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
        // ROLE-02: a new shop's stores use the default 5% cap, so the default 10% preset is hidden.
        'discount_presets' => ['0', '5'],
        'auto_print' => 'none',
        'receipt_copies' => 1,
    ];

    /** What the Settings form submits for a shop that never touched these keys (helpers/posSaleSettingsForm.js SALE_DEFAULTS). */
    private const FORM_DEFAULT_PAYLOAD = [
        'pos_enabled_payment_methods' => ['cash', 'instapay', 'e_wallet', 'visa', 'bank_transfer'],
        'pos_cash_denominations' => ['50', '100', '200', '500'],
        'pos_discount_presets' => ['0', '5', '10'],
        'pos_auto_print' => 'none',
        'pos_receipt_copies' => 1,
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

    #[DataProvider('invalidPayloads')]
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

    // -------------------------------------------------------------------- discount presets vs the cap

    private function setAllStoreCaps(Tenant $tenant, ?string $cap): void
    {
        $this->inTenant($tenant, function () use ($cap): void {
            foreach (Store::query()->pluck('id') as $storeId) {
                StorePosSetting::query()->updateOrCreate(['store_id' => $storeId], ['max_discount_percent' => $cap]);
            }
        });
    }

    public function test_a_new_shop_saving_another_tab_with_the_full_default_form_payload_gets_200(): void
    {
        $tenant = $this->createTenant();

        $this->save($tenant, ['company_name' => 'Renamed shop', ...self::FORM_DEFAULT_PAYLOAD])->assertStatus(200);

        $this->inTenant($tenant, function (): void {
            $this->assertSame('Renamed shop', Setting::get('company_name'));
            // Nothing changed in the sale settings, so nothing is stored: no preset above the 5% cap.
            $this->assertNull(Setting::get('pos_discount_presets'));
            $this->assertNull(Setting::get('pos_enabled_payment_methods'));
        });
        $this->getJson('/api/v1/settings', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('settings.pos_discount_presets', '0,5');
    }

    public function test_re_saving_what_get_settings_returned_gets_200(): void
    {
        $tenant = $this->createTenant();
        $s = $this->getJson('/api/v1/settings', $this->tenantHeaders($tenant))->assertStatus(200)->json('settings');

        $this->save($tenant, [
            'pos_enabled_payment_methods' => explode(',', $s['pos_enabled_payment_methods']),
            'pos_cash_denominations' => explode(',', $s['pos_cash_denominations']),
            'pos_discount_presets' => explode(',', $s['pos_discount_presets']),
            'pos_auto_print' => $s['pos_auto_print'],
            'pos_receipt_copies' => $s['pos_receipt_copies'],
        ])->assertStatus(200);
    }

    public function test_a_shop_without_stores_can_save_the_default_form(): void
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, function (): void {
            StorePosSetting::query()->delete();
            Store::query()->delete();
        });

        $this->save($tenant, self::FORM_DEFAULT_PAYLOAD)->assertStatus(200);
        $this->getJson('/api/v1/settings', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('settings.pos_discount_presets', '0');
    }

    public function test_a_newly_entered_preset_above_the_cap_is_still_422(): void
    {
        $tenant = $this->createTenant();

        $this->save($tenant, [...self::FORM_DEFAULT_PAYLOAD, 'pos_discount_presets' => ['0', '5', '7']])
            ->assertStatus(422)->assertJsonValidationErrors(['pos_discount_presets.2']);
        $this->save($tenant, [...self::FORM_DEFAULT_PAYLOAD, 'pos_discount_presets' => ['0', '3', '5']])->assertStatus(200);
        $this->inTenant($tenant, fn () => $this->assertSame('0,3,5', Setting::get('pos_discount_presets')));
    }

    public function test_a_shop_whose_caps_are_10_keeps_the_original_presets(): void
    {
        $tenant = $this->createTenant();
        $this->setAllStoreCaps($tenant, '10.000');

        $this->getJson('/api/v1/pos/bootstrap?include_items=0&include_customers=0', $this->tenantHeaders($tenant))
            ->assertStatus(200)->assertJsonPath('data.sale_settings.discount_presets', ['0', '5', '10']);
        $this->getJson('/api/v1/settings', $this->tenantHeaders($tenant))
            ->assertStatus(200)->assertJsonPath('settings.pos_discount_presets', '0,5,10');
        $this->save($tenant, self::FORM_DEFAULT_PAYLOAD)->assertStatus(200);
    }

    public function test_presets_above_a_lowered_cap_are_hidden_but_kept_stored(): void
    {
        $tenant = $this->createTenant();
        $this->setAllStoreCaps($tenant, '20.000');
        $this->save($tenant, ['pos_discount_presets' => ['0', '10', '20']])->assertStatus(200);

        $this->setAllStoreCaps($tenant, '10.000');
        $this->getJson('/api/v1/pos/bootstrap?include_items=0&include_customers=0', $this->tenantHeaders($tenant))
            ->assertStatus(200)->assertJsonPath('data.sale_settings.discount_presets', ['0', '10']);
        $this->getJson('/api/v1/settings', $this->tenantHeaders($tenant))
            ->assertStatus(200)->assertJsonPath('settings.pos_discount_presets', '0,10');

        // Re-sending the visible list (another tab saved) passes and does not narrow the stored list.
        $this->save($tenant, ['pos_discount_presets' => ['0', '10']])->assertStatus(200);
        $this->inTenant($tenant, fn () => $this->assertSame('0,10,20', Setting::get('pos_discount_presets')));

        $this->setAllStoreCaps($tenant, '2.000');
        $this->getJson('/api/v1/pos/bootstrap?include_items=0&include_customers=0', $this->tenantHeaders($tenant))
            ->assertStatus(200)->assertJsonPath('data.sale_settings.discount_presets', ['0']);
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

    // -------------------------------------------------------------------- other customer-payment paths

    /** @param array<string, mixed> $extra */
    private function salesInvoice(Tenant $tenant, Store $store, Customer $customer, Item $item, array $extra)
    {
        return $this->postJson('/api/v1/invoices', array_merge([
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'discount_type' => 'fixed',
            'discount_value' => '0.000',
            'items' => [['item_id' => $item->id, 'quantity' => '1.000', 'unit_price' => '100.000']],
        ], $extra), $this->tenantHeaders($tenant, null, $store));
    }

    public function test_sales_invoice_single_method_is_enforced(): void
    {
        [$tenant, $store, $customer, $item] = $this->sellingShop();
        $this->save($tenant, ['pos_enabled_payment_methods' => ['cash', 'visa']])->assertStatus(200);

        $this->salesInvoice($tenant, $store, $customer, $item, ['payment_method' => 'instapay'])
            ->assertStatus(422)->assertJsonValidationErrors(['payment_method']);
        $this->assertSame(0, Invoice::count());

        $this->salesInvoice($tenant, $store, $customer, $item, ['payment_method' => 'visa'])->assertCreated();
    }

    public function test_sales_invoice_split_payments_are_enforced(): void
    {
        [$tenant, $store, $customer, $item] = $this->sellingShop();
        $this->save($tenant, ['pos_enabled_payment_methods' => ['cash', 'visa']])->assertStatus(200);

        $this->salesInvoice($tenant, $store, $customer, $item, [
            'payments' => [['method' => 'cash', 'amount' => '50.000'], ['method' => 'instapay', 'amount' => '50.000']],
        ])->assertStatus(422)->assertJsonValidationErrors(['payments.1.method']);
        $this->assertSame(0, Invoice::count());

        $this->salesInvoice($tenant, $store, $customer, $item, [
            'payments' => [['method' => 'cash', 'amount' => '50.000'], ['method' => 'visa', 'amount' => '50.000']],
        ])->assertCreated();
    }

    public function test_collect_payment_is_enforced(): void
    {
        [$tenant, $store, $customer] = $this->sellingShop();
        $customer->forceFill(['current_balance' => '500.000'])->save();
        $this->save($tenant, ['pos_enabled_payment_methods' => ['cash', 'visa']])->assertStatus(200);
        $collect = fn (string $method) => $this->postJson('/api/v1/customers/'.$customer->id.'/collect-payment', [
            'amount' => '100.000', 'payment_method' => $method, 'payment_date' => now()->toDateString(),
        ], $this->tenantHeaders($tenant, null, $store));

        $collect('instapay')->assertStatus(422)->assertJsonValidationErrors(['payment_method']);
        $collect('wallet')->assertStatus(422)->assertJsonValidationErrors(['payment_method']); // legacy alias of e_wallet
        $collect('visa')->assertSuccessful();
    }

    public function test_customer_receipt_is_enforced(): void
    {
        [$tenant, $store, $customer] = $this->sellingShop();
        $this->save($tenant, ['pos_enabled_payment_methods' => ['cash', 'visa']])->assertStatus(200);
        $receipt = fn (string $method) => $this->postJson('/api/v1/payments/customer-receipt', [
            'customer_id' => $customer->id, 'amount' => '30.000', 'payment_method' => $method,
        ], $this->tenantHeaders($tenant, null, $store));

        $receipt('instapay')->assertStatus(422)->assertJsonValidationErrors(['payment_method']);
        $receipt('visa')->assertSuccessful();
        // check / other are not toggleable: always accepted, even with a narrowed list.
        $receipt('check')->assertSuccessful();
        $receipt('other')->assertSuccessful();
    }

    public function test_an_unrelated_save_keeps_check_and_other_and_does_not_narrow_the_methods(): void
    {
        [$tenant, $store, $customer] = $this->sellingShop();

        $this->save($tenant, ['company_name' => 'Other tab', ...self::FORM_DEFAULT_PAYLOAD])->assertStatus(200);
        $this->inTenant($tenant, fn () => $this->assertNull(Setting::get('pos_enabled_payment_methods')));

        foreach (['check', 'other', 'instapay', 'bank_transfer'] as $method) {
            $this->postJson('/api/v1/payments/customer-receipt', [
                'customer_id' => $customer->id, 'amount' => '10.000', 'payment_method' => $method,
            ], $this->tenantHeaders($tenant, null, $store))->assertSuccessful();
        }
    }

    public function test_methods_outside_the_toggles_cannot_be_saved(): void
    {
        $tenant = $this->createTenant();

        $this->save($tenant, ['pos_enabled_payment_methods' => ['cash', 'check']])
            ->assertStatus(422)->assertJsonValidationErrors(['pos_enabled_payment_methods.1']);
    }

    public function test_an_old_invoice_paid_with_a_now_disabled_method_still_shows(): void
    {
        [$tenant, $store, $customer, $item] = $this->sellingShop();
        $id = $this->salesInvoice($tenant, $store, $customer, $item, ['payment_method' => 'instapay'])
            ->assertCreated()->json('data.id');

        $this->save($tenant, ['pos_enabled_payment_methods' => ['cash']])->assertStatus(200);

        $this->getJson('/api/v1/invoices/'.$id, $this->tenantHeaders($tenant, null, $store))
            ->assertStatus(200)
            ->assertJsonPath('data.payment_method', 'instapay');
    }
}
