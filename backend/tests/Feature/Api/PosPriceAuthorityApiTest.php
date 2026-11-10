<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RateLimitKey;
use App\Support\TenantClock;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * BE-08 / POS-18: the server owns the price. Tier list price, below-minimum floor with permission or
 * manager PIN approval, server-side sale date, and the cashier never seeing the floor value.
 *
 * Fixture item: retail 100.000, wholesale 80.000, floor (min_selling_price) 70.000, cost 50.000.
 */
final class PosPriceAuthorityApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private Store $store;

    private Store $otherStore;

    private User $cashier;

    private User $manager;

    private User $admin;

    private Customer $retailCustomer;

    private Customer $wholesaleCustomer;

    private Item $item;

    private Item $plainItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);

        $this->store = $this->adoptMainStore(['name' => 'Main', 'code' => 'MAIN-PR', 'type' => 'retail', 'is_main' => true, 'is_active' => true]);
        $this->otherStore = Store::create(['name' => 'Other', 'code' => 'OTH-PR', 'type' => 'retail', 'is_main' => false, 'is_active' => true]);

        $this->cashier = $this->makeUser('01000000011', 'cashier');
        $this->manager = $this->makeUser('01000000012', 'cashier');
        $this->manager->givePermissionTo(Permission::findOrCreate('pos.price.below_min', 'web'));
        $this->manager->forceFill(['pos_pin' => Hash::make('4321')])->save();
        $this->admin = $this->makeUser('01000000013', 'admin');

        $this->retailCustomer = Customer::create(['name' => 'Retail', 'phone' => '01100000001', 'current_balance' => '0.000', 'price_tier' => 'retail', 'is_active' => true]);
        $this->wholesaleCustomer = Customer::create(['name' => 'Wholesale', 'phone' => '01100000002', 'current_balance' => '0.000', 'price_tier' => 'wholesale', 'is_active' => true]);

        $this->item = $this->makeItem('PR-1', ['wholesale_price' => '80.000', 'min_selling_price' => '70.000']);
        // No wholesale price, no floor.
        $this->plainItem = $this->makeItem('PR-2', ['wholesale_price' => null, 'min_selling_price' => '0.000']);
    }

    private function makeUser(string $phone, string $role): User
    {
        $user = User::factory()->create([
            'name' => $role.$phone,
            'phone' => $phone,
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $user->assignRole(Role::findByName($role));

        return $user;
    }

    /** @param array<string, mixed> $attrs */
    private function makeItem(string $code, array $attrs): Item
    {
        $item = Item::create(array_merge([
            'name' => $code,
            'code' => $code,
            'unit' => 'كجم',
            'cost_price' => '50.000',
            'selling_price' => '100.000',
            'current_stock' => '100.000',
            'min_stock_level' => '1.000',
            'is_active' => true,
        ], $attrs));
        StoreStock::create(['store_id' => $this->store->id, 'item_id' => $item->id, 'quantity' => '100.000']);
        StoreStock::create(['store_id' => $this->otherStore->id, 'item_id' => $item->id, 'quantity' => '100.000']);

        return $item;
    }

    /** @param array<string, mixed> $line */
    private function checkout(User $as, array $line, array $extra = [], ?Customer $customer = null, string $url = '/api/v1/pos/checkout'): TestResponse
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $line = array_merge(['item_id' => $this->item->id, 'quantity' => '1.000'], $line);

        return $this->withHeaders([
            'X-Tenant' => (string) $this->tenant->getTenantKey(),
            'Authorization' => 'Bearer '.$as->createToken('t')->plainTextToken,
            'X-Store-Id' => (string) $this->store->id,
            'Accept' => 'application/json',
        ])->postJson($url, array_merge([
            'customer_id' => ($customer ?? $this->retailCustomer)->id,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [$line],
        ], $extra));
    }

    private function lastLine(): InvoiceItem
    {
        return InvoiceItem::query()->latest('id')->firstOrFail();
    }

    // ----------------------------------------------------------------- tiers

    public function test_wholesale_customer_without_a_submitted_price_gets_the_wholesale_price(): void
    {
        $this->checkout($this->cashier, [], [], $this->wholesaleCustomer)->assertCreated();

        $line = $this->lastLine();
        $this->assertSame('80.000', (string) $line->unit_price);
        $this->assertSame('80.000', (string) $line->list_unit_price);
        $this->assertSame('wholesale', $line->price_source);
        $this->assertNull($line->override_approved_by);
    }

    public function test_retail_customer_without_a_submitted_price_gets_the_retail_price(): void
    {
        $this->checkout($this->cashier, [], [], $this->retailCustomer)->assertCreated();

        $this->assertSame('100.000', (string) $this->lastLine()->unit_price);
        $this->assertSame('retail', $this->lastLine()->price_source);
    }

    public function test_wholesale_customer_falls_back_to_retail_when_the_item_has_no_wholesale_price(): void
    {
        $this->checkout($this->cashier, ['item_id' => $this->plainItem->id], [], $this->wholesaleCustomer)->assertCreated();

        $this->assertSame('100.000', (string) $this->lastLine()->unit_price);
        $this->assertSame('retail', $this->lastLine()->price_source);
    }

    public function test_retail_and_wholesale_lines_can_be_mixed_in_one_invoice(): void
    {
        $this->checkout($this->cashier, [], [
            'items' => [
                ['item_id' => $this->item->id, 'quantity' => '1.000', 'unit_price' => '80.000'],
                ['item_id' => $this->item->id, 'quantity' => '1.000', 'unit_price' => '100.000'],
            ],
        ], $this->wholesaleCustomer)->assertCreated();

        $prices = InvoiceItem::query()->orderBy('id')->pluck('unit_price')->map(fn ($p) => (string) $p)->all();
        $this->assertSame(['80.000', '100.000'], $prices);
        $this->assertSame('180.000', (string) Invoice::query()->latest('id')->firstOrFail()->net_total);
    }

    public function test_raising_the_price_above_the_list_price_is_allowed(): void
    {
        $this->checkout($this->cashier, ['unit_price' => '130.000'])->assertCreated();

        $this->assertSame('130.000', (string) $this->lastLine()->unit_price);
        $this->assertSame('100.000', (string) $this->lastLine()->list_unit_price);
        $this->assertNull($this->lastLine()->override_approved_by);
    }

    public function test_a_price_between_the_floor_and_the_list_price_is_allowed(): void
    {
        $this->checkout($this->cashier, ['unit_price' => '75.000'])->assertCreated();
    }

    // ----------------------------------------------------------------- floor

    public function test_below_minimum_is_rejected_without_an_override_and_does_not_reveal_the_floor(): void
    {
        $response = $this->checkout($this->cashier, ['unit_price' => '60.000']);

        $response->assertStatus(422)->assertJsonValidationErrors(['items.0.unit_price', 'override']);
        $this->assertStringNotContainsString('70', (string) $response->getContent());
        $this->assertSame(0, Invoice::count());
        $this->assertSame('100.000', (string) StoreStock::where('store_id', $this->store->id)->where('item_id', $this->item->id)->value('quantity'));
    }

    public function test_a_discount_cannot_push_the_net_below_the_minimum(): void
    {
        // 1 x 100 - 40 discount = 60 net < floor 70.
        $this->checkout($this->cashier, ['unit_price' => '100.000', 'discount' => '40.000'])->assertStatus(422);
        $this->assertSame(0, Invoice::count());
    }

    public function test_no_floor_means_any_price_is_accepted(): void
    {
        $this->checkout($this->cashier, ['item_id' => $this->plainItem->id, 'unit_price' => '1.000'])->assertCreated();
    }

    public function test_user_with_the_permission_may_sell_below_minimum_and_is_recorded_as_approver(): void
    {
        $this->checkout($this->manager, ['unit_price' => '60.000'])->assertCreated();

        $this->assertSame($this->manager->id, (int) $this->lastLine()->override_approved_by);
    }

    public function test_admin_may_sell_below_minimum(): void
    {
        $this->checkout($this->admin, ['unit_price' => '60.000'])->assertCreated();
        $this->assertSame($this->admin->id, (int) $this->lastLine()->override_approved_by);
    }

    public function test_a_valid_manager_pin_approves_a_below_minimum_sale(): void
    {
        $this->checkout($this->cashier, ['unit_price' => '60.000'], ['override' => ['user_id' => $this->manager->id, 'pin' => '4321']])
            ->assertCreated();

        $line = $this->lastLine();
        $this->assertSame($this->manager->id, (int) $line->override_approved_by);
        $this->assertSame('60.000', (string) $line->unit_price);
        $this->assertSame($this->cashier->id, (int) Invoice::query()->latest('id')->firstOrFail()->user_id);
    }

    public function test_one_pin_covers_every_below_minimum_line_of_the_invoice(): void
    {
        $this->checkout($this->cashier, [], [
            'items' => [
                ['item_id' => $this->item->id, 'quantity' => '1.000', 'unit_price' => '60.000'],
                ['item_id' => $this->item->id, 'quantity' => '2.000', 'unit_price' => '65.000'],
            ],
            'override' => ['user_id' => $this->manager->id, 'pin' => '4321'],
        ])->assertCreated();

        $this->assertSame(2, InvoiceItem::query()->where('override_approved_by', $this->manager->id)->count());
    }

    public function test_an_unneeded_override_is_ignored_and_records_no_approver(): void
    {
        $this->checkout($this->cashier, ['unit_price' => '90.000'], ['override' => ['user_id' => $this->manager->id, 'pin' => '0000']])
            ->assertCreated();

        $this->assertNull($this->lastLine()->override_approved_by);
    }

    public function test_wrong_pin_is_rejected_with_422(): void
    {
        $this->checkout($this->cashier, ['unit_price' => '60.000'], ['override' => ['user_id' => $this->manager->id, 'pin' => '9999']])
            ->assertStatus(422)->assertJsonValidationErrors(['override.pin']);

        $this->assertSame(0, Invoice::count());
    }

    public function test_an_approver_without_the_permission_or_without_a_pin_is_rejected(): void
    {
        $noPermission = $this->makeUser('01000000020', 'cashier');
        $noPermission->forceFill(['pos_pin' => Hash::make('1111')])->save();
        $this->checkout($this->cashier, ['unit_price' => '60.000'], ['override' => ['user_id' => $noPermission->id, 'pin' => '1111']])
            ->assertStatus(422)->assertJsonValidationErrors(['override.pin']);

        $noPin = $this->makeUser('01000000021', 'cashier');
        $noPin->givePermissionTo(Permission::findOrCreate('pos.price.below_min', 'web'));
        $this->checkout($this->cashier, ['unit_price' => '60.000'], ['override' => ['user_id' => $noPin->id, 'pin' => '1111']])
            ->assertStatus(422)->assertJsonValidationErrors(['override.pin']);

        $this->assertSame(0, Invoice::count());
    }

    public function test_an_inactive_manager_cannot_approve(): void
    {
        $this->manager->forceFill(['is_active' => false])->save();

        $this->checkout($this->cashier, ['unit_price' => '60.000'], ['override' => ['user_id' => $this->manager->id, 'pin' => '4321']])
            ->assertStatus(422);
    }

    public function test_wrong_pin_attempts_are_rate_limited_even_for_the_right_pin(): void
    {
        RateLimiter::clear('pos-override:approver:'.$this->manager->id);

        for ($i = 0; $i < 5; $i++) {
            $this->checkout($this->cashier, ['unit_price' => '60.000'], ['override' => ['user_id' => $this->manager->id, 'pin' => '0000']])
                ->assertStatus(422);
        }

        $this->checkout($this->cashier, ['unit_price' => '60.000'], ['override' => ['user_id' => $this->manager->id, 'pin' => '4321']])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
        $this->assertSame(0, Invoice::count());
    }

    public function test_override_pin_must_be_four_to_six_digits(): void
    {
        $this->checkout($this->cashier, ['unit_price' => '60.000'], ['override' => ['user_id' => $this->manager->id, 'pin' => '12']])
            ->assertStatus(422)->assertJsonValidationErrors(['override.pin']);
    }

    public function test_the_same_floor_applies_to_the_generic_invoices_endpoint(): void
    {
        $this->checkout($this->cashier, ['unit_price' => '60.000'], [], null, '/api/v1/invoices')->assertStatus(422);
        $this->checkout($this->cashier, ['unit_price' => '60.000'], ['override' => ['user_id' => $this->manager->id, 'pin' => '4321']], null, '/api/v1/invoices')->assertCreated();
        $this->assertSame($this->manager->id, (int) $this->lastLine()->override_approved_by);
    }

    // ------------------------------------------------------------- sanity

    public function test_line_discount_above_the_line_total_is_rejected(): void
    {
        $this->checkout($this->cashier, ['item_id' => $this->plainItem->id, 'unit_price' => '10.000', 'discount' => '10.001'])
            ->assertStatus(422)->assertJsonValidationErrors(['items.0.discount']);
    }

    public function test_negative_scientific_and_overlong_numbers_are_rejected(): void
    {
        $this->checkout($this->cashier, ['unit_price' => '-1'])->assertStatus(422)->assertJsonValidationErrors(['items.0.unit_price']);
        $this->checkout($this->cashier, ['unit_price' => '1e3'])->assertStatus(422)->assertJsonValidationErrors(['items.0.unit_price']);
        $this->checkout($this->cashier, ['unit_price' => '100.0001'])->assertStatus(422)->assertJsonValidationErrors(['items.0.unit_price']);
        $this->checkout($this->cashier, ['discount' => '-5'])->assertStatus(422)->assertJsonValidationErrors(['items.0.discount']);
        $this->assertSame(0, Invoice::count());
    }

    // ----------------------------------------------------------------- date

    public function test_pos_checkout_ignores_the_client_invoice_date(): void
    {
        $this->checkout($this->cashier, ['unit_price' => '100.000'], ['invoice_date' => '2020-01-01'])->assertCreated();

        $date = Invoice::query()->latest('id')->firstOrFail()->invoice_date->toDateString();
        $this->assertNotSame('2020-01-01', $date);
        $this->assertSame(app(TenantClock::class)->businessDate(), $date);
    }

    public function test_generic_invoice_rejects_a_future_date(): void
    {
        $this->checkout($this->cashier, ['unit_price' => '100.000'], ['invoice_date' => now()->addDays(3)->toDateString()], null, '/api/v1/invoices')
            ->assertStatus(422)->assertJsonValidationErrors(['invoice_date']);
    }

    // ------------------------------------------------- floor visibility

    public function test_cashier_resources_never_contain_the_floor_value(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $headers = [
            'X-Tenant' => (string) $this->tenant->getTenantKey(),
            'Authorization' => 'Bearer '.$this->cashier->createToken('t')->plainTextToken,
            'X-Store-Id' => (string) $this->store->id,
            'Accept' => 'application/json',
        ];

        $bootstrap = $this->withHeaders($headers)->getJson('/api/v1/pos/bootstrap')->assertOk();
        $row = collect($bootstrap->json('data.items'))->firstWhere('id', $this->item->id);
        $this->assertNull($row['min_selling_price']);
        $this->assertTrue($row['has_min_price']);
        $this->assertEquals(80.0, $row['price_wholesale']);
        $this->assertEquals(100.0, $row['price_retail']);
        $plain = collect($bootstrap->json('data.items'))->firstWhere('id', $this->plainItem->id);
        $this->assertFalse($plain['has_min_price']);
        $this->assertEquals(100.0, $plain['price_wholesale']);

        $lookup = $this->withHeaders($headers)->getJson('/api/v1/pos/lookup?code=PR-1')->assertOk();
        $this->assertNull($lookup->json('data.item.min_selling_price'));
        $this->assertTrue($lookup->json('data.item.has_min_price'));

        $grid = $this->withHeaders($headers)->getJson('/api/v1/pos/items')->assertOk();
        $gridRow = collect($grid->json('data'))->firstWhere('id', $this->item->id);
        $this->assertNull($gridRow['min_selling_price']);
        $this->assertTrue($gridRow['has_min_price']);
        $this->assertEquals(80.0, $gridRow['price_wholesale']);

        // The raw floor value appears nowhere in the payloads.
        $this->assertDoesNotMatchRegularExpression(
            '/"min_selling_price":\s*[1-9]/',
            $bootstrap->getContent().$lookup->getContent().$grid->getContent(),
        );
    }

    public function test_admin_sees_the_floor_value_in_pos_resources(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $response = $this->withHeaders([
            'X-Tenant' => (string) $this->tenant->getTenantKey(),
            'Authorization' => 'Bearer '.$this->admin->createToken('t')->plainTextToken,
            'X-Store-Id' => (string) $this->store->id,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/pos/bootstrap')->assertOk();

        $row = collect($response->json('data.items'))->firstWhere('id', $this->item->id);
        $this->assertEquals(70.0, $row['min_selling_price']);
    }

    // ---------------------------------------------------------------- authz

    public function test_checkout_requires_authentication_and_permission(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['X-Tenant' => (string) $this->tenant->getTenantKey(), 'Accept' => 'application/json'])
            ->postJson('/api/v1/pos/checkout', [])->assertStatus(401);

        $nobody = User::factory()->create(['phone' => '01000000099', 'is_active' => true, 'default_store_id' => $this->store->id]);
        $this->checkout($nobody, ['unit_price' => '100.000'])->assertStatus(403);
    }

    public function test_a_store_the_user_cannot_access_is_rejected_and_nothing_is_sold(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->withHeaders([
            'X-Tenant' => (string) $this->tenant->getTenantKey(),
            'Authorization' => 'Bearer '.$this->cashier->createToken('t')->plainTextToken,
            'X-Store-Id' => (string) $this->otherStore->id,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/pos/checkout', [
            'customer_id' => $this->retailCustomer->id,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [['item_id' => $this->item->id, 'quantity' => '1.000', 'unit_price' => '100.000']],
        ])->assertStatus(403);

        $this->assertSame(0, Invoice::count());
    }

    public function test_a_manager_without_access_to_the_sale_store_cannot_approve(): void
    {
        $remote = $this->makeUser('01000000030', 'cashier');
        $remote->forceFill(['default_store_id' => $this->otherStore->id, 'pos_pin' => Hash::make('5555')])->save();
        $remote->givePermissionTo(Permission::findOrCreate('pos.price.below_min', 'web'));

        $this->checkout($this->cashier, ['unit_price' => '60.000'], ['override' => ['user_id' => $remote->id, 'pin' => '5555']])
            ->assertStatus(422)->assertJsonValidationErrors(['override.pin']);
    }

    // ----------------------------------------------------------- PIN setup

    public function test_admin_sets_a_users_pin_hashed_and_validation_rejects_bad_pins(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $headers = [
            'X-Tenant' => (string) $this->tenant->getTenantKey(),
            'Authorization' => 'Bearer '.$this->admin->createToken('t')->plainTextToken,
            'Accept' => 'application/json',
        ];

        $this->withHeaders($headers)->putJson('/api/v1/users/'.$this->cashier->id.'/pos-pin', ['pin' => '12'])->assertStatus(422);
        $this->withHeaders($headers)->putJson('/api/v1/users/'.$this->cashier->id.'/pos-pin', ['pin' => '1234567'])->assertStatus(422);
        $this->withHeaders($headers)->putJson('/api/v1/users/'.$this->cashier->id.'/pos-pin', ['pin' => '123456'])
            ->assertOk()->assertJson(['has_pos_pin' => true]);

        $stored = (string) User::query()->findOrFail($this->cashier->id)->pos_pin;
        $this->assertNotSame('123456', $stored);
        $this->assertTrue(Hash::check('123456', $stored));

        $this->withHeaders($headers)->putJson('/api/v1/users/'.$this->cashier->id.'/pos-pin', ['pin' => null])
            ->assertOk()->assertJson(['has_pos_pin' => false]);
    }

    public function test_a_cashier_cannot_set_other_users_pins(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->withHeaders([
            'X-Tenant' => (string) $this->tenant->getTenantKey(),
            'Authorization' => 'Bearer '.$this->cashier->createToken('t')->plainTextToken,
            'Accept' => 'application/json',
        ])->putJson('/api/v1/users/'.$this->manager->id.'/pos-pin', ['pin' => '1234'])->assertStatus(403);

        $this->assertTrue(Hash::check('4321', (string) User::query()->findOrFail($this->manager->id)->pos_pin));
    }

    public function test_a_user_sets_their_own_pin_with_the_account_password(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $headers = [
            'X-Tenant' => (string) $this->tenant->getTenantKey(),
            'Authorization' => 'Bearer '.$this->manager->createToken('t')->plainTextToken,
            'Accept' => 'application/json',
        ];

        $this->withHeaders($headers)->putJson('/api/v1/profile/pos-pin', ['pin' => '2468', 'current_password' => 'wrong'])->assertStatus(422);
        $this->withHeaders($headers)->putJson('/api/v1/profile/pos-pin', ['pin' => '2468', 'current_password' => 'password'])->assertOk();

        $this->assertTrue(Hash::check('2468', (string) User::query()->findOrFail($this->manager->id)->pos_pin));
    }

    public function test_pos_pin_hash_is_never_serialized(): void
    {
        $this->assertArrayNotHasKey('pos_pin', $this->manager->toArray());
    }

    // -------------------------------------------------------------- item

    public function test_item_api_stores_and_returns_the_wholesale_price(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $headers = [
            'X-Tenant' => (string) $this->tenant->getTenantKey(),
            'Authorization' => 'Bearer '.$this->admin->createToken('t')->plainTextToken,
            'X-Store-Id' => (string) $this->store->id,
            'Accept' => 'application/json',
        ];

        $id = (int) $this->withHeaders($headers)->postJson('/api/v1/items', [
            'name' => 'W', 'unit' => 'كجم', 'cost_price' => '10', 'selling_price' => '30', 'wholesale_price' => '25.5',
        ])->assertCreated()->assertJsonPath('data.wholesale_price', 25.5)->json('data.id');

        $this->assertSame('25.500', (string) Item::query()->findOrFail($id)->wholesale_price);
        $this->assertSame('0.000', (string) Item::query()->findOrFail($id)->min_selling_price);

        $this->withHeaders($headers)->putJson('/api/v1/items/'.$id, [
            'name' => 'W', 'unit' => 'كجم', 'cost_price' => '10', 'selling_price' => '30',
        ])->assertOk();
        $this->assertSame('25.500', (string) Item::query()->findOrFail($id)->wholesale_price, 'a missing key keeps the stored value');
    }

    public function test_list_price_below_the_floor_still_needs_approval(): void
    {
        // Misconfigured item: retail 100 but floor 120. Selling at the list price is still below the floor.
        $item = $this->makeItem('PR-3', ['selling_price' => '100.000', 'min_selling_price' => '120.000']);

        $this->checkout($this->cashier, ['item_id' => $item->id, 'unit_price' => '100.000'])->assertStatus(422);
        $this->checkout($this->cashier, ['item_id' => $item->id], [])->assertStatus(422);
        $this->checkout($this->manager, ['item_id' => $item->id, 'unit_price' => '100.000'])->assertCreated();
    }

    public function test_failed_pin_attempts_are_counted_per_tenant(): void
    {
        $key = 'pos-override:'.RateLimitKey::scope().':approver:'.$this->manager->id;
        RateLimiter::clear($key);

        $this->checkout($this->cashier, ['unit_price' => '60.000'], ['override' => ['user_id' => $this->manager->id, 'pin' => '0000']])->assertStatus(422);

        $this->assertSame(1, RateLimiter::attempts($key));
        $this->assertStringContainsString('t:', $key);
        // Another tenant (or central) with the same user id uses a different key.
        $this->assertSame(0, RateLimiter::attempts('pos-override:t:other-tenant:approver:'.$this->manager->id));
        $this->assertSame(0, RateLimiter::attempts('pos-override:c:approver:'.$this->manager->id));
    }

    public function test_failed_pin_hits_survive_the_failed_request(): void
    {
        $key = 'pos-override:'.RateLimitKey::scope().':approver:'.$this->manager->id;
        RateLimiter::clear($key);

        for ($i = 1; $i <= 3; $i++) {
            $this->checkout($this->cashier, ['unit_price' => '60.000'], ['override' => ['user_id' => $this->manager->id, 'pin' => '0000']])->assertStatus(422);
            $this->assertSame($i, RateLimiter::attempts($key));
        }
    }

    public function test_roles_manage_user_can_set_a_non_admin_pin_but_not_an_admins(): void
    {
        $hr = $this->makeUser('01000000040', 'cashier');
        $hr->givePermissionTo(Permission::findOrCreate('roles.manage', 'web'));

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $headers = [
            'X-Tenant' => (string) $this->tenant->getTenantKey(),
            'Authorization' => 'Bearer '.$hr->createToken('t')->plainTextToken,
            'Accept' => 'application/json',
        ];

        $this->withHeaders($headers)->putJson('/api/v1/users/'.$this->cashier->id.'/pos-pin', ['pin' => '1357'])->assertOk();
        $this->assertTrue(Hash::check('1357', (string) User::query()->findOrFail($this->cashier->id)->pos_pin));

        $this->withHeaders($headers)->putJson('/api/v1/users/'.$this->admin->id.'/pos-pin', ['pin' => '1357'])->assertStatus(403);
        $this->assertNull(User::query()->findOrFail($this->admin->id)->pos_pin);
    }

    public function test_item_api_hides_the_floor_from_users_without_view_cost_and_update_keeps_it(): void
    {
        $editor = $this->makeUser('01000000041', 'cashier');
        $editor->givePermissionTo(Permission::findOrCreate('items.edit', 'web'));
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $headers = [
            'X-Tenant' => (string) $this->tenant->getTenantKey(),
            'Authorization' => 'Bearer '.$editor->createToken('t')->plainTextToken,
            'X-Store-Id' => (string) $this->store->id,
            'Accept' => 'application/json',
        ];

        $this->withHeaders($headers)->getJson('/api/v1/items/'.$this->item->id)->assertOk()
            ->assertJsonPath('data.min_selling_price', null)->assertJsonPath('data.has_min_price', true);
        $list = $this->withHeaders($headers)->getJson('/api/v1/items')->assertOk();
        $this->assertDoesNotMatchRegularExpression('/"min_selling_price":\s*[1-9]/', $list->getContent());

        // The form omits the hidden floor: the stored one survives.
        $this->withHeaders($headers)->putJson('/api/v1/items/'.$this->item->id, [
            'name' => 'renamed', 'unit' => 'كجم', 'cost_price' => '50', 'selling_price' => '100', 'wholesale_price' => '80',
        ])->assertOk();
        $this->assertSame('70.000', (string) Item::query()->findOrFail($this->item->id)->min_selling_price);
        // A null floor is also "keep".
        $this->withHeaders($headers)->putJson('/api/v1/items/'.$this->item->id, [
            'name' => 'renamed', 'unit' => 'كجم', 'cost_price' => '50', 'selling_price' => '100', 'min_selling_price' => null,
        ])->assertOk();
        $this->assertSame('70.000', (string) Item::query()->findOrFail($this->item->id)->min_selling_price);
    }

    public function test_wholesale_price_cannot_be_below_the_floor(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $headers = [
            'X-Tenant' => (string) $this->tenant->getTenantKey(),
            'Authorization' => 'Bearer '.$this->admin->createToken('t')->plainTextToken,
            'X-Store-Id' => (string) $this->store->id,
            'Accept' => 'application/json',
        ];
        $base = ['name' => 'W', 'unit' => 'كجم', 'cost_price' => '10', 'selling_price' => '100'];

        $this->withHeaders($headers)->postJson('/api/v1/items', $base + ['min_selling_price' => '70', 'wholesale_price' => '60'])
            ->assertStatus(422)->assertJsonValidationErrors(['wholesale_price']);
        $this->withHeaders($headers)->postJson('/api/v1/items', $base + ['min_selling_price' => '70', 'wholesale_price' => '70'])->assertCreated();
        $this->withHeaders($headers)->postJson('/api/v1/items', $base + ['min_selling_price' => '70'])->assertCreated();
        // Update: wholesale 60 against the stored floor 70.
        $this->withHeaders($headers)->putJson('/api/v1/items/'.$this->item->id, $base + ['wholesale_price' => '60'])
            ->assertStatus(422)->assertJsonValidationErrors(['wholesale_price']);
        // Raising only the floor above the stored wholesale (80) is rejected too.
        $this->withHeaders($headers)->putJson('/api/v1/items/'.$this->item->id, $base + ['min_selling_price' => '90'])
            ->assertStatus(422)->assertJsonValidationErrors(['wholesale_price']);
    }

    public function test_update_without_view_cost_ignores_a_sent_cost_and_keeps_the_stored_one(): void
    {
        $editor = $this->makeUser('01000000050', 'cashier');
        $editor->givePermissionTo(Permission::findOrCreate('items.edit', 'web'));
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $headers = [
            'X-Tenant' => (string) $this->tenant->getTenantKey(),
            'Authorization' => 'Bearer '.$editor->createToken('t')->plainTextToken,
            'X-Store-Id' => (string) $this->store->id,
            'Accept' => 'application/json',
        ];
        $base = ['name' => 'renamed', 'unit' => 'كجم', 'selling_price' => '110'];

        $this->withHeaders($headers)->getJson('/api/v1/items/'.$this->item->id)->assertOk()->assertJsonPath('data.cost_price', null);

        // Key omitted, null, or a forged value: the cost stays 50.
        $this->withHeaders($headers)->putJson('/api/v1/items/'.$this->item->id, $base)->assertOk();
        $this->withHeaders($headers)->putJson('/api/v1/items/'.$this->item->id, $base + ['cost_price' => null])->assertOk();
        $this->withHeaders($headers)->putJson('/api/v1/items/'.$this->item->id, $base + ['cost_price' => '1'])->assertOk();

        $fresh = Item::query()->findOrFail($this->item->id);
        $this->assertSame('50.000', (string) $fresh->cost_price);
        $this->assertSame('110.000', (string) $fresh->selling_price);
        $this->assertSame('renamed', $fresh->name);
    }

    public function test_user_who_can_see_cost_can_change_it_and_create_still_requires_it(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $headers = [
            'X-Tenant' => (string) $this->tenant->getTenantKey(),
            'Authorization' => 'Bearer '.$this->admin->createToken('t')->plainTextToken,
            'X-Store-Id' => (string) $this->store->id,
            'Accept' => 'application/json',
        ];
        $base = ['name' => 'x', 'unit' => 'كجم', 'selling_price' => '110'];

        $this->withHeaders($headers)->putJson('/api/v1/items/'.$this->item->id, $base + ['cost_price' => '55.5'])->assertOk();
        $this->assertSame('55.500', (string) Item::query()->findOrFail($this->item->id)->cost_price);

        $this->withHeaders($headers)->postJson('/api/v1/items', $base)->assertStatus(422)->assertJsonValidationErrors(['cost_price']);
    }
}
