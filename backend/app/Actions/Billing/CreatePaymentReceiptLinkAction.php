<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\BillingPayment;
use App\Models\CentralUser;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Super-admin: a short-lived SIGNED link to a payment receipt (never a public URL, never a path).
 * The link is bound to the operator that asked for it and expires after
 * config('billing.receipt_url_minutes') and is SINGLE-USE (random nonce `n`, consumed on first fetch
 * by GetPaymentReceiptFileAction). The BillingReceiptViewed audit is written at FETCH time, not here. A payment without a stored
 * receipt is a 404.
 */
final class CreatePaymentReceiptLinkAction
{
    public const ROUTE = 'api.super_admin.billing.payments.receipt_file';

    /**
     * @return array{url: string, expires_at: string}
     */
    public function execute(int $paymentId, CentralUser $operator): array
    {
        $payment = BillingPayment::query()->findOrFail($paymentId);

        abort_unless($payment->getFirstMedia(BillingPayment::RECEIPT_COLLECTION) !== null, 404);

        $expires = now()->addMinutes(max(1, (int) config('billing.receipt_url_minutes', 5)));

        $url = URL::temporarySignedRoute(self::ROUTE, $expires, [
            'payment' => $payment->getKey(),
            'u' => $operator->getKey(),
            'n' => Str::random(32),
        ]);

        return ['url' => $url, 'expires_at' => $expires->toIso8601String()];
    }
}
