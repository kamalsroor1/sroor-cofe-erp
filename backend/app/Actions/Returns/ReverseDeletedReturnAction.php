<?php

declare(strict_types=1);

namespace App\Actions\Returns;

use App\Models\Customer;
use App\Models\ReturnDocument;
use App\Models\Supplier;
use App\Services\AuditLogService;
use App\Services\ReturnReversalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * BE-02 follow-up: puts right a return that was soft-deleted BEFORE DeleteReturnAction reversed
 * anything (its stock, average cost and party balance were left as if the return still existed).
 * Same logic as DeleteReturnAction through ReturnReversalService, applied to an already-deleted
 * document, in one transaction.
 *
 * Exactly once: the trashed document is locked and a reversal movement
 * (sales_return_cancel_out / purchase_return_cancel_in) is looked for under that lock, so a
 * second call (or a return that was reversed when it was deleted) changes nothing and
 * reports `reversed => false`.
 *
 * With $dryRun the very same work runs and is then rolled back: the caller learns whether the
 * reversal would succeed (ValidationException = it would make stock negative / an item is gone)
 * and what the party balance would become, while nothing is written.
 */
final class ReverseDeletedReturnAction
{
    public function __construct(
        private readonly ReturnReversalService $reversal,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @return array{reversed: bool, party_balance_before: string|null, party_balance_after: string|null}
     *
     * @throws ValidationException when the reversal would make stock negative or a line's item is gone
     */
    public function execute(int $returnId, bool $dryRun = false): array
    {
        $result = ['reversed' => false, 'party_balance_before' => null, 'party_balance_after' => null];

        try {
            DB::transaction(function () use ($returnId, $dryRun, &$result): void {
                $returnDoc = ReturnDocument::onlyTrashed()->whereKey($returnId)->lockForUpdate()->firstOrFail();

                if ($this->reversal->hasReversalMovement((int) $returnDoc->id)) {
                    return;
                }

                $result['party_balance_before'] = $this->partyBalance($returnDoc);

                $this->reversal->reverseStock($returnDoc);
                $this->reversal->refreshBalances($returnDoc);

                $result['reversed'] = true;
                $result['party_balance_after'] = $this->partyBalance($returnDoc);

                if (! $dryRun) {
                    $this->auditLogService->log(
                        action: $returnDoc->return_type === 'purchase_return' ? 'purchase_return_deletion_reversed' : 'sales_return_deletion_reversed',
                        auditable: $returnDoc,
                        oldValues: null,
                        newValues: ['return_number' => $returnDoc->return_number, 'total_amount' => (string) $returnDoc->total_amount],
                    );
                }

                if ($dryRun) {
                    throw new RuntimeException('dry-run rollback');
                }
            });
        } catch (RuntimeException $e) {
            if (! $dryRun || $e->getMessage() !== 'dry-run rollback') {
                throw $e;
            }
        }

        return $result;
    }

    private function partyBalance(ReturnDocument $returnDoc): ?string
    {
        if ($returnDoc->customer_id !== null) {
            $balance = Customer::query()->whereKey($returnDoc->customer_id)->value('current_balance');
        } elseif ($returnDoc->supplier_id !== null) {
            $balance = Supplier::query()->whereKey($returnDoc->supplier_id)->value('current_balance');
        } else {
            return null;
        }

        return $balance === null ? null : bcadd((string) $balance, '0', 3);
    }
}
