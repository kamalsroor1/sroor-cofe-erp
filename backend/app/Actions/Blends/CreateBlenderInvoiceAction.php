<?php

declare(strict_types=1);

namespace App\Actions\Blends;

use App\DTOs\Blends\CreateBlenderInvoiceDTO;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\Pos\SaleShiftGuard;
use App\Support\TenantClock;

final class CreateBlenderInvoiceAction
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly TenantClock $tenantClock,
        private readonly SaleShiftGuard $saleShiftGuard,
    ) {}

    /**
     * Issue and confirm sales invoice for custom coffee blend
     */
    public function execute(CreateBlenderInvoiceDTO $dto): Invoice
    {
        // POS-10: a branch that requires a shift cannot sell without an open one.
        $this->saleShiftGuard->assertCanSell($dto->store_id);

        $itemsForInvoice = [];

        foreach ($dto->components as $comp) {
            $kg = bcdiv((string) $comp['grams'], '1000', 4);
            if (bccomp($kg, '0.000', 4) > 0) {
                $itemsForInvoice[] = [
                    'item_id' => (int) $comp['item_id'],
                    'quantity' => $kg,
                    'unit_price' => (string) $comp['unit_price'],
                    'discount_amount' => '0.000',
                ];
            }
        }

        $extraDetails = "درجة التحميص: {$dto->roast_type} | الطحن: {$dto->grind_level} | الوزن: {$dto->target_weight_grams}جم";
        if ($dto->cardamom_grams > 0) {
            $extraDetails .= " | حبهان: {$dto->cardamom_grams}جم";
        }
        if ($dto->notes) {
            $extraDetails .= " | {$dto->notes}";
        }

        $notesStr = "خلطة وتوليفة مخصوصة: {$dto->blend_name} ({$extraDetails})";

        return $this->invoiceService->confirmInvoice([
            'customer_id' => $dto->customer_id,
            'store_id' => $dto->store_id,
            'invoice_date' => $this->tenantClock->businessDate(),
            'items' => $itemsForInvoice,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'paid_amount' => '0.000',
            'discount_type' => 'fixed',
            'discount_value' => '0.000',
            'notes' => $notesStr,
        ]);
    }
}
