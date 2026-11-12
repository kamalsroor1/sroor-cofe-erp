<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\DTOs\Tenants\DueLifecycleReminder;
use App\DTOs\Tenants\LifecycleReminderRunResult;
use App\Models\Tenant;
use App\Models\TenantLifecycleReminder;
use App\Support\Notifications\PlatformTelegramMessenger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SELL-02: `tenants:send-lifecycle-reminders`. For every reminder due today
 * (CollectDueLifecycleRemindersAction):
 *
 *  1. claim it in the central `tenant_lifecycle_reminders` table (insertOrIgnore on the UNIQUE
 *     tenant_id + kind + target_date), then, unless `tenant_notified_at` is already set, write
 *     the in-app notification in the tenant's own DB (NotifyTenantOfLifecycleReminderAction,
 *     idempotent per user) and only THEN stamp `tenant_notified_at`. A failed tenant write leaves
 *     the stamp empty (the next run of the day retries); a lost stamp write cannot notify twice
 *     (deterministic notification ids);
 *  2. once all tenants are done, ONE Telegram message to the operator lists every reminder of
 *     this run whose `operator_notified_at` is empty (split into several messages under the
 *     Telegram cap only when the list is long); each message stamps exactly the reminders it
 *     lists, only once Telegram accepted it. Nothing is sent for an empty list. No DB
 *     transaction is open around any HTTP call.
 *
 * A cache lock (onOneServer + withoutOverlapping cover the scheduler; this covers a manual run
 * racing it) makes concurrent runs a no-op. --dry-run reads only: no lock, no row, no
 * notification, no Telegram.
 */
final class SendLifecycleRemindersAction
{
    public const LOCK = 'tenant-lifecycle-reminders:run';

    private const LOCK_SECONDS = 1800;

    private const LINE_LIMIT = 300;

    /** Room left in every message for its header. */
    private const HEADER_RESERVE = 200;

    public function __construct(
        private readonly CollectDueLifecycleRemindersAction $collect,
        private readonly NotifyTenantOfLifecycleReminderAction $notifyTenant,
        private readonly PlatformTelegramMessenger $telegram,
    ) {}

    public function execute(bool $dryRun, ?CarbonImmutable $now = null): LifecycleReminderRunResult
    {
        $now ??= CarbonImmutable::now();

        if ($dryRun) {
            return $this->dryRun($this->collect->execute($now));
        }

        $lock = Cache::lock(self::LOCK, self::LOCK_SECONDS);
        if (! $lock->get()) {
            return new LifecycleReminderRunResult(false, [], LifecycleReminderRunResult::OPERATOR_LOCKED);
        }

        try {
            $due = $this->collect->execute($now);
            $rows = [];
            /** @var array<int, DueLifecycleReminder> $byRowId */
            $byRowId = [];

            foreach ($due as $reminder) {
                [$status, $rowId] = $this->deliverToTenant($reminder, $now);
                $rows[] = $reminder->toArray() + ['status' => $status];
                if ($rowId !== null) {
                    $byRowId[$rowId] = $reminder;
                }
            }

            return new LifecycleReminderRunResult(false, $rows, $this->notifyOperator($byRowId, $now));
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{0: string, 1: int|null} status (sent | already_sent | failed) and the central row id
     */
    private function deliverToTenant(DueLifecycleReminder $reminder, CarbonImmutable $now): array
    {
        try {
            $row = $this->claim($reminder, $now);
        } catch (Throwable $e) {
            Log::error('Lifecycle reminder could not be recorded', ['tenant_id' => $reminder->tenantId, 'kind' => $reminder->key(), 'exception' => $e::class]);

            return ['failed', null];
        }

        if ($row->tenant_notified_at !== null) {
            return ['already_sent', (int) $row->id];
        }

        try {
            $tenant = Tenant::query()->findOrFail($reminder->tenantId);
            $recipients = $this->notifyTenant->execute($tenant, $reminder, $now);
        } catch (Throwable $e) {
            Log::error('Lifecycle reminder notification failed', ['tenant_id' => $reminder->tenantId, 'kind' => $reminder->key(), 'exception' => $e::class]);

            return ['failed', (int) $row->id];
        }

        // Central context again (the tenant action restored it). Only the first writer stamps.
        // A failed stamp is a retry, not an abort: the next run re-writes nothing (fixed
        // notification ids) and stamps; the loop goes on with the next tenant.
        try {
            TenantLifecycleReminder::query()
                ->whereKey($row->id)
                ->whereNull('tenant_notified_at')
                ->update(['tenant_notified_at' => $now, 'recipients' => $recipients, 'updated_at' => $now]);
        } catch (Throwable $e) {
            Log::error('Lifecycle reminder could not be marked as notified', ['tenant_id' => $reminder->tenantId, 'kind' => $reminder->key(), 'exception' => $e::class]);

            return ['failed', (int) $row->id];
        }

        return ['sent', (int) $row->id];
    }

    private function claim(DueLifecycleReminder $reminder, CarbonImmutable $now): TenantLifecycleReminder
    {
        $timestamp = $now->toDateTimeString();

        TenantLifecycleReminder::query()->insertOrIgnore([
            'tenant_id' => $reminder->tenantId,
            'kind' => $reminder->key(),
            'target_date' => $reminder->targetDate,
            'recipients' => 0,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return $this->find($reminder) ?? throw new \RuntimeException('Lifecycle reminder row missing after insert.');
    }

    private function find(DueLifecycleReminder $reminder): ?TenantLifecycleReminder
    {
        return TenantLifecycleReminder::query()
            ->where('tenant_id', $reminder->tenantId)
            ->where('kind', $reminder->key())
            ->whereDate('target_date', $reminder->targetDate)
            ->first();
    }

    /**
     * @param  array<int, DueLifecycleReminder>  $byRowId
     */
    private function notifyOperator(array $byRowId, CarbonImmutable $now): string
    {
        $pendingIds = $byRowId === [] ? [] : TenantLifecycleReminder::query()
            ->whereIn('id', array_keys($byRowId))
            ->whereNull('operator_notified_at')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($pendingIds === []) {
            return LifecycleReminderRunResult::OPERATOR_NOTHING;
        }

        if (! $this->telegram->isConfigured()) {
            return LifecycleReminderRunResult::OPERATOR_NOT_CONFIGURED;
        }

        // Normally ONE message; a long list is split into chunks under the Telegram cap. Each chunk
        // stamps exactly the reminders it lists, and only once Telegram accepted it. The first
        // failed chunk stops the run: it and the rest stay unstamped for the next run.
        foreach ($this->operatorChunks($pendingIds, $byRowId, $now) as [$text, $ids]) {
            if (! $this->telegram->send($text)) {
                return LifecycleReminderRunResult::OPERATOR_FAILED;
            }

            try {
                TenantLifecycleReminder::query()
                    ->whereIn('id', $ids)
                    ->whereNull('operator_notified_at')
                    ->update(['operator_notified_at' => $now, 'updated_at' => $now]);
            } catch (Throwable $e) {
                Log::error('Lifecycle reminders could not be marked as sent to the operator', ['exception' => $e::class]);

                return LifecycleReminderRunResult::OPERATOR_FAILED;
            }
        }

        return LifecycleReminderRunResult::OPERATOR_SENT;
    }

    /**
     * Split the pending reminders into messages of at most CHUNK_LENGTH characters, each with
     * its own header, and return every message with the row ids it actually lists.
     *
     * @param  list<int>  $pendingIds
     * @param  array<int, DueLifecycleReminder>  $byRowId
     * @return list<array{0: string, 1: non-empty-list<int>}>
     */
    private function operatorChunks(array $pendingIds, array $byRowId, CarbonImmutable $now): array
    {
        $date = $now->setTimezone((string) config('app.timezone', 'UTC'))->toDateString();
        $limit = PlatformTelegramMessenger::MAX_LENGTH - self::HEADER_RESERVE;

        /** @var list<list<array{int, string}>> $groups */
        $groups = [];
        $current = [];
        $length = 0;
        foreach ($pendingIds as $id) {
            $line = mb_strimwidth($this->operatorLine($byRowId[$id]), 0, self::LINE_LIMIT, '...');
            $lineLength = mb_strlen($line) + 1;
            if ($current !== [] && $length + $lineLength > $limit) {
                $groups[] = $current;
                $current = [];
                $length = 0;
            }
            $current[] = [$id, $line];
            $length += $lineLength;
        }
        if ($current !== []) {
            $groups[] = $current;
        }

        $chunks = [];
        foreach ($groups as $index => $group) {
            $header = (string) __('console.lifecycle_reminders.telegram.header', [
                'count' => count($group),
                'date' => $date,
                'part' => $index + 1,
                'parts' => count($groups),
            ]);
            $chunks[] = [
                implode("\n", [$header, ...array_column($group, 1)]),
                array_column($group, 0),
            ];
        }

        return $chunks;
    }

    private function operatorLine(DueLifecycleReminder $reminder): string
    {
        $when = match ($reminder->stage()) {
            DueLifecycleReminder::STAGE_TODAY => (string) __('console.lifecycle_reminders.stages.today'),
            DueLifecycleReminder::STAGE_GRACE => (string) __('console.lifecycle_reminders.stages.grace', [
                'date' => (string) $reminder->graceEndsAt?->setTimezone((string) config('app.timezone', 'UTC'))->toDateString(),
            ]),
            default => (string) __('console.lifecycle_reminders.stages.days_left', ['days' => $reminder->daysLeft]),
        };

        return (string) __('console.lifecycle_reminders.telegram.line', [
            'name' => self::oneLine($reminder->tenantName),
            'tenant_id' => $reminder->tenantId,
            'plan' => self::oneLine($reminder->planName) ?: '-',
            'kind' => (string) __('console.lifecycle_reminders.kinds.'.$reminder->kind->value),
            'when' => $when,
            'date' => $reminder->targetDate,
            'phone' => self::oneLine($reminder->ownerPhone) ?: '-',
        ]);
    }

    /**
     * @param  list<DueLifecycleReminder>  $due
     */
    private function dryRun(array $due): LifecycleReminderRunResult
    {
        $rows = [];
        foreach ($due as $reminder) {
            $existing = $this->find($reminder);
            $rows[] = $reminder->toArray() + ['status' => $existing?->tenant_notified_at !== null ? 'already_sent' : 'would_send'];
        }

        return new LifecycleReminderRunResult(true, $rows, LifecycleReminderRunResult::OPERATOR_DRY_RUN);
    }

    /** Shop-provided text on one line, so it cannot forge extra lines in the operator message. */
    private static function oneLine(?string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\p{Cc}\x{2028}\x{2029}]+/u', ' ', (string) $value)));
    }
}
