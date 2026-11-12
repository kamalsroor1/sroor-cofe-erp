<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CheckOverdueShiftsJob;
use App\Jobs\SendDailySummaryReportJob;
use App\Models\CashShift;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\Settings\TenantSettings;
use App\Services\TelegramService;
use App\Support\TenantClock;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OPS-24: the daily Telegram summary and the "shift open too long" alert, per tenant.
 *
 * They used to be scheduled in the central context, where `invoices` / `cash_shifts` do not
 * exist, so no shop ever received anything. This central command (scheduled every 15 minutes)
 * walks the provisioned tenants, enters each one and, only when due, dispatches a queued job from
 * the tenant context (QueueTenancyBootstrapper restores the tenant in the worker; the queue
 * connection's DB is the central one, like SendShiftDiscrepancyAlertJob).
 *
 *  - summary: once per business day, after that day ended on the tenant's clock (cutoff
 *    included), for the business day that just finished; remembered in
 *    `telegram_daily_summary_last_date`.
 *  - overdue shifts: shifts open longer than `overdue_shift_alert_hours` (default 24), each
 *    alerted ONCE (ids remembered in `telegram_overdue_alerted_shift_ids`).
 *
 * A tenant without Telegram configured is skipped. One tenant failing is logged and counted but
 * never stops the others; the command then exits non-zero so the scheduler's onFailure hook
 * (routes/console.php) reports it.
 */
final class DispatchTenantTelegramAlertsCommand extends Command
{
    public const KEY_LAST_SUMMARY_DATE = 'telegram_daily_summary_last_date';

    public const KEY_ALERTED_SHIFTS = 'telegram_overdue_alerted_shift_ids';

    protected $signature = 'notify:dispatch-tenant-alerts {--tenants=* : Only these tenant ids}';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('console.telegram_alerts.description'));
    }

    public function handle(): int
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $query = Tenant::query()->provisioned()->orderBy('id');
        $only = array_filter((array) $this->option('tenants'), static fn (mixed $id): bool => is_scalar($id) && (string) $id !== '');
        if ($only !== []) {
            $query->whereIn('id', $only);
        }

        $checked = 0;
        $dispatched = 0;
        $failed = 0;

        foreach ($query->get() as $tenant) {
            $checked++;

            try {
                $dispatched += (int) $tenant->run(fn (): int => $this->dispatchFor());
            } catch (Throwable $e) {
                $failed++;
                Log::error('Scheduled Telegram alerts failed for a tenant.', [
                    'tenant_id' => (string) $tenant->getTenantKey(),
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
                $this->error((string) __('console.telegram_alerts.tenant_failed', ['tenant_id' => (string) $tenant->getTenantKey()]));
            } finally {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        $this->info((string) __('console.telegram_alerts.summary', ['checked' => $checked, 'dispatched' => $dispatched, 'failed' => $failed]));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** Runs inside the tenant; returns the number of jobs dispatched. */
    private function dispatchFor(): int
    {
        $telegram = app(TelegramService::class);
        if (! $telegram->isEnabled() || ! $telegram->getBotToken() || ! $telegram->getDefaultChatId()) {
            return 0;
        }

        return $this->dispatchSummary(app(TenantClock::class)) + $this->dispatchOverdueShifts(app(TenantSettings::class));
    }

    private function dispatchSummary(TenantClock $clock): int
    {
        // The business day that just ended is "yesterday" relative to the current business day.
        $finished = CarbonImmutable::parse($clock->today(), $clock->timezone())->subDay()->toDateString();
        $last = Setting::get(self::KEY_LAST_SUMMARY_DATE);

        if ($last !== null && $last >= $finished) {
            return 0;
        }

        // Mark first: a crash after dispatch must not send the same day twice.
        Setting::set(self::KEY_LAST_SUMMARY_DATE, $finished);
        SendDailySummaryReportJob::dispatch($finished);

        return 1;
    }

    private function dispatchOverdueShifts(TenantSettings $settings): int
    {
        $hours = $settings->overdueShiftAlertHours();

        /** @var list<int> $overdueIds */
        $overdueIds = CashShift::query()
            ->where('status', 'open')
            ->where('opened_at', '<=', now()->subHours($hours))
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $alerted = json_decode((string) Setting::get(self::KEY_ALERTED_SHIFTS, '[]'), true);
        $alerted = is_array($alerted) ? array_map('intval', $alerted) : [];

        $fresh = array_values(array_diff($overdueIds, $alerted));

        // Keep only ids still overdue (closed shifts drop out), so the list stays small.
        $remember = json_encode(array_values(array_unique(array_merge(array_intersect($alerted, $overdueIds), $fresh))));
        if ($fresh === []) {
            if ($remember !== json_encode(array_values($alerted))) {
                Setting::set(self::KEY_ALERTED_SHIFTS, $remember);
            }

            return 0;
        }

        Setting::set(self::KEY_ALERTED_SHIFTS, $remember);
        CheckOverdueShiftsJob::dispatch($hours, $fresh);

        return 1;
    }
}
