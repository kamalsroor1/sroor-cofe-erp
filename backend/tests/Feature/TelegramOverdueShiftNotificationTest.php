<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CashShift;
use App\Models\Store;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramOverdueShiftNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_shift_alert_shows_the_opening_cash_balance(): void
    {
        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.chat_id' => '12345',
        ]);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $store = Store::create([
            'name' => 'Main',
            'code' => 'TG-MAIN',
            'type' => 'retail',
            'is_main' => true,
            'is_active' => true,
        ]);
        $user = User::factory()->create(['phone' => self::ADMIN_PHONE, 'is_active' => true]);

        CashShift::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'shift_number' => 'SHF-TG-01',
            'status' => 'open',
            'opened_at' => now()->subHours(30),
            'opening_cash_balance' => '750.000',
        ]);

        $result = app(TelegramService::class)->sendOverdueShiftNotification();

        $this->assertTrue($result['success']);
        Http::assertSent(fn (Request $request) => str_contains((string) $request['text'], '750.00'));
    }
}
