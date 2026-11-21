<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Exceptions\BusinessRuleException;
use App\Models\CashShift;
use App\Models\StorePosSetting;
use App\Models\Tenant;
use App\Models\TreasuryTransfer;
use App\Services\TreasuryService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\RunsConcurrencyWorkers;
use Tests\TenantTestCase;

/**
 * SHOP-06 / BE-19: real parallel PHP processes (tests/Support/concurrency-worker.php) racing on
 * one branch's treasury and on opening its shift. MySQL/MariaDB only (phpunit.mysql.xml).
 */
#[Group('mysql')]
#[Group('mysql-only')]
#[Group('concurrency')]
final class MysqlShiftAndTreasuryTransferRaceTest extends TenantTestCase
{
    use RunsConcurrencyWorkers;

    private const WORKERS = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $driver = DB::connection($this->centralConnectionName())->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->fail("MySQL-only test run on `{$driver}`. Use: php artisan test -c phpunit.mysql.xml");
        }
    }

    public function test_parallel_transfers_never_overdraw_the_source_account(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->id;
        $adminId = (int) $this->tenantAdmin($tenant)->id;

        // 100.000 in the drawer; six transfers of 40.000 race: exactly two fit.
        $this->inTenant($tenant, fn () => CashShift::query()->create([
            'user_id' => $adminId,
            'store_id' => $storeId,
            'shift_number' => 'SHIFT-RACE-FLOAT',
            'status' => 'open',
            'opened_at' => now(),
            'opening_cash_balance' => '100.000',
        ]));

        $payloads = [];
        for ($i = 1; $i <= self::WORKERS; $i++) {
            $payloads[] = ['from_method' => 'cash', 'to_method' => 'instapay', 'amount' => '40.000', 'store_id' => $storeId, 'user_id' => $adminId];
        }

        $results = $this->runWorkers('treasury_transfer', $tenant, $payloads);

        $succeeded = array_values(array_filter($results, fn (array $r): bool => $r['ok'] === true));
        $failed = array_values(array_filter($results, fn (array $r): bool => $r['ok'] !== true));

        $this->assertCount(2, $succeeded, (string) json_encode($results, JSON_UNESCAPED_UNICODE));
        foreach ($failed as $failure) {
            $this->assertSame(BusinessRuleException::class, $failure['error_class'], (string) json_encode($failure, JSON_UNESCAPED_UNICODE));
        }

        $state = $this->inTenant($tenant, fn (): array => [
            'transfers' => TreasuryTransfer::query()->count(),
            'cash' => app(TreasuryService::class)->getBalances($storeId)['cash']['balance'],
            'instapay' => app(TreasuryService::class)->getBalances($storeId)['instapay']['balance'],
        ]);

        $this->assertSame(['transfers' => 2, 'cash' => '20.000', 'instapay' => '80.000'], $state);
    }

    public function test_parallel_shift_opens_for_one_shared_branch_create_a_single_shift(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->id;
        // SHIFT-01: shared mode, so different cashiers racing still get ONE shift for the branch.
        $this->inTenant($tenant, fn () => StorePosSetting::query()->updateOrCreate(['store_id' => $storeId], ['shift_mode' => 'shared']));

        $payloads = [];
        for ($i = 1; $i <= self::WORKERS; $i++) {
            $payloads[] = ['store_id' => $storeId, 'user_id' => (int) $this->createTenantUser($tenant, 'cashier')->id, 'opening_cash' => '50.000'];
        }

        $this->assertSingleOpenShift($tenant, $storeId, $this->runWorkers('shift_open', $tenant, $payloads));
    }

    public function test_parallel_shift_opens_by_one_cashier_in_a_per_cashier_branch_create_a_single_shift(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->id;
        $adminId = (int) $this->tenantAdmin($tenant)->id;

        $payloads = [];
        for ($i = 1; $i <= self::WORKERS; $i++) {
            $payloads[] = ['store_id' => $storeId, 'user_id' => $adminId, 'opening_cash' => '50.000'];
        }

        $this->assertSingleOpenShift($tenant, $storeId, $this->runWorkers('shift_open', $tenant, $payloads));
        $this->inTenant($tenant, fn () => $this->assertSame('per_cashier', CashShift::query()->where('status', 'open')->value('shift_mode')));
    }

    /**
     * @param  list<array<string, mixed>>  $results
     */
    private function assertSingleOpenShift(Tenant $tenant, int $storeId, array $results): void
    {
        $succeeded = array_values(array_filter($results, fn (array $r): bool => $r['ok'] === true));
        $failed = array_values(array_filter($results, fn (array $r): bool => $r['ok'] !== true));

        $this->assertCount(1, $succeeded, (string) json_encode($results, JSON_UNESCAPED_UNICODE));
        foreach ($failed as $failure) {
            // Refused as "a shift is already open" (409), never a duplicate-key 500 or a deadlock.
            $this->assertSame(BusinessRuleException::class, $failure['error_class'], (string) json_encode($failure, JSON_UNESCAPED_UNICODE));
        }

        $this->inTenant($tenant, fn () => $this->assertSame(1, CashShift::query()->where('status', 'open')->where('store_id', $storeId)->count()));
    }
}
