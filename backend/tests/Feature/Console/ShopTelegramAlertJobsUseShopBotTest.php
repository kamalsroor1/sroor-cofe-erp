<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Jobs\CheckLowStockAlertJob;
use App\Jobs\SendAccountLockedAlertJob;
use App\Jobs\SendManagerPinLockedAlertJob;
use App\Jobs\SendShiftDiscrepancyAlertJob;
use App\Models\CashShift;
use App\Models\Item;
use App\Models\Setting;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Services\Settings\SettingSecrets;
use Closure;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TenantTestCase;

/**
 * OPS-24: every Telegram alert that carries a shop's data (shift discrepancy, account locked,
 * manager PIN locked, low stock) is sent only through the shop's OWN bot and chat. With the
 * platform bot configured and the shop unconfigured (or half-configured), nothing is sent.
 */
final class ShopTelegramAlertJobsUseShopBotTest extends TenantTestCase
{
    private const PLATFORM_TOKEN = 'platform-token-ops24';

    private const PLATFORM_CHAT = '-100000';

    private const SHOP_TOKEN = 'shop-token-ops24';

    private const SHOP_CHAT = '-100777';

    protected function setUp(): void
    {
        parent::setUp();

        // The dangerous case: the PLATFORM bot is configured and enabled.
        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => self::PLATFORM_TOKEN,
            'services.telegram.chat_id' => self::PLATFORM_CHAT,
        ]);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    public function test_shift_discrepancy_alert(): void
    {
        $this->assertOnlyShopBot(function (Tenant $tenant): Closure {
            $shiftId = $this->inTenant($tenant, fn (): int => (int) CashShift::create([
                'user_id' => $this->tenantAdmin($tenant)->id,
                'store_id' => $this->tenantStore($tenant)->id,
                'shift_number' => 'SHF-OPS24-DISC',
                'status' => 'closed',
                'opened_at' => now()->subHours(8),
                'closed_at' => now(),
                'opening_cash_balance' => '100.000',
                'expected_cash_balance' => '100.000',
                'actual_cash_balance' => '90.000',
                'cash_difference' => '-10.000',
            ])->id);

            return fn () => app()->call([new SendShiftDiscrepancyAlertJob($shiftId), 'handle']);
        });
    }

    public function test_account_locked_alert(): void
    {
        $this->assertOnlyShopBot(function (Tenant $tenant): Closure {
            $userId = (int) $this->createTenantUser($tenant, 'cashier')->id;

            return fn () => app()->call([new SendAccountLockedAlertJob($userId, 'x@ops24.test', '10.0.0.1', 15), 'handle']);
        });
    }

    public function test_manager_pin_locked_alert(): void
    {
        $this->assertOnlyShopBot(function (Tenant $tenant): Closure {
            $adminId = (int) $this->tenantAdmin($tenant)->id;
            $cashierId = (int) $this->createTenantUser($tenant, 'cashier')->id;

            return fn () => app()->call([new SendManagerPinLockedAlertJob($adminId, $cashierId, 20), 'handle']);
        });
    }

    public function test_low_stock_alert(): void
    {
        $this->assertOnlyShopBot(function (Tenant $tenant): Closure {
            $storeId = (int) $this->tenantStore($tenant)->id;
            $this->inTenant($tenant, function () use ($storeId): void {
                $item = Item::create([
                    'name' => 'OPS24-LOW',
                    'code' => 'OPS24-LOW',
                    'unit' => 'كجم',
                    'cost_price' => '1.000',
                    'selling_price' => '2.000',
                    'current_stock' => '1.000',
                    'min_stock_level' => '10.000',
                    'is_active' => true,
                ]);
                StoreStock::create(['store_id' => $storeId, 'item_id' => $item->id, 'quantity' => '1.000', 'min_stock' => '10.000']);
            });

            return fn () => app()->call([new CheckLowStockAlertJob, 'handle']);
        });
    }

    /**
     * Runs the job (built by $prepare for a fresh tenant) as an unconfigured shop, a token-only
     * shop, a chat-only shop: nothing is sent. Then with the shop's own token + chat: exactly one
     * message, through the shop bot to the shop chat.
     *
     * @param  Closure(Tenant): Closure  $prepare
     */
    private function assertOnlyShopBot(Closure $prepare): void
    {
        $tenant = $this->createTenant();
        $run = $prepare($tenant);

        $this->inTenant($tenant, $run);
        Http::assertNothingSent();

        $this->inTenant($tenant, fn () => Setting::set('telegram_bot_token', SettingSecrets::encrypt(self::SHOP_TOKEN)));
        $this->inTenant($tenant, $run);
        Http::assertNothingSent();

        $this->inTenant($tenant, function (): void {
            Setting::set('telegram_bot_token', '');
            Setting::set('telegram_chat_id', self::SHOP_CHAT);
        });
        $this->inTenant($tenant, $run);
        Http::assertNothingSent();

        $this->inTenant($tenant, fn () => Setting::set('telegram_bot_token', SettingSecrets::encrypt(self::SHOP_TOKEN)));
        $this->inTenant($tenant, $run);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/bot'.self::SHOP_TOKEN.'/')
            && $request['chat_id'] === self::SHOP_CHAT);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), self::PLATFORM_TOKEN)
            || $request['chat_id'] === self::PLATFORM_CHAT);
    }
}
