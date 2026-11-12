<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Requests\PrintDailyJournalRequest;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ReturnDocument;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantClock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

/**
 * ROLE-01 (security audit follow-up): what a user WITHOUT `reports.view` (a cashier) reads through
 * the endpoints next to the dashboard, and the user-form hardening.
 *  - one open shift per store (shared drawer): cashier B selling inside the shift cashier A opened
 *    sees only B's own sales, and the shift block carries no cash figure and not A's name;
 *  - /daily-journal: own documents only, drawer figures null, same keys; the A4 print is refused;
 *  - /shifts: own shifts only; /shifts/current and /auth/me: another user's shift without cash;
 *  - a reports.view reader's payloads are unchanged;
 *  - user update: inaccessible branches (added, removed, previous default) are 403 and nothing
 *    changes; `store_ids: null` keeps the branches.
 */
final class CashierShiftVisibilityApiTest extends TenantTestCase
{
    private const MIGRATION = 'database/migrations/tenant/2026_11_16_100000_seed_returns_and_customers_delete_permissions.php';

    private Tenant $tenant;

    private int $storeId;

    private int $customerId;

    private int $itemId;

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;

        [$this->customerId, $this->itemId, $this->today] = $this->inTenant($this->tenant, function (): array {
            $customer = Customer::create(['name' => 'عميل الوردية', 'phone' => '01000009901', 'price_tier' => 'retail', 'is_active' => true, 'current_balance' => '0.000']);
            $item = Item::create([
                'name' => 'بن محوج', 'code' => 'VIS-BN', 'category' => 'coffee_beans', 'unit' => 'كجم',
                'cost_price' => '300.000', 'weighted_avg_cost' => '300.000', 'selling_price' => '450.000',
                'price_retail' => '450.000', 'price_wholesale' => '420.000',
                'current_stock' => '50.000', 'min_stock_level' => '1.000', 'is_active' => true,
            ]);
            StoreStock::create(['store_id' => $this->storeId, 'item_id' => $item->id, 'quantity' => '50.000']);

            return [(int) $customer->id, (int) $item->id, app(TenantClock::class)->businessDate()];
        });
    }

    // ------------------------------------------------------------------ shared-drawer shift (B)

    public function test_cashier_selling_inside_a_shift_another_cashier_opened_sees_only_their_own_sales(): void
    {
        $opener = $this->createTenantUser($this->tenant, 'cashier');
        $seller = $this->createTenantUser($this->tenant, 'cashier');
        $shiftId = $this->openShift($opener, now()->subHour());

        $this->sale($opener, '100.000', 'cash');
        $this->sale($seller, '30.000', 'cash');
        $this->sale($seller, '12.250', 'credit');
        $this->sale($seller, '999.000', 'cash', now()->subHours(3)); // before the shift opened

        $response = $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant, $seller))->assertOk()
            ->assertJsonPath('data.scope', 'own_shift')
            ->assertJsonPath('data.metrics.today_invoices_count', 2)
            ->assertJsonPath('data.metrics.today_sales', '42.250')
            ->assertJsonPath('data.metrics.cash_sales', '30.000')
            ->assertJsonPath('data.metrics.credit_sales', '12.250')
            ->assertJsonPath('data.active_shift.id', $shiftId)
            ->assertJsonPath('data.active_shift.is_own', false);

        $block = $response->json('data.active_shift');
        $this->assertArrayNotHasKey('user_name', $block, "The opener's name is not shown to another cashier.");
        foreach (['opening_cash_balance', 'current_cash', 'expected_cash_balance'] as $key) {
            $this->assertArrayNotHasKey($key, $block);
        }
        $this->assertEqualsCanonicalizing(['30.000', '12.250'], array_column($response->json('data.recent_invoices'), 'net_total'));

        // The opener sees only their own sale, and their own name.
        $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant, $opener))->assertOk()
            ->assertJsonPath('data.metrics.today_invoices_count', 1)
            ->assertJsonPath('data.metrics.today_sales', '100.000')
            ->assertJsonPath('data.active_shift.is_own', true)
            ->assertJsonPath('data.active_shift.user_name', $opener->name);

        // The treasury summary uses the same window.
        $this->getJson('/api/v1/treasury/summary', $this->tenantHeaders($this->tenant, $seller))->assertOk()
            ->assertJsonPath('today.window', 'shift')
            ->assertJsonPath('today.invoices_count', 2)
            ->assertJsonPath('today.sales_total', '42.250')
            ->assertJsonPath('active_shift.id', $shiftId)
            ->assertJsonPath('active_shift.is_own', false);
    }

    public function test_cashier_dashboard_with_an_inaccessible_store_id_is_forbidden(): void
    {
        $foreign = $this->branch('VIS-FAR');
        $cashier = $this->createTenantUser($this->tenant, 'cashier');

        $this->getJson('/api/v1/dashboard?store_id='.$foreign, $this->tenantHeaders($this->tenant, $cashier))->assertForbidden();
        $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant, $cashier, $foreign))->assertForbidden();
    }

    public function test_cashier_with_a_shift_in_store_a_reading_accessible_store_b_gets_zeros(): void
    {
        $storeB = $this->branch('VIS-B');
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $this->inTenant($this->tenant, fn () => User::findOrFail($cashier->id)->stores()->sync([$this->storeId, $storeB]));

        $this->openShift($cashier, now()->subHour());
        $this->sale($cashier, '80.000', 'cash');

        $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant, $cashier, $storeB))->assertOk()
            ->assertJsonPath('data.scope', 'own_shift')
            ->assertJsonPath('data.active_store.id', $storeB)
            ->assertJsonPath('data.metrics.today_sales', '0.000')
            ->assertJsonPath('data.metrics.today_invoices_count', 0)
            ->assertJsonPath('data.active_shift', null)
            ->assertJsonPath('data.recent_invoices', []);

        // In store A the sale is there.
        $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant, $cashier))->assertOk()
            ->assertJsonPath('data.metrics.today_sales', '80.000');
    }

    // ------------------------------------------------------------------ treasury summary

    public function test_own_shift_treasury_summary_excludes_other_users_expenses_and_returns(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $other = $this->createTenantUser($this->tenant, 'cashier');
        $this->openShift($cashier, now()->subHour());

        $this->expense($cashier, '15.000');
        $this->expense($this->adminId(), '250.000');
        $this->expense($other, '40.000');
        $this->salesReturn($cashier, '5.000');
        $this->salesReturn($other, '7.500');
        $this->salesReturn($this->adminId(), '60.000');

        $this->getJson('/api/v1/treasury/summary', $this->tenantHeaders($this->tenant, $cashier))->assertOk()
            ->assertJsonPath('scope', 'own_shift')
            ->assertJsonPath('today.expenses_total', '15.000')
            ->assertJsonPath('today.expenses_count', 1)
            ->assertJsonPath('today.returns_total', '5.000')
            ->assertJsonPath('today.returns_count', 1)
            ->assertJsonPath('balances', null);
    }

    // ------------------------------------------------------------------ trash purge

    public function test_trash_purge_of_a_return_succeeds_for_a_non_admin_with_the_delete_permissions(): void
    {
        $returnId = (int) $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'return_date' => $this->today,
            'items' => [['item_id' => $this->itemId, 'quantity' => '1.000', 'unit_price' => '20.000']],
        ], $this->tenantHeaders($this->tenant))->assertCreated()->json('data.id');
        $this->deleteJson('/api/v1/returns/'.$returnId, [], $this->tenantHeaders($this->tenant))->assertOk();

        $janitor = $this->createTenantUser($this->tenant, null, ['trash.access', 'returns.manage', 'returns.delete']);

        $this->deleteJson("/api/v1/trash/returns/{$returnId}/force", [], $this->tenantHeaders($this->tenant, $janitor))->assertOk();
        $this->assertFalse($this->inTenant($this->tenant, fn (): bool => ReturnDocument::withTrashed()->whereKey($returnId)->exists()));
    }

    // ------------------------------------------------------------------ migration

    public function test_permissions_migration_on_a_tenant_without_an_admin_role_creates_the_permissions_only(): void
    {
        $names = ['returns.delete', 'customers.delete'];

        $this->inTenant($this->tenant, function () use ($names): void {
            Permission::query()->whereIn('name', $names)->get()->each(fn (Permission $p) => $p->delete());
            Role::findByName('admin')->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->assertFalse(Role::query()->where('name', 'admin')->exists());

            $this->runMigration('up');
            $this->runMigration('up'); // idempotent
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach ($names as $name) {
                $this->assertSame(1, Permission::query()->where('name', $name)->where('guard_name', 'web')->count());
                $this->assertSame(0, Role::query()->whereHas('permissions', fn ($q) => $q->where('name', $name))->count(), "No role receives {$name}.");
            }

            $this->runMigration('down');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->assertSame(0, Permission::query()->whereIn('name', $names)->count());
        });
    }

    // ------------------------------------------------------------------ daily journal

    public function test_cashier_daily_journal_holds_only_their_own_documents_and_no_drawer_figures(): void
    {
        $opener = $this->createTenantUser($this->tenant, 'cashier');
        $seller = $this->createTenantUser($this->tenant, 'cashier');
        $this->openShift($opener, now()->subHour());

        $openerInvoice = $this->sale($opener, '100.000', 'cash');
        $sellerInvoice = $this->sale($seller, '70.000', 'cash');
        $adminInvoice = $this->sale($this->adminId(), '40.000', 'credit');
        $openerExpense = $this->expense($opener, '15.000');
        $adminExpense = $this->expense($this->adminId(), '250.000');

        $admin = $this->getJson('/api/v1/daily-journal', $this->tenantHeaders($this->tenant))->assertOk()
            ->assertJsonPath('data.scope', 'store')
            ->assertJsonPath('data.summary.total_sales', '210.000')
            ->assertJsonPath('data.summary.cash_sales', '170.000')
            ->assertJsonPath('data.summary.total_expenses', '265.000')
            ->assertJsonPath('data.summary.opening_cash_balance', '500.000')
            ->assertJsonPath('data.summary.expected_cash_in_drawer', '405.000')
            ->assertJsonPath('data.active_shift.opening_cash_balance', '500.000')
            ->assertJsonPath('data.active_shift.user_name', $opener->name);
        $this->assertEqualsCanonicalizing([$openerInvoice, $sellerInvoice, $adminInvoice], array_column($admin->json('data.invoices'), 'id'));
        $this->assertEqualsCanonicalizing([$openerExpense, $adminExpense], array_column($admin->json('data.expenses'), 'id'));

        $own = $this->getJson('/api/v1/daily-journal', $this->tenantHeaders($this->tenant, $opener))->assertOk()
            ->assertJsonPath('data.scope', 'own_shift')
            ->assertJsonPath('data.summary.total_sales', '100.000')
            ->assertJsonPath('data.summary.cash_sales', '100.000')
            ->assertJsonPath('data.summary.credit_sales', '0.000')
            ->assertJsonPath('data.summary.total_expenses', '15.000')
            ->assertJsonPath('data.summary.cash_expenses', '15.000')
            ->assertJsonPath('data.summary.net_cash_today', '85.000')
            ->assertJsonPath('data.summary.opening_cash_balance', null)
            ->assertJsonPath('data.summary.expected_cash_in_drawer', null)
            ->assertJsonPath('data.active_shift.opening_cash_balance', null)
            ->assertJsonPath('data.active_shift.is_own', true)
            ->assertJsonPath('data.active_shift.user_name', $opener->name);
        $this->assertSame([$openerInvoice], array_column($own->json('data.invoices'), 'id'));
        $this->assertSame([$openerExpense], array_column($own->json('data.expenses'), 'id'));

        // One shape for the SPA: the same keys in both scopes.
        $this->assertSame(array_keys($admin->json('data')), array_keys($own->json('data')));
        $this->assertSame(array_keys($admin->json('data.summary')), array_keys($own->json('data.summary')));
        $this->assertSame(array_keys($admin->json('data.active_shift')), array_keys($own->json('data.active_shift')));

        // The other cashier: only their sale, and not the opener's name.
        $seller = $this->getJson('/api/v1/daily-journal', $this->tenantHeaders($this->tenant, $seller))->assertOk()
            ->assertJsonPath('data.summary.total_sales', '70.000')
            ->assertJsonPath('data.summary.total_expenses', '0.000')
            ->assertJsonPath('data.active_shift.is_own', false)
            ->assertJsonPath('data.active_shift.user_name', null)
            ->assertJsonPath('data.active_shift.opening_cash_balance', null);
        $this->assertSame([$sellerInvoice], array_column($seller->json('data.invoices'), 'id'));
        $this->assertSame([], $seller->json('data.expenses'));
    }

    public function test_daily_journal_print_is_refused_to_a_cashier_and_served_to_admin(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $manager = $this->createTenantUser($this->tenant, null, ['daily_journal.view', 'reports.view']);
        $admin = $this->tenantAdmin($this->tenant);

        // The A4 print (routes/tenant.php, `daily.journal.print`) authorizes in PrintDailyJournalRequest.
        $authorizes = fn (?User $user): bool => $this->inTenant($this->tenant, function () use ($user): bool {
            $request = PrintDailyJournalRequest::create('/daily-journal/print', 'GET');
            $request->setUserResolver(fn () => $user === null ? null : User::findOrFail($user->id));

            return $request->authorize();
        });

        $this->assertFalse($authorizes($cashier), 'The store-wide A4 journal is refused to a cashier.');
        $this->assertFalse($authorizes(null));
        $this->assertTrue($authorizes($manager));
        $this->assertTrue($authorizes($admin));
    }

    // ------------------------------------------------------------------ shifts

    public function test_cashier_lists_only_their_own_shifts_and_admin_lists_every_shift(): void
    {
        $opener = $this->createTenantUser($this->tenant, 'cashier');
        $seller = $this->createTenantUser($this->tenant, 'cashier');
        $closed = $this->closedShift($this->adminId());
        $open = $this->openShift($opener, now()->subHour());

        $this->getJson('/api/v1/shifts', $this->tenantHeaders($this->tenant, $opener))->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $open)
            ->assertJsonPath('data.0.opening_cash_balance', '500.000');
        $this->getJson('/api/v1/shifts', $this->tenantHeaders($this->tenant, $seller))->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('data', []);

        $all = $this->getJson('/api/v1/shifts', $this->tenantHeaders($this->tenant))->assertOk()->assertJsonPath('meta.total', 2);
        $this->assertEqualsCanonicalizing([$open, $closed], array_column($all->json('data'), 'id'));
        $this->getJson('/api/v1/shifts', $this->tenantGuestHeaders($this->tenant))->assertUnauthorized();
    }

    public function test_current_shift_hides_the_cash_figures_from_a_cashier_who_did_not_open_it(): void
    {
        $opener = $this->createTenantUser($this->tenant, 'cashier');
        $seller = $this->createTenantUser($this->tenant, 'cashier');
        $shiftId = $this->openShift($opener, now()->subHour());
        $this->sale($opener, '100.000', 'cash');

        $seen = $this->getJson('/api/v1/shifts/current', $this->tenantHeaders($this->tenant, $seller))->assertOk()
            ->assertJsonPath('has_active', true)
            ->assertJsonPath('active_shift.id', $shiftId);
        foreach (['opening_cash_balance', 'total_cash_sales', 'expected_cash_balance', 'actual_cash_balance', 'cash_difference'] as $key) {
            $seen->assertJsonPath('active_shift.'.$key, null);
        }
        foreach ((array) $seen->json('metrics') as $key => $value) {
            $this->assertNull($value, "metrics.{$key} must be null for a cashier who did not open the shift");
        }
        $this->assertArrayHasKey('expected_cash_balance', (array) $seen->json('metrics'));

        // The opener (they close it) and a reports.view reader keep every figure.
        foreach ([$opener, null] as $reader) {
            $this->getJson('/api/v1/shifts/current', $this->tenantHeaders($this->tenant, $reader))->assertOk()
                ->assertJsonPath('active_shift.opening_cash_balance', '500.000')
                ->assertJsonPath('metrics.opening_cash_balance', '500.000')
                ->assertJsonPath('metrics.total_cash_sales', '100.000')
                ->assertJsonPath('metrics.expected_cash_balance', '600.000');
        }
    }

    public function test_me_hides_the_opening_float_of_a_shift_the_cashier_did_not_open(): void
    {
        $opener = $this->createTenantUser($this->tenant, 'cashier');
        $seller = $this->createTenantUser($this->tenant, 'cashier');
        $shiftId = $this->openShift($opener, now()->subHour());

        $this->getJson('/api/v1/auth/me', $this->tenantHeaders($this->tenant, $seller))->assertOk()
            ->assertJsonPath('data.active_shift.id', $shiftId)
            ->assertJsonPath('data.active_shift.opening_cash_balance', null);
        $this->getJson('/api/v1/auth/me', $this->tenantHeaders($this->tenant, $opener))->assertOk()
            ->assertJsonPath('data.active_shift.opening_cash_balance', '500.000');
        $this->getJson('/api/v1/auth/me', $this->tenantHeaders($this->tenant))->assertOk()
            ->assertJsonPath('data.active_shift.opening_cash_balance', '500.000');

        // The twin block of /system/context follows the same rule.
        $this->getJson('/api/v1/system/context', $this->tenantHeaders($this->tenant, $seller))->assertOk()
            ->assertJsonPath('data.active_shift.id', $shiftId)
            ->assertJsonPath('data.active_shift.opening_cash_balance', null);
        $this->getJson('/api/v1/system/context', $this->tenantHeaders($this->tenant, $opener))->assertOk()
            ->assertJsonPath('data.active_shift.opening_cash_balance', '500.000');
        $this->getJson('/api/v1/system/context', $this->tenantHeaders($this->tenant))->assertOk()
            ->assertJsonPath('data.active_shift.opening_cash_balance', '500.000');
    }

    // ------------------------------------------------------------------ user form hardening

    public function test_delegate_update_adding_or_removing_an_inaccessible_branch_is_forbidden_and_changes_nothing(): void
    {
        $foreign = $this->branch('VIS-FOREIGN');
        [$delegate, $target] = $this->delegateAndTarget();
        $headers = $this->tenantHeaders($this->tenant, $delegate);
        $base = ['name' => $target->name, 'phone' => $target->phone, 'role' => 'shift-cashier', 'is_active' => true];

        // Adding a branch the delegate cannot access.
        $this->putJson('/api/v1/users/'.$target->id, $base + ['store_ids' => [$this->storeId, $foreign], 'default_store_id' => $this->storeId], $headers)
            ->assertForbidden();
        $this->assertSame([$this->storeId], $this->storeIdsOf($target->id));

        // Removing a branch the delegate cannot access.
        $this->inTenant($this->tenant, fn () => User::findOrFail($target->id)->stores()->sync([$this->storeId, $foreign]));
        $this->putJson('/api/v1/users/'.$target->id, $base + ['store_ids' => [$this->storeId], 'default_store_id' => $this->storeId], $headers)
            ->assertForbidden();
        $this->assertEqualsCanonicalizing([$this->storeId, $foreign], $this->storeIdsOf($target->id));
    }

    public function test_delegate_cannot_move_a_user_off_a_default_branch_the_delegate_cannot_access(): void
    {
        $foreign = $this->branch('VIS-PREV');
        [$delegate, $target] = $this->delegateAndTarget();
        $this->inTenant($this->tenant, function () use ($target, $foreign): void {
            $user = User::findOrFail($target->id);
            $user->forceFill(['default_store_id' => $foreign])->save();
            $user->stores()->sync([$this->storeId, $foreign]);
        });

        $this->putJson('/api/v1/users/'.$target->id, [
            'name' => $target->name, 'phone' => $target->phone, 'role' => 'shift-cashier', 'is_active' => true,
            'default_store_id' => $this->storeId,
        ], $this->tenantHeaders($this->tenant, $delegate))->assertForbidden();

        $this->assertSame($foreign, $this->inTenant($this->tenant, fn (): int => (int) User::findOrFail($target->id)->default_store_id));
    }

    public function test_store_ids_null_on_update_keeps_the_branches(): void
    {
        $b2 = $this->branch('VIS-K2');
        $user = $this->createTenantUser($this->tenant, 'cashier', attributes: ['default_store_id' => $b2]);
        $this->inTenant($this->tenant, fn () => User::findOrFail($user->id)->stores()->sync([$this->storeId, $b2]));

        $this->putJson('/api/v1/users/'.$user->id, [
            'name' => $user->name, 'phone' => $user->phone, 'role' => 'cashier', 'is_active' => true,
            'default_store_id' => $b2, 'store_ids' => null,
        ], $this->tenantHeaders($this->tenant))->assertOk();

        $this->assertEqualsCanonicalizing([$this->storeId, $b2], $this->storeIdsOf($user->id));
    }

    public function test_a_delegate_cannot_create_an_admin_through_a_differently_cased_role_name(): void
    {
        [$delegate] = $this->delegateAndTarget();

        $status = $this->postJson('/api/v1/users', [
            'name' => 'مدير مزيف', 'phone' => '01000009977', 'password' => 'fixtureCashier123', 'role' => 'Admin',
            'is_active' => true, 'store_ids' => [$this->storeId],
        ], $this->tenantHeaders($this->tenant, $delegate))->status();

        $this->assertContains($status, [403, 422]);
        $this->assertFalse($this->inTenant($this->tenant, fn (): bool => User::query()->where('phone', '01000009977')->exists()));
    }

    // ------------------------------------------------------------------ helpers

    /** @return array{0: User, 1: User} a roles.manage delegate of the main store and a user they may edit */
    private function delegateAndTarget(): array
    {
        $this->inTenant($this->tenant, fn () => Role::findOrCreate('shift-cashier', 'web')->syncPermissions(['pos.access']));
        $delegate = $this->createTenantUser($this->tenant, null, ['roles.manage', 'pos.access']);
        $target = $this->createTenantUser($this->tenant, 'shift-cashier');
        $this->inTenant($this->tenant, fn () => User::findOrFail($target->id)->stores()->sync([$this->storeId]));

        return [$delegate, $target];
    }

    private function branch(string $code): int
    {
        return $this->inTenant($this->tenant, fn (): int => (int) Store::query()->create(['name' => 'فرع '.$code, 'code' => $code, 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id);
    }

    private function adminId(): int
    {
        return $this->inTenant($this->tenant, fn (): int => (int) User::role('admin')->orderBy('id')->value('id'));
    }

    /** One open shift per store, as ShiftService allows. */
    private function openShift(User $user, \DateTimeInterface $openedAt): int
    {
        return $this->inTenant($this->tenant, fn (): int => (int) CashShift::query()->create([
            'user_id' => $user->id, 'store_id' => $this->storeId, 'shift_number' => 'SH-'.uniqid(),
            'status' => 'open', 'opened_at' => $openedAt, 'opening_cash_balance' => '500.000',
        ])->id);
    }

    private function closedShift(int $userId): int
    {
        return $this->inTenant($this->tenant, fn (): int => (int) CashShift::query()->create([
            'user_id' => $userId, 'store_id' => $this->storeId, 'shift_number' => 'SH-C-'.uniqid(),
            'status' => 'closed', 'opened_at' => now()->subDay(), 'closed_at' => now()->subDay()->addHours(8),
            'opening_cash_balance' => '200.000', 'expected_cash_balance' => '900.000', 'actual_cash_balance' => '880.000',
            'cash_difference' => '-20.000',
        ])->id);
    }

    /** A confirmed invoice; a cash one also gets the sale voucher (PAY-INV-*) InvoiceService writes. */
    private function sale(User|int $user, string $net, string $type, ?\DateTimeInterface $at = null): int
    {
        $userId = $user instanceof User ? (int) $user->id : $user;

        return $this->inTenant($this->tenant, function () use ($userId, $net, $type, $at): int {
            $stamp = ($at ?? now())->format('Y-m-d H:i:s');
            $invoiceId = (int) DB::table('invoices')->insertGetId([
                'invoice_number' => 'VIS-'.uniqid(), 'customer_id' => $this->customerId, 'user_id' => $userId, 'store_id' => $this->storeId,
                'invoice_date' => $this->today, 'payment_type' => $type, 'status' => 'confirmed',
                'subtotal' => $net, 'net_total' => $net, 'paid_amount' => $type === 'cash' ? $net : '0.000',
                'remaining_amount' => $type === 'cash' ? '0.000' : $net, 'total_cost' => '0.000',
                'created_at' => $stamp, 'updated_at' => $stamp,
            ]);

            if ($type === 'cash') {
                DB::table('payments')->insert([
                    'payment_number' => 'PAY-INV-'.uniqid(), 'customer_id' => $this->customerId, 'invoice_id' => $invoiceId,
                    'user_id' => $userId, 'store_id' => $this->storeId, 'amount' => $net, 'payment_date' => $this->today,
                    'payment_method' => 'cash', 'created_at' => $stamp, 'updated_at' => $stamp,
                ]);
            }

            return $invoiceId;
        });
    }

    private function salesReturn(User|int $user, string $amount): void
    {
        $userId = $user instanceof User ? (int) $user->id : $user;
        $this->inTenant($this->tenant, function () use ($userId, $amount): void {
            $stamp = now()->format('Y-m-d H:i:s');
            DB::table('returns')->insert([
                'return_number' => 'RET-VIS-'.uniqid(), 'return_type' => 'sales_return', 'customer_id' => $this->customerId,
                'user_id' => $userId, 'store_id' => $this->storeId, 'total_amount' => $amount,
                'return_date' => $this->today, 'created_at' => $stamp, 'updated_at' => $stamp,
            ]);
        });
    }

    private function expense(User|int $user, string $amount): int
    {
        $userId = $user instanceof User ? (int) $user->id : $user;

        return $this->inTenant($this->tenant, function () use ($userId, $amount): int {
            $stamp = now()->format('Y-m-d H:i:s');

            return (int) DB::table('expenses')->insertGetId([
                'expense_number' => 'EXP-VIS-'.uniqid(), 'category' => 'packaging', 'title' => 'أكياس', 'amount' => $amount,
                'expense_date' => $this->today, 'payment_method' => 'cash', 'user_id' => $userId, 'store_id' => $this->storeId,
                'created_at' => $stamp, 'updated_at' => $stamp,
            ]);
        });
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
