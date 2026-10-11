<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CashShift;
use App\Models\Tenant;
use App\Services\TelegramService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TenantTestCase;

class TelegramOverdueShiftNotificationTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.chat_id' => '12345',
        ]);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    public function test_overdue_shift_alert_shows_the_opening_cash_balance(): void
    {
        $tenant = $this->createTenant();
        $this->openOverdueShift($tenant, 'SHF-TG-01', '750.000');

        $result = $this->inTenant($tenant, fn (): array => app(TelegramService::class)->sendOverdueShiftNotification());

        $this->assertTrue($result['success']);
        Http::assertSent(fn (Request $request) => str_contains((string) $request['text'], '750.00'));
    }

    public function test_overdue_shifts_of_another_tenant_are_not_reported(): void
    {
        $tenant = $this->createTenant();
        $other = $this->createTenant();
        $this->openOverdueShift($other, 'SHF-TG-OTHER', '999.000');

        $result = $this->inTenant($tenant, fn (): array => app(TelegramService::class)->sendOverdueShiftNotification());

        $this->assertTrue($result['success']);
        Http::assertNothingSent();
    }

    private function openOverdueShift(Tenant $tenant, string $number, string $opening): void
    {
        $this->inTenant($tenant, fn () => CashShift::create([
            'user_id' => $this->tenantAdmin($tenant)->id,
            'store_id' => $this->tenantStore($tenant)->id,
            'shift_number' => $number,
            'status' => 'open',
            'opened_at' => now()->subHours(30),
            'opening_cash_balance' => $opening,
        ]));
    }
}
