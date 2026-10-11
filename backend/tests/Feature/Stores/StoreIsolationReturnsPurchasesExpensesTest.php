<?php

declare(strict_types=1);

namespace Tests\Feature\Stores;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\ReturnDocument;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * STOR-2 (H1): returns, purchases and expenses are branch-scoped like invoices and shifts.
 *  - `store.active` is mounted on every route, `store.active:concrete` on the creates;
 *  - a record of store B requested by a user of store A is 404 (CTO Q13) on show, update,
 *    delete and cancel, and nothing changes: stock, supplier balance and the record stay put;
 *  - the lists cover the active store only; a header or ?store_id of B is 403;
 *  - a create books on the active store: a body store_id of B is 403, `all` is 403.
 */
final class StoreIsolationReturnsPurchasesExpensesTest extends TenantTestCase
{
    /** Permissions the user of A gets, so only the STORE decides the outcome. */
    private const PERMISSIONS = [
        'returns.manage', 'purchases.view', 'purchases.create', 'purchases.delete', 'expenses.manage',
    ];

    /**
     * @return array<string, array{string, string, string, bool}>
     */
    public static function mountedEndpoints(): array
    {
        return [
            'returns index' => ['api.returns.index', 'GET', '/api/v1/returns', false],
            'returns show' => ['api.returns.show', 'GET', '/api/v1/returns/1', false],
            'returns store' => ['api.returns.store', 'POST', '/api/v1/returns', true],
            'returns destroy' => ['api.returns.destroy', 'DELETE', '/api/v1/returns/1', false],
            'purchases index' => ['api.purchases.index', 'GET', '/api/v1/purchases', false],
            'purchases smart reorder' => ['api.purchases.smart_reorder', 'GET', '/api/v1/purchases/smart-reorder', false],
            'purchases show' => ['api.purchases.show', 'GET', '/api/v1/purchases/1', false],
            'purchases store' => ['api.purchases.store', 'POST', '/api/v1/purchases', true],
            'purchases cancel' => ['api.purchases.cancel', 'POST', '/api/v1/purchases/1/cancel', false],
            'expenses index' => ['api.expenses.index', 'GET', '/api/v1/expenses', false],
            'expenses show' => ['api.expenses.show', 'GET', '/api/v1/expenses/1', false],
            'expenses store' => ['api.expenses.store', 'POST', '/api/v1/expenses', true],
            'expenses update' => ['api.expenses.update', 'PUT', '/api/v1/expenses/1', false],
            'expenses destroy' => ['api.expenses.destroy', 'DELETE', '/api/v1/expenses/1', false],
        ];
    }

    #[DataProvider('mountedEndpoints')]
    public function test_every_endpoint_is_store_scoped(string $name, string $method, string $uri, bool $concrete): void
    {
        $route = Route::getRoutes()->getByName($name);
        $this->assertNotNull($route, $name);
        $this->assertContains($concrete ? 'store.active:concrete' : 'store.active', $route->gatherMiddleware(), $name);

        $tenant = $this->createTenant();
        $seed = $this->seedStores($tenant);
        $userA = $this->userOfA($tenant);
        $storeless = $this->createTenantUser($tenant, 'cashier', self::PERMISSIONS, ['default_store_id' => null]);

        foreach ([$seed['b'], 'all'] as $store) {
            $this->json($method, $uri, [], $this->headersFor($tenant, $userA, $store))
                ->assertStatus(403)
                ->assertJsonPath('error_code', 'store_access_denied');
        }

        // No header and no usable store: only ResolveActiveStore refuses this.
        $this->json($method, $uri, [], $this->headersFor($tenant, $storeless, null))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'store_access_denied');

        $all = $this->json($method, $uri, [], $this->headersFor($tenant, null, 'all'));
        if ($concrete) {
            $all->assertStatus(403)->assertJsonPath('error_code', 'concrete_store_required');
        } else {
            $this->assertNotSame(403, $all->getStatusCode(), $name.' refused `all` for the admin');
        }

        $this->assertSame('20.000', $this->stockOf($tenant, $seed['b'], $seed['item']));
        $this->assertSame(0, $this->inTenant($tenant, fn (): int => ReturnDocument::query()->count()));
        $this->assertSame(0, $this->inTenant($tenant, fn (): int => Purchase::query()->count()));
        $this->assertSame(0, $this->inTenant($tenant, fn (): int => Expense::query()->count()));
    }

    public function test_return_of_store_b_is_not_found_for_a_user_of_a_and_is_not_deleted(): void
    {
        $tenant = $this->createTenant();
        $seed = $this->seedStores($tenant);
        $returnB = (int) $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return',
            'customer_id' => $seed['customer'],
            'return_date' => now()->toDateString(),
            'items' => [['item_id' => $seed['item'], 'quantity' => '0.250', 'unit_price' => '20.000']],
        ], $this->tenantHeaders($tenant, null, $seed['b']))->assertStatus(201)->json('data.id');
        $this->assertSame('20.250', $this->stockOf($tenant, $seed['b'], $seed['item']));

        $userA = $this->userOfA($tenant);
        $headers = $this->tenantHeaders($tenant, $userA);

        $this->getJson('/api/v1/returns/'.$returnB, $headers)->assertStatus(404);
        $this->deleteJson('/api/v1/returns/'.$returnB, [], $headers)->assertStatus(404);

        $this->assertNotNull($this->inTenant($tenant, fn () => ReturnDocument::query()->find($returnB)), 'return was deleted');
        $this->assertSame('20.250', $this->stockOf($tenant, $seed['b'], $seed['item']));
        $this->assertNotContains($returnB, collect($this->getJson('/api/v1/returns', $headers)->assertOk()->json('data'))->pluck('id')->all());
        $this->getJson('/api/v1/returns?store_id='.$seed['b'], $headers)->assertStatus(403);

        // A user assigned to B reads it; a user without the permission still gets 403, not 404.
        $this->getJson('/api/v1/returns/'.$returnB, $this->tenantHeaders($tenant, $this->userOfAAndB($tenant, $seed)))->assertOk();
        $this->getJson('/api/v1/returns/'.$returnB, $this->tenantHeaders($tenant, $this->createTenantUser($tenant, null, ['items.view'])))
            ->assertStatus(403);
    }

    public function test_purchase_of_store_b_is_not_found_for_a_user_of_a_and_cannot_be_cancelled(): void
    {
        $tenant = $this->createTenant();
        $seed = $this->seedStores($tenant);
        $purchaseB = (int) $this->postJson('/api/v1/purchases', [
            'supplier_id' => $seed['supplier'],
            'purchase_date' => now()->toDateString(),
            'paid_amount' => '0.000',
            'items' => [['item_id' => $seed['item'], 'quantity' => '1.500', 'unit_cost' => '10.000']],
        ], $this->tenantHeaders($tenant, null, $seed['b']))->assertStatus(201)->json('data.id');
        $this->assertSame('21.500', $this->stockOf($tenant, $seed['b'], $seed['item']));
        $supplierBalance = $this->supplierBalance($tenant, $seed['supplier']);

        $userA = $this->userOfA($tenant);
        $headers = $this->tenantHeaders($tenant, $userA);

        $this->getJson('/api/v1/purchases/'.$purchaseB, $headers)->assertStatus(404);
        $this->postJson('/api/v1/purchases/'.$purchaseB.'/cancel', ['reason' => 'x'], $headers)->assertStatus(404);

        $this->assertSame('confirmed', $this->inTenant($tenant, fn () => Purchase::query()->whereKey($purchaseB)->value('status')));
        $this->assertSame('21.500', $this->stockOf($tenant, $seed['b'], $seed['item']));
        $this->assertSame($supplierBalance, $this->supplierBalance($tenant, $seed['supplier']));
        $this->assertNotContains($purchaseB, collect($this->getJson('/api/v1/purchases', $headers)->assertOk()->json('data'))->pluck('id')->all());

        $this->getJson('/api/v1/purchases/'.$purchaseB, $this->tenantHeaders($tenant, $this->userOfAAndB($tenant, $seed)))->assertOk();
    }

    public function test_expense_of_store_b_is_not_found_for_a_user_of_a_and_stays_unchanged(): void
    {
        $tenant = $this->createTenant();
        $seed = $this->seedStores($tenant);
        $payload = [
            'title' => 'إيجار فرع ب',
            'category' => 'إيجار',
            'cost_center' => 'rent',
            'amount' => '125.500',
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ];
        $expenseB = (int) $this->postJson('/api/v1/expenses', $payload, $this->tenantHeaders($tenant, null, $seed['b']))
            ->assertStatus(201)
            ->json('data.id');

        $userA = $this->userOfA($tenant);
        $headers = $this->tenantHeaders($tenant, $userA);

        $this->getJson('/api/v1/expenses/'.$expenseB, $headers)->assertStatus(404);
        $this->putJson('/api/v1/expenses/'.$expenseB, array_merge($payload, ['amount' => '1.000', 'title' => 'مخترق']), $headers)
            ->assertStatus(404);
        $this->deleteJson('/api/v1/expenses/'.$expenseB, [], $headers)->assertStatus(404);

        $row = $this->inTenant($tenant, fn () => Expense::query()->find($expenseB));
        $this->assertInstanceOf(Expense::class, $row, 'expense was deleted');
        $this->assertSame('125.500', (string) $row->amount);
        $this->assertSame('إيجار فرع ب', $row->title);
        $this->assertSame($seed['b'], (int) $row->store_id);
        $this->assertNotContains($expenseB, collect($this->getJson('/api/v1/expenses', $headers)->assertOk()->json('data'))->pluck('id')->all());
    }

    public function test_creates_book_on_the_active_store_and_refuse_another_store_in_the_body(): void
    {
        $tenant = $this->createTenant();
        $seed = $this->seedStores($tenant);
        $userA = $this->userOfA($tenant);
        $headers = $this->tenantHeaders($tenant, $userA);
        $expense = [
            'title' => 'نثريات',
            'category' => 'تشغيل',
            'cost_center' => 'operational',
            'amount' => '10.000',
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ];

        $this->postJson('/api/v1/expenses', $expense + ['store_id' => $seed['b']], $headers)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'store_access_denied');
        $this->postJson('/api/v1/purchases', [
            'supplier_id' => $seed['supplier'],
            'purchase_date' => now()->toDateString(),
            'store_id' => $seed['b'],
            'items' => [['item_id' => $seed['item'], 'quantity' => '1.000', 'unit_cost' => '10.000']],
        ], $headers)->assertStatus(403);
        $this->assertSame(0, $this->inTenant($tenant, fn (): int => Expense::query()->count()));
        $this->assertSame(0, $this->inTenant($tenant, fn (): int => Purchase::query()->count()));
        $this->assertSame('20.000', $this->stockOf($tenant, $seed['b'], $seed['item']));

        $id = (int) $this->postJson('/api/v1/expenses', $expense, $headers)->assertStatus(201)->json('data.id');
        $this->assertSame($seed['a'], (int) $this->inTenant($tenant, fn () => Expense::query()->whereKey($id)->value('store_id')));
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    /**
     * Store A = the harness main store, B an extra branch; one item with stock in A and B.
     *
     * @return array{a: int, b: int, item: int, customer: int, supplier: int}
     */
    private function seedStores(Tenant $tenant): array
    {
        $storeA = (int) $this->tenantStore($tenant)->id;

        return $this->inTenant($tenant, function () use ($storeA): array {
            $storeB = (int) Store::query()->create([
                'name' => 'فرع ب',
                'code' => 'BR-B',
                'type' => 'retail',
                'is_main' => false,
                'is_active' => true,
            ])->id;

            $item = Item::query()->create([
                'code' => 'ISO-RPE',
                'name' => 'صنف العزل',
                'unit' => 'كجم',
                'cost_price' => '10.000',
                'selling_price' => '20.000',
                'current_stock' => '30.000',
                'is_active' => true,
            ]);

            StoreStock::query()->create(['store_id' => $storeA, 'item_id' => $item->id, 'quantity' => '10.000']);
            StoreStock::query()->create(['store_id' => $storeB, 'item_id' => $item->id, 'quantity' => '20.000']);

            $customer = Customer::query()->create([
                'name' => 'عميل العزل',
                'phone' => '01000007712',
                'current_balance' => '0.000',
                'is_active' => true,
            ]);

            $supplier = Supplier::query()->create([
                'name' => 'مورد العزل',
                'phone' => '01000007713',
                'current_balance' => '0.000',
                'is_active' => true,
            ]);

            return [
                'a' => $storeA,
                'b' => $storeB,
                'item' => (int) $item->id,
                'customer' => (int) $customer->id,
                'supplier' => (int) $supplier->id,
            ];
        });
    }

    private function userOfA(Tenant $tenant): User
    {
        return $this->createTenantUser($tenant, 'cashier', self::PERMISSIONS);
    }

    /**
     * @param  array{b: int}  $seed
     */
    private function userOfAAndB(Tenant $tenant, array $seed): User
    {
        $user = $this->userOfA($tenant);
        $this->inTenant($tenant, fn () => User::query()->findOrFail($user->id)->stores()->attach($seed['b']));

        return $user;
    }

    private function stockOf(Tenant $tenant, int $storeId, int $itemId): string
    {
        return (string) $this->inTenant($tenant, fn () => StoreStock::query()
            ->where('store_id', $storeId)->where('item_id', $itemId)->value('quantity'));
    }

    private function supplierBalance(Tenant $tenant, int $supplierId): string
    {
        return (string) $this->inTenant($tenant, fn () => Supplier::query()->whereKey($supplierId)->value('current_balance'));
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(Tenant $tenant, ?User $user, int|string|null $store): array
    {
        $headers = $this->tenantHeaders($tenant, $user);
        unset($headers['X-Store-Id']);

        if ($store !== null) {
            $headers['X-Store-Id'] = (string) $store;
        }

        return $headers;
    }
}
