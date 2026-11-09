<?php

declare(strict_types=1);

namespace App\Actions\Returns;

use App\Models\Invoice;
use App\Models\Purchase;
use App\Services\ReturnSourceService;

/**
 * BE-01: one source document with its lines and the quantity each can still return.
 * A document of another store is a 404 (never revealed).
 */
final class ShowReturnSourceAction
{
    public function __construct(private readonly ReturnSourceService $returnSourceService) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $type, int $sourceId, int $storeId): array
    {
        if ($type === ReturnSourceService::SALES) {
            $source = Invoice::query()->with('customer:id,name')
                ->whereKey($sourceId)->where('store_id', $storeId)->where('status', 'confirmed')->firstOrFail();

            return [
                'id' => (int) $source->id,
                'number' => $source->invoice_number,
                'date' => $source->invoice_date?->toDateString(),
                'party_id' => (int) $source->customer_id,
                'party_name' => $source->customer?->name,
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
            'lines' => $this->returnSourceService->returnableLines($source),
        ];
    }
}
