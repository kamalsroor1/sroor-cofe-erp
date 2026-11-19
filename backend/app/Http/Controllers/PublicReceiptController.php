<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Receipts\GetPublicReceiptAction;
use App\Support\Receipts\ReceiptLinkTenantTag;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * PRD-22: GET /r/{invoice}?expires=&t=&signature= on the shop's host (routes/tenant.php).
 *
 * Public (no session), read-only. The `signed:relative` middleware rejects a tampered or expired
 * link (403); a link of another shop (tenant tag mismatch), a cancelled or a missing invoice is
 * a 404. Never indexed, never cached by shared caches, no referrer leaves the page.
 */
final class PublicReceiptController extends Controller
{
    public function __construct(
        private readonly GetPublicReceiptAction $getReceipt,
    ) {}

    public function __invoke(Request $request, int $invoice): Response
    {
        $tenant = tenant();
        abort_unless($tenant !== null && ReceiptLinkTenantTag::matches((string) $tenant->getTenantKey(), $request->query('t')), 404);

        return response()
            ->view('receipts.public', $this->getReceipt->execute($invoice))
            ->withHeaders([
                'X-Robots-Tag' => 'noindex, nofollow',
                'Cache-Control' => 'private, no-store',
                'Referrer-Policy' => 'no-referrer',
            ]);
    }
}
