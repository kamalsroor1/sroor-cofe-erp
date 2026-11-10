<?php

declare(strict_types=1);

namespace App\Actions\Returns;

use App\Actions\Customers\ResolveWalkInCustomerAction;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Services\ReturnSourceService;

/**
 * BE-01: one source document with its lines and the quantity each can still return.
 * A document of another store is a 404 (never revealed).
 *
 * Sales sources also carry `is_walk_in` (bool, the customer is the generic cash customer) and
 * `cash_refundable_amount` (3-decimal string, never negative): the cash still refundable on the
 * invoice (live payments received minus live PAY-RET/PAY-REF refunds), the BE-10 cap on a
 * cash refund. Both keys are null for purchase sources.
 */
final class ShowReturnSourceAction
{
    public function __construct(
        private readonly ReturnSourceService $returnSourceService,
        private readonly ResolveWalkInCustomerAction $walkInCustomer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $type, int $sourceId, int $storeId): array
    {
        if ($type === ReturnSourceService::SALES) {
            $source = Invoice::query()->with('customer:id,name,phone')
                ->whereKey($sourceId)->where('store_id', $storeId)->where('status', 'confirmed')->firstOrFail();

            $refundable = $this->returnSourceService->cashRefundable([(int) $source->id])[(int) $source->id];

            return [
                'id' => (int) $source->id,
                'number' => $source->invoice_number,
                'date' => $source->invoice_date?->toDateString(),
                'party_id' => (int) $source->customer_id,
                'party_name' => $source->customer?->name,
                'is_walk_in' => $source->customer instanceof Customer && $this->walkInCustomer->isWalkIn($source->customer),
                'cash_refundable_amount' => bccomp($refundable, '0.000', 3) < 0 ? '0.000' : $refundable,
                'lines' => $this->returnSourceService->returnableLines($source),
            ];
        }

        $source = Purchase::query()->with('supplier:id,name')
            ->whereKey($sourceId)->where('store_id', $storeId)->where('status', 'confirmed')->firstOrFail();

        return [
            'id' => (int) $source->id,
            'number' => $source->purchase_number,
            'date' => $source->purchase_date?->toDateString(),
            'party_id' => (int) $source->supplier_id,
            'party_name' => $source->supplier?->name,
            'is_walk_in' => null,
            'cash_refundable_amount' => null,
            'lines' => $this->returnSourceService->returnableLines($source),
        ];
    }
}
