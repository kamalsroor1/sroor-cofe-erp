<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\DTOs\Billing\RejectManualPaymentDTO;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\Billing\TemporaryAccessOutcome;
use App\Enums\CentralAuditEvent;
use App\Exceptions\Billing\BillingException;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Services\Billing\TemporaryAccessService;
use App\Services\CentralAuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * PRD-03 / ENTI-3.4: a super-admin rejects a pending manual payment with a reason (3-500 chars,
 * validated by RejectBillingPaymentRequest). The invoice stays `pending`, so the shop can submit a
 * corrected receipt. Only a `pending` payment can be rejected (else 409). Central transaction,
 * billing lock order tenant -> payment -> invoice (the tenant id is read unlocked first; it never changes).
 *
 * #232 spec D: a rejection settles the invoice's 48-hour temporary-access window `rejected`
 * (access back to the previous state at once; data and any running paid term untouched). A later
 * receipt for the same invoice never gets a new window (one per invoice).
 */
final class RejectManualPaymentAction
{
    public function __construct(
        private readonly CentralAuditLogger $audit,
        private readonly TemporaryAccessService $temporaryAccess,
    ) {}

    public function execute(RejectManualPaymentDTO $dto, CentralUser $operator): BillingPayment
    {
        $connection = DB::connection((string) (new BillingPayment)->getConnectionName());

        return $connection->transaction(function () use ($dto, $operator): BillingPayment {
            $tenantId = BillingPayment::query()->whereKey($dto->paymentId)->valueOrFail('tenant_id');
            $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->firstOrFail();

            $payment = BillingPayment::query()->whereKey($dto->paymentId)->lockForUpdate()->firstOrFail();

            if ($payment->status !== BillingPaymentStatus::Pending) {
                throw BillingException::paymentNotPending();
            }

            $invoice = BillingInvoice::query()->whereKey($payment->billing_invoice_id)->lockForUpdate()->firstOrFail();

            $payment->status = BillingPaymentStatus::Rejected;
            $payment->rejection_reason = $dto->reason;
            $payment->verified_by = (int) $operator->getKey();
            $payment->verified_at = now();
            $payment->save();

            $this->audit->record(
                CentralAuditEvent::BillingPaymentRejected,
                [
                    'payment_id' => (int) $payment->getKey(),
                    'invoice_id' => (int) $invoice->getKey(),
                    'invoice_number' => $invoice->number,
                    'amount' => $payment->amount,
                    'reason' => $dto->reason,
                ],
                $operator,
                $payment,
                (string) $payment->tenant_id,
            );

            $this->temporaryAccess->settle($tenant, $invoice, TemporaryAccessOutcome::Rejected, $operator, CarbonImmutable::now());

            return $payment;
        });
    }
}
