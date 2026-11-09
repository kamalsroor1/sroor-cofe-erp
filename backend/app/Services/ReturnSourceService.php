<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\ReturnDocument;
use App\Models\ReturnItem;
use App\Support\Money\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * BE-01: ties a return to the invoice (sales) or purchase (purchase) it returns.
 *
 * For every source line this knows the quantity still returnable (line quantity minus what
 * non-deleted returns already took, so deleting a return frees it again, BE-02) and the
 * refund price: the line's net value after its share of the document discount. The client
 * only chooses the line and the quantity; item, price and party come from the source.
 *
 * The refund of a line is prorated by quantity and, when a return takes the last of the
 * line, is the remaining value, so the refunds of a line add up to its net value exactly.
 */
final class ReturnSourceService
{
    public const SALES = 'sales_return';

    public const PURCHASE = 'purchase_return';

    /**
     * Step 1 (call first inside the return transaction, BEFORE locking the customer/supplier,
     * the same document -> party -> lines -> items order as cancelInvoice/cancelPurchase):
     * lock the source document of the store.
     *
     * @throws ModelNotFoundException when the document is not in the store (404, never revealed)
     */
    public function lockDocument(string $type, int $sourceId, int $storeId): Invoice|Purchase
    {
        return $type === self::SALES
            ? Invoice::query()->whereKey($sourceId)->where('store_id', $storeId)->lockForUpdate()->firstOrFail()
            : Purchase::query()->whereKey($sourceId)->where('store_id', $storeId)->lockForUpdate()->firstOrFail();
    }

    /**
     * Refuse (422) to cancel, edit or delete an invoice/purchase that still has returns: its
     * returned quantity could otherwise be returned again, or the document disappear under them.
     */
    public static function assertNoLiveReturns(Invoice|Purchase $source): void
    {
        $column = $source instanceof Invoice ? 'invoice_id' : 'purchase_id';

        if (ReturnDocument::query()->where($column, $source->getKey())->exists()) {
            throw ValidationException::withMessages([$column => __('returns.source_has_returns')]);
        }
    }

    /**
     * Step 2 (after the party lock): lock the document lines and turn the requested lines
     * into return lines priced from the source.
     *
     * @param  list<array<string, mixed>>  $requested  each with `<invoice|purchase>_item_id`, `quantity`, optional `item_id`
     * @return list<array{item_id: int, quantity: string, unit_price: string, total_price: string, invoice_item_id: int|null, purchase_item_id: int|null}>
     *
     * @throws ValidationException
     */
    public function resolveLines(string $type, Invoice|Purchase $source, int $partyId, array $requested): array
    {
        $isSales = $type === self::SALES;
        $linkKey = $isSales ? 'invoice_item_id' : 'purchase_item_id';

        $sourceField = $isSales ? 'invoice_id' : 'purchase_id';
        $partyField = $isSales ? 'customer_id' : 'supplier_id';

        if ($source->status !== 'confirmed') {
            throw ValidationException::withMessages([$sourceField => __('returns.source_not_confirmed')]);
        }
        if ((int) $source->{$partyField} !== $partyId) {
            throw ValidationException::withMessages([$partyField => __('returns.party_mismatch')]);
        }

        $lines = $this->lockedLines($source, $isSales);
        $state = $this->lineState($lines->keys()->all(), $linkKey);
        $discountShare = $this->discountRatio($source);

        $resolved = [];
        $seen = [];
        foreach ($requested as $index => $request) {
            $lineId = (int) ($request[$linkKey] ?? 0);
            if (isset($seen[$lineId])) {
                throw ValidationException::withMessages(["items.{$index}.{$linkKey}" => __('returns.line_repeated')]);
            }
            $seen[$lineId] = true;
            $line = $lines->get($lineId);
            if ($line === null) {
                throw ValidationException::withMessages(["items.{$index}.{$linkKey}" => __('returns.line_not_in_source')]);
            }
            if (isset($request['item_id']) && (int) $request['item_id'] !== (int) $line->item_id) {
                throw ValidationException::withMessages(["items.{$index}.item_id" => __('returns.item_mismatch')]);
            }

            $quantity = Decimal::normalize((string) $request['quantity']);
            $lineQuantity = (string) $line->quantity;
            $returned = $state[$lineId]['quantity'] ?? '0.000';
            $remaining = bcsub($lineQuantity, $returned, 3);

            if (bccomp($quantity, $remaining, 3) > 0) {
                throw ValidationException::withMessages(["items.{$index}.quantity" => __('returns.over_return', [
                    'remaining' => $remaining,
                ])]);
            }

            $net = $this->netLineValue((string) $line->total_price, $discountShare);
            $refund = bccomp($quantity, $remaining, 3) === 0
                ? bcsub($net, $state[$lineId]['value'] ?? '0.000', 3)
                : Decimal::round(bcdiv(bcmul($net, $quantity, 6), $lineQuantity, 12));
            if (bccomp($refund, '0.000', 3) < 0) {
                $refund = '0.000';
            }

            $resolved[] = [
                'item_id' => (int) $line->item_id,
                'quantity' => $quantity,
                'unit_price' => $this->unitPrice($net, $lineQuantity),
                'total_price' => $refund,
                'invoice_item_id' => $isSales ? $lineId : null,
                'purchase_item_id' => $isSales ? null : $lineId,
            ];
        }

        return $resolved;
    }

    /**
     * Read-only view for the create-return screen (no locks): every line with what is
     * still returnable and the refund unit price.
     *
     * @return list<array<string, mixed>>
     */
    public function returnableLines(Invoice|Purchase $source): array
    {
        $isSales = $source instanceof Invoice;
        $linkKey = $isSales ? 'invoice_item_id' : 'purchase_item_id';

        $lines = $isSales
            ? InvoiceItem::query()->with('item:id,name,code,unit')->where('invoice_id', $source->id)->orderBy('id')->get()
            : PurchaseItem::query()->with('item:id,name,code,unit')->where('purchase_id', $source->id)->orderBy('id')->get();

        $state = $this->lineState($lines->modelKeys(), $linkKey);
        $discountShare = $this->discountRatio($source);

        $out = [];
        foreach ($lines as $line) {
            $returned = $state[$line->id]['quantity'] ?? '0.000';
            $net = $this->netLineValue((string) $line->total_price, $discountShare);

            $out[] = [
                'id' => (int) $line->id,
                'item_id' => (int) $line->item_id,
                'item_name' => $line->item?->name,
                'item_code' => $line->item?->code,
                'unit' => $line->item?->unit,
                'quantity' => (string) $line->quantity,
                'returned_quantity' => $returned,
                'remaining_quantity' => bcsub((string) $line->quantity, $returned, 3),
                'unit_price' => $this->unitPrice($net, (string) $line->quantity),
            ];
        }

        return $out;
    }

    /**
     * @return Collection<int, InvoiceItem|PurchaseItem> locked, keyed by id
     */
    private function lockedLines(Invoice|Purchase $source, bool $isSales): Collection
    {
        $query = $isSales
            ? InvoiceItem::query()->where('invoice_id', $source->id)
            : PurchaseItem::query()->where('purchase_id', $source->id);

        /** @var Collection<int, InvoiceItem|PurchaseItem> $lines */
        $lines = $query->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        return $lines;
    }

    /**
     * Quantity and value already returned per source line by non-deleted returns.
     *
     * @param  list<int>  $lineIds
     * @return array<int, array{quantity: string, value: string}>
     */
    private function lineState(array $lineIds, string $linkKey): array
    {
        if ($lineIds === []) {
            return [];
        }

        $rows = ReturnItem::query()
            ->join('returns', 'returns.id', '=', 'return_items.return_id')
            ->whereNull('returns.deleted_at')
            ->whereNull('return_items.deleted_at')
            ->whereIn('return_items.'.$linkKey, $lineIds)
            ->get(['return_items.'.$linkKey.' as line_id', 'return_items.quantity', 'return_items.total_price']);

        $state = [];
        foreach ($rows as $row) {
            $id = (int) $row->getAttribute('line_id');
            $state[$id]['quantity'] = bcadd($state[$id]['quantity'] ?? '0.000', (string) $row->quantity, 3);
            $state[$id]['value'] = bcadd($state[$id]['value'] ?? '0.000', (string) $row->total_price, 3);
        }

        return $state;
    }

    /**
     * Share of every line's value that the document discount takes: discount / subtotal.
     */
    private function discountRatio(Model $source): string
    {
        $subtotal = (string) $source->getAttribute('subtotal');
        $discount = (string) $source->getAttribute('discount_amount');

        if (bccomp($subtotal, '0.000', 3) <= 0 || bccomp($discount, '0.000', 3) <= 0) {
            return '0';
        }

        return bcdiv($discount, $subtotal, 12);
    }

    /**
     * The line value after its share of the document discount (line discounts are already
     * in `total_price`; shipping and extra expenses are never refunded).
     */
    private function netLineValue(string $lineTotal, string $discountRatio): string
    {
        $share = Decimal::round(bcmul($lineTotal, $discountRatio, 12));

        return bcsub($lineTotal, $share, 3);
    }

    private function unitPrice(string $net, string $lineQuantity): string
    {
        if (bccomp($lineQuantity, '0.000', 3) <= 0) {
            return '0.000';
        }

        return Decimal::round(bcdiv($net, $lineQuantity, 12));
    }
}
