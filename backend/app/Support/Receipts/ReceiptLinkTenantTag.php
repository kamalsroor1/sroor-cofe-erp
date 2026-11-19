<?php

declare(strict_types=1);

namespace App\Support\Receipts;

/**
 * PRD-22: binds a signed receipt link to ONE tenant.
 *
 * Receipt links are signed relative to the host (the shop's host can differ between the app and
 * the browser), and every tenant shares the APP_KEY, so the signature alone would let shop A's
 * link for invoice #5 open shop B's invoice #5 on shop B's host. The signed query carries this
 * opaque tag of the tenant key (keyed HMAC, not the key itself) and the page refuses a mismatch.
 */
final class ReceiptLinkTenantTag
{
    public static function for(string $tenantKey): string
    {
        return substr(hash_hmac('sha256', 'receipt-link|'.$tenantKey, (string) config('app.key')), 0, 20);
    }

    public static function matches(string $tenantKey, mixed $tag): bool
    {
        return is_string($tag) && hash_equals(self::for($tenantKey), $tag);
    }
}
