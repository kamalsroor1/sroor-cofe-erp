<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Item;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class QuotationService
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    public function generateQuotationNumber(?int $storeId = null): string
    {
        $prefix = 'QUO';
        $today = now()->format('Ymd');
        $countToday = Quotation::whereDate('created_at', now()->toDateString())->count() + 1;
        return sprintf('%s-%s-%04d', $prefix, $today, $countToday);
    }

    public function createQuotation(array $data): Quotation
    {
        return DB::transaction(function () use ($data) {
            $subtotal = '0.000';
            $pricingTier = $data['pricing_tier'] ?? 'wholesale';

            $quotation = Quotation::create([
                'quotation_number' => $data['quotation_number'] ?? $this->generateQuotationNumber($data['store_id'] ?? null),
                'customer_id'      => $data['customer_id'] ?? null,
                'customer_name'    => $data['customer_name'] ?? null,
                'customer_phone'   => $data['customer_phone'] ?? null,
                'user_id'          => Auth::id() ?? 1,
                'store_id'         => $data['store_id'],
                'quotation_date'   => $data['quotation_date'] ?? now()->toDateString(),
                'valid_until'      => $data['valid_until'] ?? now()->addDays(7)->toDateString(),
                'pricing_tier'     => $pricingTier,
                'status'           => $data['status'] ?? 'draft',
                'subtotal'         => '0.000',
                'discount_type'    => $data['discount_type'] ?? 'fixed',
                'discount_value'   => (string)($data['discount_value'] ?? '0.000'),
                'discount_amount'  => '0.000',
                'shipping_cost'    => (string)($data['shipping_cost'] ?? '0.000'),
                'net_total'        => '0.000',
                'notes'            => $data['notes'] ?? null,
                'terms_conditions' => $data['terms_conditions'] ?? "• الأسعار الموضحة بالعرض سارية حتى تاريخ انتهاء الصلاحية.\n• التسليم من مخازن ومقرات سرور كوفي ما لم يُتفق على الشحن.\n• السداد نقدًا أو تحويل بنكي/إلكتروني عند اعتماد أمر التوريد.",
            ]);

            foreach ($data['items'] as $line) {
                $item = Item::where('id', $line['item_id'])->firstOrFail();
                $qty = (string)$line['quantity'];
                $unitPrice = (string)$line['unit_price'];
                $lineDiscount = (string)($line['discount_amount'] ?? '0.000');

                $gross = bcmul($qty, $unitPrice, 3);
                $netLine = bcsub($gross, $lineDiscount, 3);
                $netLine = bccomp($netLine, '0.000', 3) > 0 ? $netLine : '0.000';

                $quotation->items()->create([
                    'item_id'         => $item->id,
                    'quantity'        => $qty,
                    'unit_price'      => $unitPrice,
                    'price_tier'      => $line['price_tier'] ?? $pricingTier,
                    'discount_amount' => $lineDiscount,
                    'total_price'     => $netLine,
                    'notes'           => $line['notes'] ?? null,
                ]);

                $subtotal = bcadd($subtotal, $netLine, 3);
            }

            // Discount calculation
            $discountType = $data['discount_type'] ?? 'fixed';
            $discountVal = (string)($data['discount_value'] ?? '0.000');
            if ($discountType === 'percentage') {
                $discountAmount = bcmul($subtotal, bcdiv($discountVal, '100', 6), 3);
            } else {
                $discountAmount = $discountVal;
            }
            if (bccomp($discountAmount, $subtotal, 3) > 0) {
                $discountAmount = $subtotal;
            }

            $shipping = (string)($data['shipping_cost'] ?? '0.000');
            $afterDiscount = bcsub($subtotal, $discountAmount, 3);
            $netTotal = bcadd($afterDiscount, $shipping, 3);

            $quotation->update([
                'subtotal'        => $subtotal,
                'discount_amount' => $discountAmount,
                'net_total'       => $netTotal,
            ]);

            return $quotation;
        });
    }

    public function updateQuotation(Quotation $quotation, array $data): Quotation
    {
        return DB::transaction(function () use ($quotation, $data) {
            $locked = Quotation::where('id', $quotation->id)->lockForUpdate()->firstOrFail();

            if ($locked->isConverted()) {
                throw new Exception('لا يمكن تعديل عرض سعر تم تحويله بالفعل إلى فاتورة مبيعات.');
            }

            $locked->items()->delete();

            $subtotal = '0.000';
            $pricingTier = $data['pricing_tier'] ?? $locked->pricing_tier;

            foreach ($data['items'] as $line) {
                $item = Item::where('id', $line['item_id'])->firstOrFail();
                $qty = (string)$line['quantity'];
                $unitPrice = (string)$line['unit_price'];
                $lineDiscount = (string)($line['discount_amount'] ?? '0.000');

                $gross = bcmul($qty, $unitPrice, 3);
                $netLine = bcsub($gross, $lineDiscount, 3);
                $netLine = bccomp($netLine, '0.000', 3) > 0 ? $netLine : '0.000';

                $locked->items()->create([
                    'item_id'         => $item->id,
                    'quantity'        => $qty,
                    'unit_price'      => $unitPrice,
                    'price_tier'      => $line['price_tier'] ?? $pricingTier,
                    'discount_amount' => $lineDiscount,
                    'total_price'     => $netLine,
                    'notes'           => $line['notes'] ?? null,
                ]);

                $subtotal = bcadd($subtotal, $netLine, 3);
            }

            $discountType = $data['discount_type'] ?? $locked->discount_type;
            $discountVal = (string)($data['discount_value'] ?? '0.000');
            if ($discountType === 'percentage') {
                $discountAmount = bcmul($subtotal, bcdiv($discountVal, '100', 6), 3);
            } else {
                $discountAmount = $discountVal;
            }
            if (bccomp($discountAmount, $subtotal, 3) > 0) {
                $discountAmount = $subtotal;
            }

            $shipping = (string)($data['shipping_cost'] ?? '0.000');
            $afterDiscount = bcsub($subtotal, $discountAmount, 3);
            $netTotal = bcadd($afterDiscount, $shipping, 3);

            $locked->update([
                'customer_id'      => $data['customer_id'] ?? null,
                'customer_name'    => $data['customer_name'] ?? null,
                'customer_phone'   => $data['customer_phone'] ?? null,
                'store_id'         => $data['store_id'] ?? $locked->store_id,
                'quotation_date'   => $data['quotation_date'] ?? $locked->quotation_date,
                'valid_until'      => $data['valid_until'] ?? $locked->valid_until,
                'pricing_tier'     => $pricingTier,
                'status'           => $data['status'] ?? $locked->status,
                'subtotal'         => $subtotal,
                'discount_type'    => $discountType,
                'discount_value'   => $discountVal,
                'discount_amount'  => $discountAmount,
                'shipping_cost'    => $shipping,
                'net_total'        => $netTotal,
                'notes'            => $data['notes'] ?? $locked->notes,
                'terms_conditions' => $data['terms_conditions'] ?? $locked->terms_conditions,
            ]);

            return $locked;
        });
    }

    public function convertToInvoice(Quotation $quotation, array $paymentOverrides = []): Invoice
    {
        return DB::transaction(function () use ($quotation, $paymentOverrides) {
            $locked = Quotation::where('id', $quotation->id)->lockForUpdate()->firstOrFail();

            if ($locked->isConverted()) {
                throw new Exception("عرض السعر رقم [{$locked->quotation_number}] تم تحويله بالفعل لفاتورة مبيعات.");
            }

            // Ensure customer exists in database
            $customerId = $locked->customer_id;
            if (!$customerId) {
                $custName = trim($locked->customer_name ?: 'عميل عرض سعر ' . $locked->quotation_number);
                $custPhone = trim($locked->customer_phone ?: '');
                $customer = Customer::firstOrCreate(
                    ['name' => $custName],
                    [
                        'phone'           => $custPhone ?: null,
                        'current_balance' => '0.000',
                        'is_active'       => true,
                    ]
                );
                $customerId = $customer->id;
                $locked->update(['customer_id' => $customerId]);
            }

            // Prepare Invoice Items array for confirmInvoice
            $invoiceItems = [];
            foreach ($locked->items as $qItem) {
                $invoiceItems[] = [
                    'item_id'         => $qItem->item_id,
                    'quantity'        => (string)$qItem->quantity,
                    'unit_price'      => (string)$qItem->unit_price,
                    'discount_amount' => (string)$qItem->discount_amount,
                ];
            }

            $paymentType = $paymentOverrides['payment_type'] ?? 'cash';
            $paymentMethod = $paymentOverrides['payment_method'] ?? 'cash';
            $paidAmount = $paymentOverrides['paid_amount'] ?? ($paymentType === 'cash' ? (string)$locked->net_total : '0.000');

            $invoiceData = [
                'customer_id'    => $customerId,
                'store_id'       => $locked->store_id,
                'invoice_date'   => now()->toDateString(),
                'payment_type'   => $paymentType,
                'payment_method' => $paymentMethod,
                'discount_type'  => $locked->discount_type,
                'discount_value' => (string)$locked->discount_value,
                'shipping_cost'  => (string)$locked->shipping_cost,
                'paid_amount'    => $paidAmount,
                'notes'          => "تم إنشاء الفاتورة بتحويل عرض السعر رقم: {$locked->quotation_number}" . ($locked->notes ? "\n" . $locked->notes : ''),
                'items'          => $invoiceItems,
            ];

            $invoice = $this->invoiceService->confirmInvoice($invoiceData);

            $locked->update([
                'status'               => 'converted',
                'converted_invoice_id' => $invoice->id,
            ]);

            return $invoice;
        });
    }

    public function formatWhatsAppMessage(Quotation $quotation): string
    {
        $client = $quotation->target_customer_name;
        $tier = $quotation->pricing_tier_label;
        $validity = $quotation->valid_until ? $quotation->valid_until->format('Y-m-d') : 'ساري حتى إشعار آخر';
        
        $msg = "☕ *عرض أسعار من سرور كوفي*\n";
        $msg .= "═════════════════════\n";
        $msg .= "📄 *رقم العرض:* {$quotation->quotation_number}\n";
        $msg .= "👤 *السيد/السادة:* {$client}\n";
        $msg .= "🏷️ *نوع التسعير:* {$tier}\n";
        $msg .= "📅 *تاريخ العرض:* {$quotation->quotation_date->format('Y-m-d')}\n";
        $msg .= "⏳ *الصلاحية حتى:* {$validity}\n";
        $msg .= "═════════════════════\n";
        $msg .= "*تفاصيل الأصناف والأسعار:*\n";

        foreach ($quotation->items as $i => $it) {
            $num = $i + 1;
            $name = $it->item?->name ?? 'صنف';
            $unit = $it->item?->unit ?: 'كجم';
            $qty = number_format($it->quantity, 2);
            $price = number_format($it->unit_price, 2);
            $tot = number_format($it->total_price, 2);
            $msg .= "{$num}. *{$name}*\n";
            $msg .= "   الكمية: {$qty} {$unit} × {$price} ج.م = *{$tot} ج.م*\n";
        }

        $msg .= "═════════════════════\n";
        $msg .= "💰 *الإجمالي المطلوب:* *" . number_format($quotation->net_total, 2) . " ج.م*\n";
        
        if (bccomp((string)$quotation->shipping_cost, '0.000', 3) > 0) {
            $msg .= "🚚 *مصاريف الشحن والتوصيل:* " . number_format($quotation->shipping_cost, 2) . " ج.م\n";
        }
        
        if ($quotation->notes) {
            $msg .= "\n📝 *ملاحظات:* {$quotation->notes}\n";
        }
        
        $msg .= "\nيسعدنا تواصلكم واعتماد العرض على رقمنا أو زيارة مقراتنا! ✨";
        
        return $msg;
    }
}
