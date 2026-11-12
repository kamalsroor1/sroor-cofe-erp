<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ReturnDocument;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantClock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

/**
 * ROLE-01: what a cashier may do and see.
 *  - deleting a return / a customer needs returns.delete / customers.delete (admin only by default);
 *  - the dashboard and the treasury summary of a user without reports.view hold only their own
 *    shift (day) movement, never store totals, drawer or account balances; never cached for others;
 *  - expenses: without expenses.manage / reports.view only the ones they recorded;
 *  - a non-admin user needs a branch (422), assigned in the same save; a user with no branch at
 *    all gets `no_store_assigned` instead of the generic store 403;
 *  - the cashier stays locked out of reports / purchases / users / settings / roles.
 */
final class CashierRoleScopeApiTest extends TenantTestCase
{
    private const MIGRATION = 'database/migrations/tenant/2026_11_16_100000_seed_returns_and_customers_delete_permissions.php';

    private const PASSWORD = 'fixtureCashier123';

    private Tenant $tenant;

    private int $storeId;

    private int $customerId;

    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;

        [$this->customerId, $this->itemId] = $this->inTenant($this->tenant, function (): array {
            $customer = Customer::create(['name' => 'عميل الكاشير', 'phone' => '01000008801', 'price_tier' => 'retail', 'is_active' => true, 'current_balance' => '75.000']);
            Supplier::create(['name' => 'مورد', 'phone' => '01000008802', 'current_balance' => '40.000', 'is_active' => true]);
            $item = Item::create([
                'name' => 'بن', 'code' => 'ROLE-BN', 'category' => 'coffee_beans', 'unit' => 'كجم',
                'cost_price' => '350.000', 'weighted_avg_cost' => '350.000', 'selling_price' => '480.000',
                'price_retail' => '480.000', 'price_wholesale' => '440.000',
                'current_stock' => '100.000', 'min_stock_level' => '150.000', 'is_active' => true,
            ]);
            StoreStock::create(['store_id' => $this->storeId, 'item_id' => $item->id, 'quantity' => '100.000']);

            return [(int) $customer->id, (int) $item->id];
        });
    }

    // ------------------------------------------------------------------ returns.delete

    public function test_cashier_cannot_delete_a_return_and_nothing_is_reversed(): void
    {
        $id = $this->createReturnAsAdmin();
        $this->assertSame('103.000', $this->stock());

        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $this->deleteJson('/api/v1/returns/'.$id, [], $this->tenantHeaders($this->tenant, $cashier))
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertSame('103.000', $this->stock(), 'A refused delete must not reverse stock.');
        $this->assertTrue($this->inTenant($this->tenant, fn (): bool => ReturnDocument::query()->whereKey($id)->exists()));

        // Creating and viewing returns stays on returns.manage.
        $this->getJson('/api/v1/returns/'.$id, $this->tenantHeaders($this->tenant, $cashier))->assertOk();
    }

    public function test_admin_deletes_a_return(): void
    {
        $id = $this->createReturnAsAdmin();

        $this->deleteJson('/api/v1/returns/'.$id, [], $this->tenantHeaders($this->tenant))->assertOk();
        $this->assertSame('100.000', $this->stock());
    }

    public function test_a_custom_role_with_returns_manage_and_returns_delete_deletes_a_return(): void
    {
        $id = $this->createReturnAsAdmin();
        $this->inTenant($this->tenant, fn () => Role::findOrCreate('returns-clerk', 'web')->syncPermissions(['returns.manage', 'returns.delete']));
        $clerk = $this->createTenantUser($this->tenant, 'returns-clerk');

        $this->deleteJson('/api/v1/returns/'.$id, [], $this->tenantHeaders($this->tenant, $clerk))->assertOk();
        $this->assertSame('100.000', $this->stock());
    }

    public function test_returns_delete_alone_without_returns_manage_is_forbidden(): void
    {
        $id = $this->createReturnAsAdmin();
        $user = $this->createTenantUser($this->tenant, null, ['returns.delete']);

        $this->deleteJson('/api/v1/returns/'.$id, [], $this->tenantHeaders($this->tenant, $user))->assertForbidden();
        $this->assertSame('103.000', $this->stock());
    }

    public function test_deleting_a_return_requires_authentication(): void
    {
        $id = $this->createReturnAsAdmin();

        $this->deleteJson('/api/v1/returns/'.$id, [], $this->tenantGuestHeaders($this->tenant))->assertUnauthorized();
    }

    public function test_purging_a_trashed_return_or_customer_needs_the_delete_permission(): void
    {
        $returnId = $this->createReturnAsAdmin();
        $this->deleteJson('/api/v1/returns/'.$returnId, [], $this->tenantHeaders($this->tenant))->assertOk();
        $this->deleteJson('/api/v1/customers/'.$this->customerWithoutBalance(), [], $this->tenantHeaders($this->tenant))->assertOk();
        $customerId = $this->inTenant($this->tenant, fn (): int => (int) Customer::onlyTrashed()->value('id'));

        $janitor = $this->createTenantUser($this->tenant, null, ['trash.access']);
        $headers = $this->tenantHeaders($this->tenant, $janitor);

        $this->deleteJson("/api/v1/trash/returns/{$returnId}/force", [], $headers)->assertForbidden();
        $this->deleteJson("/api/v1/trash/customers/{$customerId}/force", [], $headers)->assertForbidden();
        $this->assertTrue($this->inTenant($this->tenant, fn (): bool => ReturnDocument::onlyTrashed()->whereKey($returnId)->exists()));
        $this->assertTrue($this->inTenant($this->tenant, fn (): bool => Customer::onlyTrashed()->whereKey($customerId)->exists()));

        $this->deleteJson("/api/v1/trash/customers/{$customerId}/force", [], $this->tenantHeaders($this->tenant))->assertOk();
    }

    // ------------------------------------------------------------------ customers.delete

    public function test_cashier_cannot_delete_a_customer_and_admin_can(): void
    {
        $id = $this->customerWithoutBalance();
        $cashier = $this->createTenantUser($this->tenant, 'cashier');

        $this->deleteJson('/api/v1/customers/'.$id, [], $this->tenantHeaders($this->tenant, $cashier))->assertForbidden();
        $this->assertTrue($this->inTenant($this->tenant, fn (): bool => Customer::query()->whereKey($id)->exists()));

        // Updating stays on customers.manage.
        $this->putJson('/api/v1/customers/'.$id, ['name' => 'عميل معدل', 'phone' => '01000008803', 'price_tier' => 'retail'], $this->tenantHeaders($this->tenant, $cashier))->assertOk();

        $this->deleteJson('/api/v1/customers/'.$id, [], $this->tenantGuestHeaders($this->tenant))->assertUnauthorized();
        $this->deleteJson('/api/v1/customers/'.$id, [], $this->tenantHeaders($this->tenant))->assertOk();
        $this->assertFalse($this->inTenant($this->tenant, fn (): bool => Customer::query()->whereKey($id)->exists()));
    }

    public function test_a_user_granted_customers_delete_may_delete_a_customer(): void
    {
        $id = $this->customerWithoutBalance();
        $user = $this->createTenantUser($this->tenant, 'cashier', ['customers.delete']);

        $this->deleteJson('/api/v1/customers/'.$id, [], $this->tenantHeaders($this->tenant, $user))->assertOk();
    }

    // ------------------------------------------------------------------ seeder + migration

    public function test_new_delete_permissions_are_seeded_for_admin_only(): void
    {
        $holders = $this->inTenant($this->tenant, fn (): array => [
            'returns.delete' => Role::query()->whereHas('permissions', fn ($q) => $q->where('name', 'returns.delete'))->orderBy('name')->pluck('name')->all(),
            'customers.delete' => Role::query()->whereHas('permissions', fn ($q) => $q->where('name', 'customers.delete'))->orderBy('name')->pluck('name')->all(),
        ]);

        $this->assertSame(['admin'], $holders['returns.delete']);
        $this->assertSame(['admin'], $holders['customers.delete']);
    }

    public function test_migration_backfills_admin_only_on_a_tenant_that_predates_it_and_is_reversible(): void
    {
        $names = ['returns.delete', 'customers.delete'];

        $this->inTenant($this->tenant, function () use ($names): void {
            // The role set as it was before ROLE-01: the permissions do not exist yet.
            Permission::query()->whereIn('name', $names)->get()->each(fn (Permission $p) => $p->delete());
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->assertSame(0, Permission::query()->whereIn('name', $names)->count());

            $this->runMigration('up');
            $this->runMigration('up'); // idempotent
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach ($names as $name) {
                $this->assertSame(1, Permission::query()->where('name', $name)->where('guard_name', 'web')->count());
                $this->assertTrue(Role::findByName('admin')->hasPermissionTo($name), "admin holds {$name}");
                foreach (['cashier', 'storekeeper', 'accountant'] as $role) {
                    $this->assertFalse(Role::findByName($role)->hasPermissionTo($name), "{$role} must not receive {$name}");
                }
            }

            $this->runMigration('down');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->assertSame(0, Permission::query()->whereIn('name', $names)->count());
        });
    }

    // ------------------------------------------------------------------ dashboard

    public function test_cashier_dashboard_holds_only_their_own_open_shift(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $other = $this->createTenantUser($this->tenant, 'cashier');
        $adminId = $this->adminId();

        // ShiftService allows ONE open shift per store: $other sells inside the cashier's shift.
        $this->openShift($cashier, now()->subHour());

        $this->sale($cashier, '100.000', 'cash');
        $this->sale($cashier, '40.500', 'credit');
        $this->sale($cashier, '999.000', 'cash', now()->subHours(3)); // before their shift opened
        $this->sale($other, '70.000', 'cash');
        $this->sale($adminId, '500.000', 'cash');
        $this->salesReturn($cashier, '10.250');
        $this->salesReturn($other, '5.000');

        $response = $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant, $cashier))->assertOk()
            ->assertJsonPath('data.scope', 'own_shift')
            ->assertJsonPath('data.can_view_financials', false)
            ->assertJsonPath('data.metrics.today_invoices_count', 2)
            ->assertJsonPath('data.metrics.today_gross_sales', '140.500')
            ->assertJsonPath('data.metrics.today_returns', '10.250')
            ->assertJsonPath('data.metrics.today_returns_count', 1)
            ->assertJsonPath('data.metrics.today_sales', '130.250')
            ->assertJsonPath('data.metrics.cash_sales', '100.000')
            ->assertJsonPath('data.metrics.credit_sales', '40.500')
            ->assertJsonPath('data.analytics', null)
            ->assertJsonPath('data.top_selling_items', [])
            ->assertJsonPath('data.active_shift.user_name', $cashier->name)
            ->assertJsonPath('data.active_shift.is_own', true);

        foreach (['total_cash_collected', 'today_expenses', 'expenses_count', 'supplier_payments', 'net_cash_today', 'customers_debt',
            'suppliers_debt', 'monthly_sales', 'monthly_gross_sales', 'monthly_returns', 'monthly_gross_profit', 'monthly_margin',
            'customers_count', 'suppliers_count'] as $key) {
            $response->assertJsonPath('data.metrics.'.$key, null);
        }

        $shift = $response->json('data.active_shift');
        $this->assertArrayNotHasKey('current_cash', $shift, 'No drawer balance for a cashier.');
        $this->assertArrayNotHasKey('starting_cash', $shift);

        $recent = $response->json('data.recent_invoices');
        $this->assertCount(2, $recent);
        $this->assertEqualsCanonicalizing(['100.000', '40.500'], array_column($recent, 'net_total'));

        // Cashier role has items.view: the low-stock radar is shown.
        $response->assertJsonPath('data.metrics.low_stock_count', 1);
        $this->assertCount(1, $response->json('data.low_stock_items'));
    }

    public function test_dashboard_without_an_own_shift_is_all_zeros_and_without_items_view_no_low_stock(): void
    {
        $this->sale($this->adminId(), '500.000', 'cash');
        $user = $this->createTenantUser($this->tenant, null, ['pos.access']);

        $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant, $user))->assertOk()
            ->assertJsonPath('data.scope', 'own_shift')
            ->assertJsonPath('data.metrics.today_sales', '0.000')
            ->assertJsonPath('data.metrics.today_invoices_count', 0)
            ->assertJsonPath('data.metrics.low_stock_count', null)
            ->assertJsonPath('data.low_stock_items', [])
            ->assertJsonPath('data.active_shift', null)
            ->assertJsonPath('data.recent_invoices', []);
    }

    public function test_cashier_cannot_widen_the_dashboard_with_all_stores(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $headers = $this->tenantHeaders($this->tenant, $cashier);
        $headers['X-Store-Id'] = 'all';

        $this->getJson('/api/v1/dashboard', $headers)->assertForbidden();
    }

    public function test_dashboard_cache_never_leaks_between_admin_and_cashier_in_either_direction(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $this->openShift($cashier, now()->subHour());
        $this->sale($cashier, '20.000', 'cash');
        $this->sale($this->adminId(), '300.000', 'cash');

        // Admin first: the store cache is warm with store-wide figures.
        $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant))->assertOk()
            ->assertJsonPath('data.scope', 'store')
            ->assertJsonPath('data.metrics.today_sales', '320.000');
        $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant, $cashier))->assertOk()
            ->assertJsonPath('data.scope', 'own_shift')
            ->assertJsonPath('data.metrics.today_sales', '20.000')
            ->assertJsonPath('data.metrics.monthly_sales', null);

        // Cashier first (fresh cache): the admin still gets the store view.
        Cache::flush();
        $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant, $cashier))->assertOk()
            ->assertJsonPath('data.metrics.today_sales', '20.000');
        $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant))->assertOk()
            ->assertJsonPath('data.scope', 'store')
            ->assertJsonPath('data.metrics.today_sales', '320.000')
            ->assertJsonPath('data.can_view_financials', true);

        // The cashier's payload is per user: a second cashier never gets the first one's figures.
        $second = $this->createTenantUser($this->tenant, 'cashier');
        $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant, $second))->assertOk()
            ->assertJsonPath('data.metrics.today_sales', '0.000');
    }

    // ------------------------------------------------------------------ treasury summary

    public function test_cashier_treasury_summary_has_own_movement_and_no_balances(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $this->openShift($cashier, now()->subHour());
        $this->sale($cashier, '60.000', 'cash');
        $this->sale($this->adminId(), '900.000', 'cash');

        $response = $this->getJson('/api/v1/treasury/summary', $this->tenantHeaders($this->tenant, $cashier))->assertOk()
            ->assertJsonPath('scope', 'own_shift')
            ->assertJsonPath('balances', null)
            ->assertJsonPath('today.window', 'shift')
            ->assertJsonPath('today.invoices_count', 1)
            ->assertJsonPath('today.sales_total', '60.000');

        $body = (string) $response->getContent();
        foreach (['total_receivable', 'total_payable', 'accounts', 'net_cash', 'total_inflow', 'opening_cash_balance'] as $key) {
            $this->assertStringNotContainsString('"'.$key.'"', $body, "{$key} must not reach a cashier");
        }

        $this->getJson('/api/v1/treasury/summary', $this->tenantHeaders($this->tenant))->assertOk()
            ->assertJsonPath('scope', 'store')
            ->assertJsonPath('balances.total_receivable', '75.000')
            ->assertJsonPath('balances.total_payable', '40.000');
    }

    public function test_cashier_treasury_summary_without_a_shift_is_their_own_day(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $this->sale($cashier, '12.500', 'cash');
        $this->sale($this->adminId(), '900.000', 'cash');

        $this->getJson('/api/v1/treasury/summary', $this->tenantHeaders($this->tenant, $cashier))->assertOk()
            ->assertJsonPath('today.window', 'day')
            ->assertJsonPath('today.sales_total', '12.500')
            ->assertJsonPath('active_shift', null);
    }

    // ------------------------------------------------------------------ expenses

    public function test_journal_reader_sees_only_their_own_expenses(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $mine = $this->expense($cashier->id, '15.000');
        $theirs = $this->expense($this->adminId(), '250.000');
        $headers = $this->tenantHeaders($this->tenant, $cashier);

        $list = $this->getJson('/api/v1/expenses', $headers)->assertOk()
            ->assertJsonPath('summary.count_filtered', 1)
            ->assertJsonPath('summary.total_filtered', '15.000');
        $this->assertSame([$mine], array_column($list->json('data'), 'id'));

        $this->getJson('/api/v1/expenses/'.$mine, $headers)->assertOk();
        $this->getJson('/api/v1/expenses/'.$theirs, $headers)->assertNotFound();

        // Recording an expense needs expenses.manage (unchanged).
        $this->postJson('/api/v1/expenses', [
            'title' => 'أكياس', 'category' => 'packaging', 'cost_center' => 'operational', 'amount' => '5.000',
            'expense_date' => now()->toDateString(), 'payment_method' => 'cash',
        ], $headers)->assertForbidden();

        // A manager (reports.view) sees everyone's.
        $this->getJson('/api/v1/expenses', $this->tenantHeaders($this->tenant))->assertOk()
            ->assertJsonPath('summary.count_filtered', 2);
        $this->getJson('/api/v1/expenses', $this->tenantGuestHeaders($this->tenant))->assertUnauthorized();
    }

    // ------------------------------------------------------------------ users and branches

    public function test_creating_a_non_admin_user_requires_a_branch(): void
    {
        $this->postJson('/api/v1/users', $this->userPayload('01000008811'), $this->tenantHeaders($this->tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['store_ids']);

        $this->postJson('/api/v1/users', $this->userPayload('01000008812', ['store_ids' => []]), $this->tenantHeaders($this->tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['store_ids']);

        $this->postJson('/api/v1/users', $this->userPayload('01000008813', ['store_ids' => [999999]]), $this->tenantHeaders($this->tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['store_ids.0']);

        // An admin needs no branch.
        $this->postJson('/api/v1/users', $this->userPayload('01000008814', ['role' => 'admin']), $this->tenantHeaders($this->tenant))
            ->assertCreated();
    }

    public function test_a_new_cashier_with_a_branch_opens_the_pos_immediately(): void
    {
        $branch = $this->inTenant($this->tenant, fn (): int => (int) Store::query()->create(['name' => 'فرع ٢', 'code' => 'ROLE-B2', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id);

        $response = $this->postJson('/api/v1/users', $this->userPayload('01000008821', ['store_ids' => [$branch]]), $this->tenantHeaders($this->tenant))
            ->assertCreated()
            ->assertJsonPath('data.default_store_id', $branch);
        $userId = (int) $response->json('data.id');

        $this->assertSame([$branch], $this->inTenant($this->tenant, fn (): array => User::findOrFail($userId)->stores()->pluck('stores.id')->map(fn ($id): int => (int) $id)->all()));

        $user = $this->inTenant($this->tenant, fn (): User => User::findOrFail($userId));
        $headers = $this->tenantHeaders($this->tenant, $user, $branch);
        // SELL-01: the first sign-in replaces the password chosen by the admin.
        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => self::PASSWORD, 'password' => 'fixtureNew4567', 'password_confirmation' => 'fixtureNew4567',
        ], $headers)->assertOk();

        $this->getJson('/api/v1/pos/bootstrap', $this->tenantHeaders($this->tenant, $user, $branch))->assertOk();
    }

    public function test_updating_a_user_replaces_their_branches_in_the_same_save(): void
    {
        [$b2, $b3] = $this->inTenant($this->tenant, fn (): array => [
            (int) Store::query()->create(['name' => 'ب2', 'code' => 'ROLE-U2', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id,
            (int) Store::query()->create(['name' => 'ب3', 'code' => 'ROLE-U3', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id,
        ]);
        $user = $this->createTenantUser($this->tenant, 'cashier');
        $base = ['name' => $user->name, 'phone' => $user->phone, 'role' => 'cashier', 'is_active' => true];

        $this->putJson('/api/v1/users/'.$user->id, $base + ['store_ids' => [$b2, $b3], 'default_store_id' => $b3], $this->tenantHeaders($this->tenant))
            ->assertOk()
            ->assertJsonPath('data.default_store_id', $b3);
        $this->assertEqualsCanonicalizing([$b2, $b3], $this->storeIdsOf($user->id));

        $this->putJson('/api/v1/users/'.$user->id, $base + ['store_ids' => []], $this->tenantHeaders($this->tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['store_ids']);
        $this->assertEqualsCanonicalizing([$b2, $b3], $this->storeIdsOf($user->id), 'A refused update keeps the branches.');
    }

    public function test_a_delegate_cannot_assign_a_branch_they_cannot_access(): void
    {
        $foreignBranch = $this->inTenant($this->tenant, fn (): int => (int) Store::query()->create(['name' => 'بعيد', 'code' => 'ROLE-FAR', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id);
        $this->inTenant($this->tenant, fn () => Role::findOrCreate('shift-cashier', 'web')->syncPermissions(['pos.access']));
        $delegate = $this->createTenantUser($this->tenant, null, ['roles.manage', 'pos.access']);

        $this->postJson('/api/v1/users', $this->userPayload('01000008831', ['role' => 'shift-cashier', 'store_ids' => [$foreignBranch]]), $this->tenantHeaders($this->tenant, $delegate))
            ->assertForbidden();
        $this->assertFalse($this->inTenant($this->tenant, fn (): bool => User::query()->where('phone', '01000008831')->exists()));
    }

    public function test_a_user_with_no_branch_gets_a_clear_error_code_and_login_and_me_say_so(): void
    {
        $user = $this->createTenantUser($this->tenant, 'cashier', attributes: ['default_store_id' => null, 'password' => Hash::make(self::PASSWORD)]);
        $headers = $this->tenantHeaders($this->tenant, $user);
        unset($headers['X-Store-Id']);

        $this->getJson('/api/v1/pos/bootstrap', $headers)->assertForbidden()
            ->assertJsonPath('error_code', 'no_store_assigned')
            ->assertJsonPath('message', __('store_scope.no_store_assigned'));

        // An explicit store header is the same answer.
        $this->getJson('/api/v1/pos/bootstrap', $this->tenantHeaders($this->tenant, $user))->assertForbidden()
            ->assertJsonPath('error_code', 'no_store_assigned');

        $this->getJson('/api/v1/auth/me', $headers)->assertOk()
            ->assertJsonPath('data.store', null)
            ->assertJsonPath('data.store_notice.error_code', 'no_store_assigned');

        $login = $this->postJson('/api/v1/auth/login', ['login' => $user->phone, 'password' => self::PASSWORD, 'device_name' => 'test'], ['X-Tenant' => (string) $this->tenant->getTenantKey(), 'Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.store', null)
            ->assertJsonPath('data.store_notice.error_code', 'no_store_assigned');
        $this->assertContains('returns.manage', $login->json('data.user.permissions'));
        $this->assertNotContains('returns.delete', $login->json('data.user.permissions'));

        // A user who has a branch carries no notice.
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $this->getJson('/api/v1/auth/me', $this->tenantHeaders($this->tenant, $cashier))->assertOk()
            ->assertJsonPath('data.store_notice', null);
    }

    public function test_admin_me_payload_lists_the_new_delete_permissions(): void
    {
        $permissions = $this->getJson('/api/v1/auth/me', $this->tenantHeaders($this->tenant))->assertOk()->json('data.user.permissions');

        $this->assertContains('returns.delete', $permissions);
        $this->assertContains('customers.delete', $permissions);
    }

    // ------------------------------------------------------------------ locked matrix

    public function test_cashier_stays_locked_out_of_reports_purchases_users_settings_and_roles(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $headers = $this->tenantHeaders($this->tenant, $cashier);

        foreach (['/api/v1/reports/summary', '/api/v1/reports/items', '/api/v1/purchases', '/api/v1/users', '/api/v1/settings', '/api/v1/roles'] as $uri) {
            $this->getJson($uri, $headers)->assertForbidden();
        }
        $this->postJson('/api/v1/purchases', [], $headers)->assertForbidden();
        $this->postJson('/api/v1/users', $this->userPayload('01000008841', ['store_ids' => [$this->storeId]]), $headers)->assertForbidden();
        $this->postJson('/api/v1/settings', [], $headers)->assertForbidden();
    }

    // ------------------------------------------------------------------ helpers

    private function createReturnAsAdmin(): int
    {
        return (int) $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'return_date' => now()->toDateString(),
            'items' => [['item_id' => $this->itemId, 'quantity' => '3.000', 'unit_price' => '20.000']],
        ], $this->tenantHeaders($this->tenant))->assertCreated()->json('data.id');
    }

    private function customerWithoutBalance(): int
    {
        return $this->inTenant($this->tenant, fn (): int => (int) Customer::create(['name' => 'عميل للحذف', 'phone' => '01000008804', 'price_tier' => 'retail', 'is_active' => true])->id);
    }

    private function stock(): string
    {
        return $this->inTenant($this->tenant, fn (): string => (string) Item::findOrFail($this->itemId)->current_stock);
    }

    private function adminId(): int
    {
        return $this->inTenant($this->tenant, fn (): int => (int) User::role('admin')->orderBy('id')->value('id'));
    }

    private function openShift(User $user, \DateTimeInterface $openedAt): void
    {
        $this->inTenant($this->tenant, fn () => CashShift::query()->create([
            'user_id' => $user->id, 'store_id' => $this->storeId, 'shift_number' => 'SH-'.uniqid(),
            'status' => 'open', 'opened_at' => $openedAt, 'opening_cash_balance' => '500.000',
        ]));
    }

    private function sale(User|int $user, string $net, string $type, ?\DateTimeInterface $at = null): void
    {
        $userId = $user instanceof User ? (int) $user->id : $user;
        $this->inTenant($this->tenant, function () use ($userId, $net, $type, $at): void {
            $stamp = ($at ?? now())->format('Y-m-d H:i:s');
            DB::table('invoices')->insert([
                'invoice_number' => 'ROLE-'.uniqid(), 'customer_id' => $this->customerId, 'user_id' => $userId, 'store_id' => $this->storeId,
                'invoice_date' => app(TenantClock::class)->businessDate(), 'payment_type' => $type, 'status' => 'confirmed',
                'subtotal' => $net, 'net_total' => $net, 'paid_amount' => $type === 'cash' ? $net : '0.000',
                'remaining_amount' => $type === 'cash' ? '0.000' : $net, 'total_cost' => '0.000',
                'created_at' => $stamp, 'updated_at' => $stamp,
            ]);
        });
    }

    private function salesReturn(User $user, string $amount): void
    {
        $this->inTenant($this->tenant, function () use ($user, $amount): void {
            $stamp = now()->format('Y-m-d H:i:s');
            DB::table('returns')->insert([
                'return_number' => 'RET-ROLE-'.uniqid(), 'return_type' => 'sales_return', 'customer_id' => $this->customerId,
                'user_id' => $user->id, 'store_id' => $this->storeId, 'total_amount' => $amount,
                'return_date' => app(TenantClock::class)->businessDate(), 'created_at' => $stamp, 'updated_at' => $stamp,
            ]);
        });
    }

    private function expense(int $userId, string $amount): int
    {
        return $this->inTenant($this->tenant, function () use ($userId, $amount): int {
            $stamp = now()->format('Y-m-d H:i:s');

            return (int) DB::table('expenses')->insertGetId([
                'expense_number' => 'EXP-ROLE-'.uniqid(), 'category' => 'packaging', 'title' => 'أكياس', 'amount' => $amount,
                'expense_date' => now()->toDateString(), 'payment_method' => 'cash', 'user_id' => $userId, 'store_id' => $this->storeId,
                'created_at' => $stamp, 'updated_at' => $stamp,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function userPayload(string $phone, array $overrides = []): array
    {
        return array_merge([
            'name' => 'كاشير '.$phone, 'phone' => $phone, 'password' => self::PASSWORD, 'role' => 'cashier', 'is_active' => true,
        ], $overrides);
    }

    /** @return list<int> */
    private function storeIdsOf(int $userId): array
    {
        return $this->inTenant($this->tenant, fn (): array => User::findOrFail($userId)->stores()->pluck('stores.id')->map(fn ($id): int => (int) $id)->all());
    }

    private function runMigration(string $direction): void
    {
        /** @var Migration $migration */
        $migration = require base_path(self::MIGRATION);
        $migration->{$direction}();
    }
}
