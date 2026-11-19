<?php

declare(strict_types=1);

namespace App\Actions\Receipts;

use App\DTOs\Receipts\ReceiptLinkResult;
use App\Exceptions\BusinessRuleException;
use App\Models\Invoice;
use App\Support\PlatformHosts;
use App\Support\Receipts\ReceiptLinkTenantTag;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * PRD-22: a signed, expiring public link to an invoice's receipt, on the shop's own host.
 *
 * The caller has already checked the permission and that the invoice is in a branch the user can
 * reach. The link carries the invoice id, the expiry, the tenant tag and the signature only.
 * A cancelled invoice gets no link (422).
 */
final class CreateInvoiceReceiptLinkAction
{
    public const ROUTE = 'receipts.public';

    public function execute(int $invoiceId): ReceiptLinkResult
    {
        $invoice = Invoice::query()->select(['id', 'status'])->findOrFail($invoiceId);

        if ($invoice->status === 'cancelled') {
            throw BusinessRuleException::make('receipts.errors.cancelled');
        }

        $tenant = tenant();
        if ($tenant === null) {
            throw new RuntimeException('A receipt link needs an initialised tenant.');
        }

        $expiresAt = CarbonImmutable::now()->addDays((int) config('invoices.receipt_links.ttl_days', 30));

        $path = URL::temporarySignedRoute(self::ROUTE, $expiresAt, [
            'invoice' => (int) $invoice->getKey(),
            't' => ReceiptLinkTenantTag::for((string) $tenant->getTenantKey()),
        ], absolute: false);

        return new ReceiptLinkResult($this->origin((string) $tenant->getTenantKey()).$path, $expiresAt);
    }

    /** The shop's own host (first domain record), else its default subdomain host. */
    private function origin(string $tenantKey): string
    {
        $tenant = tenant();
        $host = $tenant !== null && method_exists($tenant, 'domains')
            ? $tenant->domains()->orderBy('id')->value('domain')
            : null;

        $host = is_string($host) && trim($host) !== '' ? $host : PlatformHosts::tenantHost($tenantKey);

        return str_starts_with($host, 'http') ? rtrim($host, '/') : PlatformHosts::origin($host);
    }
}
