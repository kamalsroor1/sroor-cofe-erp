<?php

declare(strict_types=1);

namespace App\Actions\Returns;

use App\DTOs\Returns\ReturnDocumentDTO;
use App\Models\Invoice;
use App\Models\ReturnDocument;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\Returns\ReturnWindowGuard;
use App\Services\ReturnService;

final class CreateReturnAction
{
    public function __construct(
        private readonly ReturnService $returnService,
        private readonly ReturnWindowGuard $returnWindowGuard,
        private readonly ActivityLogService $activityLogService,
    ) {}

    /**
     * Create return document (sales return or purchase return) atomically.
     *
     * SET-02: a sales return against an invoice must be inside the tenant's return window
     * (ReturnWindowGuard) unless the actor or an approving manager holds returns.override_window.
     * The window depends only on the invoice's business date (not changed once the invoice has
     * returns) and on today's business date, so it is checked before ReturnService opens its
     * retried transaction; an invoice outside the active store is left to ReturnService (404).
     */
    public function execute(ReturnDocumentDTO $dto, ?User $actor = null): ReturnDocument
    {
        $window = $this->checkReturnWindow($dto, $actor);

        // SEC-21 review: the override record is written inside the return's transaction, so a
        // return past its window never exists without it (a failing log rolls the return back).
        $logOverride = $window !== null && $window['overridden']
            ? fn (ReturnDocument $returnDoc) => $this->logWindowOverride($returnDoc, $dto, $window, $actor)
            : null;

        return $this->returnService->createReturn($dto->toArray(), $logOverride);
    }

    /**
     * @param  array{overridden: bool, approved_by: int|null, max_days: int, age_days: int, invoice_date: string}  $window
     */
    private function logWindowOverride(ReturnDocument $returnDoc, ReturnDocumentDTO $dto, array $window, ?User $actor): void
    {
        $this->activityLogService->log(
            module: 'returns',
            action: 'return_window_overridden',
            description: __('returns.activity_window_overridden', [
                'number' => $returnDoc->return_number,
                'age' => $window['age_days'],
                'days' => $window['max_days'],
            ]),
            subject: $returnDoc,
            properties: [
                'invoice_id' => $dto->invoice_id,
                'invoice_date' => $window['invoice_date'],
                'age_days' => $window['age_days'],
                'max_days' => $window['max_days'],
                'approved_by' => $window['approved_by'],
                'via_manager_pin' => $actor === null || $window['approved_by'] !== (int) $actor->id,
            ],
            userId: $actor?->id !== null ? (int) $actor->id : null,
            storeId: $dto->store_id,
        );
    }

    /**
     * @return array{overridden: bool, approved_by: int|null, max_days: int, age_days: int, invoice_date: string}|null
     */
    private function checkReturnWindow(ReturnDocumentDTO $dto, ?User $actor): ?array
    {
        if ($dto->return_type !== 'sales_return' || $dto->invoice_id === null) {
            return null;
        }

        $invoice = Invoice::query()
            ->whereKey($dto->invoice_id)
            ->when($dto->store_id !== null, fn ($query) => $query->where('store_id', $dto->store_id))
            ->first(['id', 'store_id', 'invoice_date', 'created_at']);

        return $invoice instanceof Invoice
            ? $this->returnWindowGuard->check($invoice, $actor, $dto->override)
            : null;
    }
}
