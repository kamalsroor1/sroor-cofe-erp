<?php

declare(strict_types=1);

namespace App\Actions\Returns;

use App\Models\ReturnDocument;
use App\Services\AuditLogService;
use App\Services\ReturnReversalService;
use Illuminate\Support\Facades\DB;

/**
 * BE-02: deleting a return exactly reverses what creating it did, in one transaction.
 *
 * - sales return: its stock leaves the store again (`sales_return_cancel_out`) and the
 *   customer balance is recomputed;
 * - purchase return: its stock goes back (`purchase_return_cancel_in`), the item's weighted
 *   average cost returns to the snapshot taken when the return was made (or the moving-average
 *   formula when something moved the cost since) and the supplier balance is recomputed.
 *
 * The stock/cost reversal lives in ReturnReversalService (shared with ReverseDeletedReturnAction).
 * A return has no treasury effect (the `refund_amount` field is not booked anywhere), so
 * there is no treasury row to reverse. Idempotent: the document is locked and a deleted
 * one is invisible to the lookup, so a second delete is a 404 and cannot reverse twice.
 */
final class DeleteReturnAction
{
    public function __construct(
        private readonly ReturnReversalService $reversal,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function execute(int $returnId): ReturnDocument
    {
        return DB::transaction(function () use ($returnId): ReturnDocument {
            // 1. Lock the document (a soft-deleted one is not found: no double reversal).
            $returnDoc = ReturnDocument::query()->whereKey($returnId)->lockForUpdate()->firstOrFail();

            // 2. Party + items are locked in order and the stock/cost put back.
            $this->reversal->reverseStock($returnDoc);

            $returnDoc->delete();

            // 3. The balance formulas ignore soft-deleted returns, so recomputing is the reversal.
            $this->reversal->refreshBalances($returnDoc);

            $this->auditLogService->log(
                action: $returnDoc->return_type === 'purchase_return' ? 'purchase_return_deleted' : 'sales_return_deleted',
                auditable: $returnDoc,
                oldValues: $returnDoc->toArray(),
                newValues: null,
            );

            return $returnDoc;
        });
    }
}
