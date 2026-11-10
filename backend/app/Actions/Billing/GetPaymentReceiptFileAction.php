<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\CentralAuditEvent;
use App\Enums\CentralPermission;
use App\Models\BillingPayment;
use App\Models\CentralUser;
use App\Services\CentralAuditLogger;
use App\Support\PlatformSuperAdmin;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Resolves the file behind a validated signed receipt link. The signature is checked by the
 * `signed` middleware; here the operator that the link was issued to must still be an active
 * operator allowed to view billing (a revoked operator's link dies with the account).
 * The link is single-use: its nonce is consumed atomically (Cache::add) on the first fetch, a
 * replay gets 410. The BillingReceiptViewed audit is recorded here, at fetch time.
 */
final class GetPaymentReceiptFileAction
{
    public function __construct(private readonly CentralAuditLogger $audit) {}

    public function execute(int $paymentId, int $operatorId, string $nonce): Media
    {
        $operator = CentralUser::query()->find($operatorId);

        abort_unless($operator instanceof CentralUser && PlatformSuperAdmin::can($operator, CentralPermission::BillingView), 403);

        $payment = BillingPayment::query()->findOrFail($paymentId);

        // The nonce is covered by the URL signature, so it can only be one we minted.
        abort_if($nonce === '', 403);
        $ttl = max(1, (int) config('billing.receipt_url_minutes', 5)) + 5;
        abort_unless(Cache::add('billing:receipt:used:'.$nonce, true, now()->addMinutes($ttl)), 410);

        $media = $payment->getFirstMedia(BillingPayment::RECEIPT_COLLECTION);

        abort_unless($media instanceof Media, 404);

        $this->audit->record(
            CentralAuditEvent::BillingReceiptViewed,
            ['payment_id' => (int) $payment->getKey(), 'invoice_id' => (int) $payment->billing_invoice_id],
            $operator,
            $payment,
            (string) $payment->tenant_id,
        );

        return $media;
    }
}
