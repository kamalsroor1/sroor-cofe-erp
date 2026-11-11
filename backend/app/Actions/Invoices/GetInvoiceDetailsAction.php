<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Models\Setting;
use App\Services\Settings\TenantSettings;

final class GetInvoiceDetailsAction
{
    public function __construct(
        private readonly TenantSettings $tenantSettings,
    ) {}

    /**
     * Get Invoice details with relations and WhatsApp sharing URL
     */
    public function execute(int $invoiceId): array
    {
        $invoice = Invoice::with([
            'customer',
            'user:id,name',
            'store:id,name,phone,address',
            'items.item:id,name,code,unit',
            'additionalExpenses',
            'payments.user:id,name',
        ])->findOrFail($invoiceId);

        $customer = $invoice->customer;
        $store = $invoice->store;

        $cleanPhone = preg_replace('/[^0-9]/', '', (string) $customer?->phone);
        if (str_starts_with($cleanPhone, '01')) {
            $cleanPhone = '20'.substr($cleanPhone, 1);
        }

        $companyName = Setting::get('company_name') ?: ($store?->name ?: config('app.name', __('common.app_name')));
        $currency = $this->tenantSettings->currencyLabel();

        $lines = [];
        $lines[] = __('invoices.whatsapp_receipt.title', ['company' => $companyName]);
        $lines[] = '--------------------------------';
        $lines[] = __('invoices.whatsapp_receipt.invoice_number', ['number' => $invoice->invoice_number]);
        $lines[] = __('invoices.whatsapp_receipt.date', ['date' => $invoice->invoice_date?->__toString() ?? '']);
        $lines[] = __('invoices.whatsapp_receipt.customer', ['customer' => $customer?->name ?? __('invoices.whatsapp_receipt.walk_in_customer')]);
        $lines[] = __('invoices.whatsapp_receipt.branch', ['store' => $store?->name ?? __('invoices.whatsapp_receipt.main_branch')]);
        $lines[] = '--------------------------------';
        $lines[] = __('invoices.whatsapp_receipt.items_header');

        foreach ($invoice->items as $idx => $lineItem) {
            $name = $lineItem->item?->name ?? __('invoices.whatsapp_receipt.item_fallback');
            $qty = number_format((float) $lineItem->quantity, 2);
            $price = number_format((float) $lineItem->unit_price, 2);
            $total = number_format((float) $lineItem->total_price, 2);
            $lines[] = ($idx + 1).". {$name} × {$qty} = {$total} {$currency}";
        }

        $lines[] = '--------------------------------';
        $lines[] = __('invoices.whatsapp_receipt.net_total', ['amount' => number_format((float) $invoice->net_total, 2), 'currency' => $currency]);
        $lines[] = __('invoices.whatsapp_receipt.paid', ['amount' => number_format((float) $invoice->paid_amount, 2), 'currency' => $currency]);
        $lines[] = __('invoices.whatsapp_receipt.remaining', ['amount' => number_format((float) $invoice->remaining_amount, 2), 'currency' => $currency]);

        if ($customer) {
            $lines[] = __('invoices.whatsapp_receipt.current_balance', ['amount' => number_format((float) $customer->current_balance, 2), 'currency' => $currency]);
        }

        $lines[] = '--------------------------------';
        $lines[] = __('invoices.whatsapp_receipt.footer', ['company' => $companyName]);

        $messageText = implode("\n", $lines);
        $encodedText = urlencode($messageText);
        $whatsappUrl = ! empty($cleanPhone) ? "https://wa.me/{$cleanPhone}?text={$encodedText}" : "https://wa.me/?text={$encodedText}";

        return [
            'invoice' => $invoice,
            'whatsapp' => [
                'phone' => $customer?->phone,
                'clean_phone' => $cleanPhone,
                'message_text' => $messageText,
                'whatsapp_url' => $whatsappUrl,
            ],
        ];
    }
}
