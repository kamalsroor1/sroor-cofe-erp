<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CashShift;
use App\Models\Store;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogServiceShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_shift_records_the_stored_cash_difference(): void
    {
        $store = Store::create([
            'name' => 'Main',
            'code' => 'ALS-MAIN',
            'type' => 'retail',
            'is_main' => true,
            'is_active' => true,
        ]);
        $user = User::factory()->create(['phone' => self::ADMIN_PHONE, 'is_active' => true]);

        $shift = CashShift::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'shift_number' => 'SHF-ALS-01',
            'status' => 'closed',
            'opened_at' => now()->subHour(),
            'closed_at' => now(),
            'opening_cash_balance' => '500.000',
            'expected_cash_balance' => '1500.000',
            'actual_cash_balance' => '1495.000',
            'cash_difference' => '-5.000',
        ]);

        $log = app(ActivityLogService::class)->logShift('shift_closed', $shift->fresh(), 'closed');

        $this->assertSame('500.000', $log->properties['opening_cash_balance']);
        $this->assertSame('1495.000', $log->properties['actual_cash_balance']);
        $this->assertSame('-5.000', $log->properties['difference']);
    }
}
