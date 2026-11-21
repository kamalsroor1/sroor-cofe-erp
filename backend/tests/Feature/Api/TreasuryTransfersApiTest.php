<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TreasuryTransfer;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\TreasuryService;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * SHOP-06: POST/GET /api/v1/treasury/transfers (TreasuryService::transfer behind
 * FormRequest -> DTO -> Action -> Resource, permission treasury.transfer, store scoping).
 */
final class TreasuryTransfersApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $mainStoreId;

    private int $branchStoreId;

    /** @var array<string, string> */
    private array $adminHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->mainStoreId = (int) $this->tenantStore($this->tenant)->id;
        $this->branchStoreId = $this->inTenant($this->tenant, fn (): int => (int) Store::create([
            'name' => 'فرع التحويلات',
            'code' => 'TRF-BR-01',
            'type' => 'branch',
            'is_main' => false,
            'is_active' => true,
        ])->id);

        $this->tenantAdmin($this->tenant);
        $this->adminHeaders = $this->tenantHeaders($this->tenant);

        // 500.000 cash in the main store's drawer (an open shift's opening float).
        $this->openShiftWithFloat($this->tenant, $this->mainStoreId, '500.000');
    }

    // ------------------------------------------------------------------
    // 200 / 201
    // ------------------------------------------------------------------

    public function test_admin_transfers_money_and_both_balances_move_by_the_exact_amount(): void
    {
        $response = $this->postJson('/api/v1/treasury/transfers', [
            'from_method' => 'cash',
            'to_method' => 'instapay',
            'amount' => '200.250',
            'transfer_fee' => '5.000',
            'notes' => 'توريد للبنك',
        ], $this->adminHeaders);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.from_method', 'cash')
            ->assertJsonPath('data.to_method', 'instapay')
            ->assertJsonPath('data.amount', '200.250')
            ->assertJsonPath('data.transfer_fee', '5.000')
            ->assertJsonPath('data.notes', 'توريد للبنك');
        $this->assertStringStartsWith('TTRF-', (string) $response->json('data.transfer_number'));

        $balances = $this->balances($this->mainStoreId);
        $this->assertSame('294.750', $balances['cash']['balance']);
        $this->assertSame('200.250', $balances['instapay']['balance']);

        $this->inTenant($this->tenant, function (): void {
            $transfer = TreasuryTransfer::query()->sole();
            $this->assertSame($this->mainStoreId, (int) $transfer->store_id);
        });
    }

    public function test_user_granted_treasury_transfer_can_transfer(): void
    {
        $user = $this->createTenantUser($this->tenant, permissions: ['treasury.transfer']);

        $this->postJson('/api/v1/treasury/transfers', [
            'from_method' => 'cash',
            'to_method' => 'e_wallet',
            'amount' => '10',
        ], $this->tenantHeaders($this->tenant, $user))->assertStatus(201)->assertJsonPath('data.amount', '10.000');
    }

    public function test_history_lists_only_the_active_store_newest_first(): void
    {
        $this->openShiftWithFloat($this->tenant, $this->branchStoreId, '300.000');
        $this->serviceTransfer($this->mainStoreId, '10.000');
        $this->serviceTransfer($this->mainStoreId, '20.000');
        $this->serviceTransfer($this->branchStoreId, '30.000');

        $response = $this->getJson('/api/v1/treasury/transfers', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.amount', '20.000')
            ->assertJsonPath('data.1.amount', '10.000');

        $branch = $this->getJson('/api/v1/treasury/transfers', $this->tenantHeaders($this->tenant, store: $this->branchStoreId));
        $branch->assertStatus(200)->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.amount', '30.000');
    }

    public function test_history_filters_by_method(): void
    {
        $this->serviceTransfer($this->mainStoreId, '10.000', 'instapay');
        $this->serviceTransfer($this->mainStoreId, '20.000', 'e_wallet');

        $this->getJson('/api/v1/treasury/transfers?method=e_wallet', $this->adminHeaders)
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.to_method', 'e_wallet');
    }

    // ------------------------------------------------------------------
    // 422
    // ------------------------------------------------------------------

    public function test_insufficient_balance_is_rejected_and_nothing_is_written(): void
    {
        $this->postJson('/api/v1/treasury/transfers', [
            'from_method' => 'cash',
            'to_method' => 'instapay',
            'amount' => '499.000',
            'transfer_fee' => '1.001',
        ], $this->adminHeaders)->assertStatus(422);

        $this->inTenant($this->tenant, fn () => $this->assertSame(0, TreasuryTransfer::query()->count()));
        $this->assertSame('500.000', $this->balances($this->mainStoreId)['cash']['balance']);
    }

    public function test_same_account_is_rejected(): void
    {
        $this->postJson('/api/v1/treasury/transfers', [
            'from_method' => 'cash',
            'to_method' => 'cash',
            'amount' => '10',
        ], $this->adminHeaders)->assertStatus(422)->assertJsonValidationErrors(['to_method']);
    }

    public function test_invalid_payload_is_rejected(): void
    {
        $this->postJson('/api/v1/treasury/transfers', [
            'from_method' => 'gold',
            'amount' => '-5',
            'transfer_fee' => '1.2345',
        ], $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from_method', 'to_method', 'amount', 'transfer_fee']);

        $this->postJson('/api/v1/treasury/transfers', [
            'from_method' => 'cash',
            'to_method' => 'instapay',
            'amount' => '0',
        ], $this->adminHeaders)->assertStatus(422)->assertJsonValidationErrors(['amount']);
    }

    // ------------------------------------------------------------------
    // 401 / 403
    // ------------------------------------------------------------------

    public function test_guest_is_rejected(): void
    {
        $guest = $this->tenantGuestHeaders($this->tenant);

        $this->getJson('/api/v1/treasury/transfers', $guest)->assertStatus(401);
        $this->postJson('/api/v1/treasury/transfers', ['from_method' => 'cash', 'to_method' => 'instapay', 'amount' => '1'], $guest)->assertStatus(401);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        // A cashier sees the daily journal but may not move treasury money nor read its transfers.
        $cashier = $this->createTenantUser($this->tenant, permissions: ['daily_journal.view', 'daily_journal.close_shift', 'pos.access']);
        $headers = $this->tenantHeaders($this->tenant, $cashier);

        $this->postJson('/api/v1/treasury/transfers', ['from_method' => 'cash', 'to_method' => 'instapay', 'amount' => '1'], $headers)->assertStatus(403);
        $this->getJson('/api/v1/treasury/transfers', $headers)->assertStatus(403);

        $this->inTenant($this->tenant, fn () => $this->assertSame(0, TreasuryTransfer::query()->count()));
    }

    public function test_reports_viewer_can_read_history_but_not_transfer(): void
    {
        $viewer = $this->createTenantUser($this->tenant, permissions: ['reports.view']);
        $headers = $this->tenantHeaders($this->tenant, $viewer);

        $this->getJson('/api/v1/treasury/transfers', $headers)->assertStatus(200);
        $this->postJson('/api/v1/treasury/transfers', ['from_method' => 'cash', 'to_method' => 'instapay', 'amount' => '1'], $headers)->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // Store scoping & tenant isolation
    // ------------------------------------------------------------------

    public function test_transfer_from_a_branch_the_user_cannot_access_is_forbidden(): void
    {
        $user = $this->createTenantUser($this->tenant, permissions: ['treasury.transfer']);
        $this->openShiftWithFloat($this->tenant, $this->branchStoreId, '100.000');

        // Header on a foreign branch: refused by the store middleware.
        $this->postJson('/api/v1/treasury/transfers', ['from_method' => 'cash', 'to_method' => 'instapay', 'amount' => '1'], $this->tenantHeaders($this->tenant, $user, $this->branchStoreId))
            ->assertStatus(403);

        // A body store_id of a foreign branch is access-checked too, never used to pick the store.
        $this->postJson('/api/v1/treasury/transfers', ['from_method' => 'cash', 'to_method' => 'instapay', 'amount' => '1', 'store_id' => $this->branchStoreId], $this->tenantHeaders($this->tenant, $user))
            ->assertStatus(403);

        $this->inTenant($this->tenant, fn () => $this->assertSame(0, TreasuryTransfer::query()->count()));
    }

    public function test_transfer_is_booked_on_the_active_branch_and_checks_that_branch_balance(): void
    {
        // The branch drawer is empty: the main store's 500.000 must not fund a branch transfer.
        $this->postJson('/api/v1/treasury/transfers', ['from_method' => 'cash', 'to_method' => 'instapay', 'amount' => '50'], $this->tenantHeaders($this->tenant, store: $this->branchStoreId))
            ->assertStatus(422);

        $this->openShiftWithFloat($this->tenant, $this->branchStoreId, '80.000');
        $this->postJson('/api/v1/treasury/transfers', ['from_method' => 'cash', 'to_method' => 'instapay', 'amount' => '50'], $this->tenantHeaders($this->tenant, store: $this->branchStoreId))
            ->assertStatus(201);

        $this->assertSame('30.000', $this->balances($this->branchStoreId)['cash']['balance']);
        $this->assertSame('500.000', $this->balances($this->mainStoreId)['cash']['balance']);
    }

    public function test_transfers_of_another_tenant_are_invisible(): void
    {
        $other = $this->createTenant();
        $otherStore = (int) $this->tenantStore($other)->id;
        $this->openShiftWithFloat($other, $otherStore, '100.000');
        $this->inTenant($other, function () use ($other, $otherStore): void {
            $this->actingAs($this->tenantAdmin($other));
            app(TreasuryService::class)->transfer(['from_method' => 'cash', 'to_method' => 'instapay', 'amount' => '40.000', 'store_id' => $otherStore]);
        });

        $this->getJson('/api/v1/treasury/transfers', $this->adminHeaders)
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 0);

        $this->inTenant($this->tenant, fn () => $this->assertSame(0, TreasuryTransfer::query()->count()));
    }

    // ------------------------------------------------------------------
    // Rollback
    // ------------------------------------------------------------------

    public function test_a_failure_after_the_transfer_row_is_written_rolls_everything_back(): void
    {
        $this->app->instance(ActivityLogService::class, new class extends ActivityLogService
        {
            public function __construct() {}

            public function log(...$args): never
            {
                throw new RuntimeException('activity log down');
            }
        });

        try {
            $this->withoutExceptionHandling();
            $this->postJson('/api/v1/treasury/transfers', ['from_method' => 'cash', 'to_method' => 'instapay', 'amount' => '100'], $this->adminHeaders);
            $this->fail('The transfer should have failed.');
        } catch (RuntimeException $e) {
            $this->assertSame('activity log down', $e->getMessage());
        }

        $this->inTenant($this->tenant, fn () => $this->assertSame(0, TreasuryTransfer::withTrashed()->count()));
        $this->assertSame('500.000', $this->balances($this->mainStoreId)['cash']['balance']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function openShiftWithFloat(Tenant $tenant, int $storeId, string $float): void
    {
        $this->inTenant($tenant, function () use ($tenant, $storeId, $float): void {
            $adminId = (int) $this->tenantAdmin($tenant)->id;
            CashShift::query()->create([
                'user_id' => $adminId,
                'store_id' => $storeId,
                'shift_number' => 'SHIFT-T-'.$storeId.'-'.uniqid(),
                'status' => 'open',
                'opened_at' => now(),
                'opening_cash_balance' => $float,
            ]);
        });
    }

    private function serviceTransfer(int $storeId, string $amount, string $to = 'instapay'): void
    {
        $this->inTenant($this->tenant, function () use ($storeId, $amount, $to): void {
            /** @var User $admin */
            $admin = $this->tenantAdmin($this->tenant);
            $this->actingAs($admin);
            app(TreasuryService::class)->transfer(['from_method' => 'cash', 'to_method' => $to, 'amount' => $amount, 'store_id' => $storeId]);
        });
    }

    /** @return array<string, mixed> */
    private function balances(int $storeId): array
    {
        return $this->inTenant($this->tenant, fn (): array => app(TreasuryService::class)->getBalances($storeId));
    }
}
