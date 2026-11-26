<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Billing\BillingPaymentStatus;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\Tenant;
use App\Services\Billing\BillingOperatorNotifier;
use App\Services\Billing\TemporaryAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * #232 spec E: 6 hours (config billing.temporary_access.reminder_hours_before) before an UNSETTLED
 * 48-hour temporary-access window ends, remind the platform operators: a panel notification item
 * and an e-mail (queued after commit), through BillingOperatorNotifier.
 *
 * Due = not settled, not reminded yet, and now in [ends_at - 6h, ends_at). Once per window:
 * `temporary_access_reminder_sent_at` is stamped in the same central transaction as the panel row,
 * under the billing lock order (tenant -> invoice) with the conditions re-checked, so two schedulers
 * or a re-run never remind twice. A window that already ended (scheduler down) gets no late
 * reminder; a settled one gets none. One failing invoice never stops the others. Central only.
 */
final class SendTemporaryAccessRemindersAction
{
    public function __construct(
        private readonly TemporaryAccessService $temporaryAccess,
        private readonly BillingOperatorNotifier $notifier,
    ) {}

    /** @return int how many reminders were sent */
    public function execute(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $horizon = $now->addSeconds($this->temporaryAccess->reminderSeconds());

        $ids = BillingInvoice::query()
            ->whereNotNull('temporary_access_started_at')
            ->whereNull('temporary_access_settled_at')
            ->whereNull('temporary_access_reminder_sent_at')
            ->where('temporary_access_ends_at', '>', $now)
            ->where('temporary_access_ends_at', '<=', $horizon)
            ->orderBy('temporary_access_ends_at')
            ->orderBy('id')
            ->pluck('id');

        $sent = 0;

        foreach ($ids as $id) {
            try {
                if ($this->remind((int) $id, $now, $horizon)) {
                    $sent++;
                }
            } catch (Throwable $e) {
                Log::warning('Temporary-access reminder failed', ['invoice_id' => (int) $id, 'exception' => $e::class]);
            }
        }

        return $sent;
    }

    private function remind(int $invoiceId, CarbonImmutable $now, CarbonImmutable $horizon): bool
    {
        $connection = DB::connection((string) (new BillingInvoice)->getConnectionName());

        return $connection->transaction(function () use ($invoiceId, $now, $horizon): bool {
            $tenantId = BillingInvoice::query()->whereKey($invoiceId)->valueOrFail('tenant_id');
            $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->firstOrFail();
            $invoice = BillingInvoice::query()->whereKey($invoiceId)->lockForUpdate()->firstOrFail();

            $endsAt = $invoice->temporary_access_ends_at;
            $due = $invoice->hasUnsettledTemporaryAccess()
                && $invoice->temporary_access_reminder_sent_at === null
                && $endsAt !== null
                && $now->lessThan($endsAt)
                && ! $horizon->lessThan($endsAt);

            if (! $due) {
                return false;
            }

            $invoice->temporary_access_reminder_sent_at = Carbon::instance($now);
            $invoice->save();

            // The receipt under review now, else the one that opened the window.
            $payment = BillingPayment::query()
                ->where('billing_invoice_id', $invoice->getKey())
                ->where('status', BillingPaymentStatus::Pending->value)
                ->orderByDesc('id')
                ->first()
                ?? BillingPayment::query()->find($invoice->temporary_access_payment_id);

            $this->notifier->temporaryAccessReminder($tenant, $invoice, $payment);

            return true;
        });
    }
}
