<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CashShift;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PERF-01: the Telegram alert for a shift closed with a cash difference.
 *
 * It used to be sent INSIDE the shift-close transaction (an HTTP call with a 10 s timeout), so
 * a slow or unreachable Telegram held the shift's row locks and delayed the close. It is now
 * dispatched from ShiftService::closeShift() with afterCommit(): it is queued only once the
 * close has committed (never for a rolled-back close) and the close no longer waits for it.
 *
 * Dispatched from tenant context, so QueueTenancyBootstrapper restores the tenant in the
 * worker. Carries only the shift id; the shift is re-read when the job runs. A Telegram failure
 * is logged and never retried (one alert, no spam).
 */
final class SendShiftDiscrepancyAlertJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $shiftId,
    ) {}

    public function handle(TelegramService $telegram): void
    {
        try {
            $shift = CashShift::query()->with(['user', 'store'])->find($this->shiftId);
            if ($shift === null) {
                return;
            }

            $telegram->sendShiftDiscrepancyNotification($shift);
        } catch (Throwable $e) {
            Log::error('Shift discrepancy notification failed: '.$e->getMessage());
        }
    }
}
