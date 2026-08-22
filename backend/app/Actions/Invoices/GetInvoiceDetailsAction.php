<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\Models\Invoice;

final class GetInvoiceDetailsAction
{
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

        $cleanPhone = preg_replace('/[^0-9]/', '', (string)$customer?->phone);
        if (str_starts_with($cleanPhone, '01')) {
            $cleanPhone = '20' . substr($cleanPhone, 1);
        }

        $lines = [];
        $lines[] = "☕ *فاتورة مبيعات - سرور كوفي ERP*";
        $lines[] = "--------------------------------";
        $lines[] = "📄 *رقم الفاتورة:* " . $invoice->invoice_number;
        $lines[] = "📅 *التاريخ:* " . $invoice->invoice_date;
        $lines[] = "👤 *العميل:* " . ($customer?->name ?? 'عميل نقدي');
        $lines[] = "🏢 *الفرع:* " . ($store?->name ?? 'الفرع الرئيسي');
        $lines[] = "--------------------------------";
        $lines[] = "📦 *تفاصيل الأصناف:*";

        foreach ($invoice->items as $idx => $lineItem) {
            $name = $lineItem->item?->name ?? 'صنف';
            $qty = number_format((float)$lineItem->quantity, 2);
            $price = number_format((float)$lineItem->unit_price, 2);
            $total = number_format((float)$lineItem->total_price, 2);
            $lines[] = ($idx + 1) . ". {$name} × {$qty} = {$total} ج.م";
        }

        $lines[] = "--------------------------------";
        $lines[] = "💵 *الإجمالي الصافي:* " . number_format((float)$invoice->net_total, 2) . " ج.م";
        $lines[] = "✅ *المدفوع:* " . number_format((float)$invoice->paid_amount, 2) . " ج.م";
        $lines[] = "⚠️ *المتبقي:* " . number_format((float)$invoice->remaining_amount, 2) . " ج.م";

        if ($customer) {
            $lines[] = "📊 *إجمالي رصيد الحساب الحالي:* " . number_format((float)$customer->current_balance, 2) . " ج.م";
        }

        $lines[] = "--------------------------------";
        $lines[] = "شكراً لتعاملكم مع سرور كوفي لتوريدات خامات مطاحن البن ☕";

        $messageText = implode("\n", $lines);
        $encodedText = urlencode($messageText);
        $whatsappUrl = !empty($cleanPhone) ? "https://wa.me/{$cleanPhone}?text={$encodedText}" : "https://wa.me/?text={$encodedText}";

        return [
            'invoice'  => $invoice,
            'whatsapp' => [
                'phone'        => $customer?->phone,
                'clean_phone'  => $cleanPhone,
                'message_text' => $messageText,
                'whatsapp_url' => $whatsappUrl,
            ],
        ];
    }
}
