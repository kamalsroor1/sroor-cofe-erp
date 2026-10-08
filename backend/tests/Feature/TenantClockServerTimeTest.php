<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\ReorderAssistantService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * W1 hardening note 8: "today" in the treasury summary, the smart-reorder window and the
 * Telegram messages is the TENANT's calendar day (TenantClock, SETG-2), not the server's.
 *
 * Fixed instant: 2026-10-08 18:00 UTC
 *  - server / storage timezone (Africa/Cairo): still 2026-10-08
 *  - tenant timezone Asia/Tokyo (UTC+9):       already 2026-10-09 03:00
 */
final class TenantClockServerTimeTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(CarbonImmutable::parse('2026-10-08 18:00:00', 'UTC'));

        $this->seed(PermissionsSeeder::class);
        Setting::set('timezone', 'Asia/Tokyo');

        $this->store = Store::create(['name' => 'الفرع الرئيسي', 'code' => 'MAIN', 'is_main' => true, 'is_active' => true]);
        $this->admin = User::factory()->create(['phone' => self::ADMIN_PHONE, 'is_active' => true, 'default_store_id' => $this->store->id]);
        $this->admin->assignRole('admin');
        $this->customer = Customer::create(['name' => 'عميل', 'phone' => '01000007301', 'current_balance' => '0.000', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_server_and_tenant_days_differ_at_the_fixed_instant(): void
    {
        $this->assertSame('2026-10-08', now()->toDateString());
    }

    public function test_treasury_summary_uses_the_tenant_day(): void
    {
        $this->invoice('INV-TZ-1', '2026-10-08', '100.000');
        $this->invoice('INV-TZ-2', '2026-10-09', '250.500');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$this->admin->createToken('t')->plainTextToken,
            'X-Store-Id' => (string) $this->store->id,
        ])->getJson('/api/v1/treasury/summary')
            ->assertOk()
            ->assertJsonPath('today.date', '2026-10-09')
            ->assertJsonPath('today.sales_total', 250.5);
    }

    public function test_reorder_analysis_window_ends_on_the_tenant_day(): void
    {
        $item = Item::create([
            'name' => 'بن', 'code' => 'BN-TZ', 'category' => 'coffee_beans', 'unit' => 'كجم',
            'cost_price' => '10.000', 'selling_price' => '20.000', 'price_retail' => '20.000', 'price_wholesale' => '18.000',
            'current_stock' => '100.000', 'min_stock' => '1.000', 'is_active' => true,
        ]);
        $invoice = $this->invoice('INV-TZ-3', '2026-10-09', '28.000');
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'item_id' => $item->id, 'quantity' => '1.400',
            'unit_price' => '20.000', 'cost_price' => '10.000', 'unit_cost' => '10.000',
            'total_price' => '28.000', 'discount_amount' => '0.000', 'tax_amount' => '0.000', 'net_price' => '28.000',
        ]);

        $result = app(ReorderAssistantService::class)->getReorderSuggestions(null, 14, 15);
        $row = collect($result['suggestions'])->firstWhere('id', $item->id);

        $this->assertNotNull($row);
        // SUM() precision is driver-specific (sqlite returns '1.4'): compare as decimals.
        $this->assertSame(0, bccomp((string) $row['analysis_sales'], '1.400', 3));
        $this->assertSame('0.100', $row['daily_consumption']);
    }

    public function test_telegram_daily_summary_and_timestamps_use_the_tenant_clock(): void
    {
        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.chat_id' => '12345',
        ]);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $this->invoice('INV-TZ-4', '2026-10-09', '75.000');

        $telegram = app(TelegramService::class);

        $this->assertTrue($telegram->sendDailySummaryNotification()['success']);
        Http::assertSent(fn (HttpRequest $request): bool => str_contains((string) $request['text'], '2026-10-09')
            && str_contains((string) $request['text'], '75.00')
            && str_contains((string) $request['text'], '03:00 AM'));

        $this->assertTrue($telegram->sendTestNotification()['success']);
        Http::assertSent(fn (HttpRequest $request): bool => str_contains((string) $request['text'], '2026-10-09 03:00 AM'));
    }

    private function invoice(string $number, string $date, string $total): Invoice
    {
        return Invoice::create([
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'invoice_number' => $number,
            'invoice_date' => $date,
            'subtotal' => $total,
            'discount_amount' => '0.000',
            'tax_amount' => '0.000',
            'net_total' => $total,
            'paid_amount' => $total,
            'remaining_amount' => '0.000',
            'payment_type' => 'cash',
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);
    }
}
