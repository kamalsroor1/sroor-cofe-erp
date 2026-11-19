<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Receipts\CreateInvoiceReceiptLinkAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Receipts\CreateInvoiceReceiptLinkRequest;
use App\Http\Resources\Receipts\ReceiptLinkResource;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * PRD-22: POST /api/v1/invoices/{id}/receipt-link (auth, store.active).
 * Permission in the Form Request (403); an invoice of a branch the user cannot reach is a 404.
 */
final class InvoiceReceiptLinkController extends Controller
{
    public function __construct(
        private readonly CreateInvoiceReceiptLinkAction $createLink,
    ) {}

    public function store(CreateInvoiceReceiptLinkRequest $request, int $id): JsonResponse
    {
        $invoice = Invoice::query()->select(['id', 'store_id'])->find($id);
        if (! $invoice instanceof Invoice || ! Gate::forUser($request->user())->allows('view', $invoice)) {
            throw (new ModelNotFoundException)->setModel(Invoice::class, [$id]);
        }

        return (new ReceiptLinkResource($this->createLink->execute($id)))
            ->additional(['success' => true])
            ->response();
    }
}
