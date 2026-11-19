<?php

declare(strict_types=1);

namespace App\Actions\Receipts;

use App\Models\Invoice;
use App\Models\Item;
use App\Services\Branding\TenantBranding;
use App\Services\Settings\TenantSettings;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * PRD-22: the read-only data of the public receipt page, for the signed link holder.
 *
 * Allowlist only: the shop brand, invoice number/date, line names/quantities/prices and the
 * totals. Never the cost, profit, cashier, customer phone or balance, payments or internal ids.
 * A cancelled or deleted invoice is not found (404).
 */
final class GetPublicReceiptAction
{
    public function __construct(
        private readonly TenantBranding $branding,
        private readonly TenantSettings $settings,
    ) {}

    /**
     * @return array{
     *     brand: array{name: string, subtitle: string, phone: string, address: string, logo: string|null, header_lines: list<string>, footer: string},
     *     invoice: array{number: string, date: string, time: string, customer: string|null, subtotal: string, discount: string, extras: list<array{title: string, amount: string}>, net: string, paid: string, remaining: string},
     *     lines: list<array{name: string, quantity: string, unit_price: string, total: string}>,
     *     currency: string
     * }
     */
    public function execute(int $invoiceId): array
    {
        $invoice = Invoice::query()
            ->with(['customer:id,name', 'items.item:id,name', 'additionalExpenses'])
            ->whereKey($invoiceId)
            ->where('status', '!=', 'cancelled')
            ->first();

        if (! $invoice instanceof Invoice) {
            throw (new ModelNotFoundException)->setModel(Invoice::class, [$invoiceId]);
        }

        $brand = $this->branding->get();

        $extras = [];
        $customerExpenses = $invoice->additionalExpenses->whereIn('paid_by', ['customer_account', 'supplier_account']);
        foreach ($customerExpenses as $expense) {
            $extras[] = ['title' => (string) $expense->title, 'amount' => (string) $expense->amount];
        }
        if ($extras === [] && bccomp((string) $invoice->shipping_cost, '0', 3) > 0) {
            $extras[] = ['title' => (string) __('invoices.shipping_delivery'), 'amount' => (string) $invoice->shipping_cost];
        }

        $lines = [];
        foreach ($invoice->items as $line) {
            // A soft-deleted item is not loaded: the line keeps a neutral name.
            $item = $line->getRelation('item');
            $lines[] = [
                'name' => $item instanceof Item ? (string) $item->name : (string) __('invoices.whatsapp_receipt.item_fallback'),
                'quantity' => (string) $line->quantity,
                'unit_price' => (string) $line->unit_price,
                'total' => (string) $line->total_price,
            ];
        }

        return [
            'brand' => [
                'name' => $brand->showName ? $brand->name : '',
                'subtitle' => $brand->showSubtitle ? $brand->subtitle : '',
                'phone' => $brand->phone,
                'address' => $brand->address,
                'logo' => $brand->showLogo ? $this->branding->logoDataUri() : null,
                'header_lines' => $brand->receiptHeaderLines,
                'footer' => $brand->receiptFooterText,
            ],
            'invoice' => [
                'number' => (string) $invoice->invoice_number,
                'date' => $invoice->invoice_date?->format('Y-m-d') ?? '',
                'time' => $invoice->created_at?->format('H:i') ?? '',
                'customer' => $invoice->customer?->name,
                'subtotal' => (string) $invoice->subtotal,
                'discount' => (string) $invoice->discount_amount,
                'extras' => $extras,
                'net' => (string) $invoice->net_total,
                'paid' => (string) $invoice->paid_amount,
                'remaining' => (string) $invoice->remaining_amount,
            ],
            'lines' => $lines,
            'currency' => $this->settings->currencyLabel(),
        ];
    }
}
