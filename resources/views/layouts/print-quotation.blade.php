<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>عرض أسعار - {{ $quotation->quotation_number }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@600;700;800;900&family=Tajawal:wght@500;700;900&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4;
            margin: 10mm 12mm;
        }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0 !important; background: #fff !important; }
            .container { box-shadow: none !important; border: none !important; padding: 0 !important; width: 100% !important; max-width: 100% !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color: #000 !important; }
            .table th { background: #000 !important; color: #fff !important; }
        }
        body {
            font-family: 'Cairo', 'Tajawal', sans-serif;
            color: #000000;
            background: #f1f5f9;
            padding: 20px;
            font-size: 13.5px;
            font-weight: 700;
            line-height: 1.4;
        }
        .container {
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
            padding: 25px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            border: 1px solid #cbd5e1;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #000;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .brand-title {
            margin: 0;
            color: #000;
            font-size: 26px;
            font-weight: 900;
            letter-spacing: -0.5px;
        }
        .brand-subtitle {
            margin: 4px 0 0 0;
            color: #000;
            font-size: 14px;
            font-weight: 800;
        }
        .quotation-meta {
            text-align: left;
        }
        .quotation-title {
            margin: 0;
            font-size: 22px;
            font-weight: 900;
            color: #000;
        }
        .quotation-num {
            margin: 3px 0;
            font-weight: 900;
            font-size: 14px;
            color: #000;
            font-family: monospace;
        }
        .quotation-date {
            margin: 2px 0;
            font-size: 12.5px;
            font-weight: 700;
            color: #000;
        }
        .tier-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            border: 1.5px solid #000;
            font-size: 12px;
            font-weight: 900;
            margin-top: 4px;
            background: #f8fafc;
        }
        .customer-card {
            background: #ffffff;
            padding: 10px 14px;
            border-radius: 6px;
            border: 2px solid #000;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
        }
        .table th {
            background: #000000;
            color: #ffffff;
            padding: 8px 10px;
            text-align: right;
            font-size: 12.5px;
            font-weight: 900;
            border: 1px solid #000;
        }
        .table td {
            padding: 7px 10px;
            border: 1px solid #000000;
            font-size: 12.5px;
            font-weight: 800;
            color: #000;
        }
        .table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .summary-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-top: 15px;
        }
        .terms-box {
            flex: 1;
            padding: 10px 14px;
            border: 1.5px solid #000;
            border-radius: 6px;
            background: #fafafa;
        }
        .terms-title {
            font-size: 13px;
            font-weight: 900;
            margin-bottom: 6px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px;
        }
        .terms-content {
            font-size: 11.5px;
            font-weight: 700;
            line-height: 1.6;
            white-space: pre-line;
            color: #222;
        }
        .summary-box {
            width: 250px;
            border: 2px solid #000;
            border-radius: 6px;
            overflow: hidden;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 12px;
            border-bottom: 1px solid #ddd;
            font-size: 12.5px;
            font-weight: 800;
        }
        .summary-row.total {
            border-bottom: none;
            background: #000;
            color: #fff !important;
            font-size: 15px;
            font-weight: 900;
        }
        .summary-row.total span {
            color: #fff !important;
        }
        .footer {
            margin-top: 25px;
            padding-top: 12px;
            border-top: 2px solid #000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            font-weight: 800;
        }
        .actions-bar {
            max-width: 210mm;
            margin: 0 auto 15px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.9; }
        .btn-print { background: #0f172a; color: #fff; }
        .btn-back { background: #e2e8f0; color: #1e293b; }
        .btn-wa { background: #16a34a; color: #fff; }
    </style>
</head>
<body>

    <!-- Top Action Bar (No Print) -->
    <div class="actions-bar no-print">
        <div style="display: flex; gap: 8px;">
            <a href="{{ route('quotations.index') }}" class="btn btn-back">
                <span>← رجوع لعروض الأسعار</span>
            </a>
            <button onclick="window.print()" class="btn btn-print">
                <span>🖨️ طباعة العرض (Ctrl + P)</span>
            </button>
        </div>

        <div>
            @php
                $service = app(\App\Services\QuotationService::class);
                $rawMsg = $service->formatWhatsAppMessage($quotation);
                $encoded = urlencode($rawMsg);
                $phone = preg_replace('/[^0-9]/', '', $quotation->target_customer_phone ?? '');
                if (str_starts_with($phone, '01')) { $phone = '2' . $phone; }
                $waUrl = $phone ? "https://api.whatsapp.com/send?phone={$phone}&text={$encoded}" : "https://api.whatsapp.com/send?text={$encoded}";
            @endphp
            <a href="{{ $waUrl }}" target="_blank" class="btn btn-wa">
                <span>📲 مشاركة العرض على واتساب</span>
            </a>
        </div>
    </div>

    <!-- Printable Container -->
    <div class="container">
        
        <!-- Header -->
        <div class="header">
            <div>
                <h1 class="brand-title">سرور كوفي - تجارة وتوزيع البن</h1>
                <p class="brand-subtitle">أجود أنواع البن والمحوجات والمواد الخام</p>
                <div class="tier-badge">
                    @if($quotation->pricing_tier === 'wholesale')
                        🏪 نوع العرض: أسعار بيع جملة تجارية خاصة
                    @else
                        🏷️ نوع العرض: أسعار بيع قطاعي معتمدة
                    @endif
                </div>
            </div>
            <div class="quotation-meta">
                <h2 class="quotation-title">عرض أسعار (Quotation)</h2>
                <div class="quotation-num">رقم: {{ $quotation->quotation_number }}</div>
                <div class="quotation-date">تاريخ العرض: {{ $quotation->quotation_date->format('Y/m/d') }}</div>
                @if($quotation->valid_until)
                <div class="quotation-date" style="font-weight: 900; color: #b91c1c;">
                    صلاحية العرض حتى: {{ $quotation->valid_until->format('Y/m/d') }}
                </div>
                @endif
            </div>
        </div>

        <!-- Customer & Store Info Card -->
        <div class="customer-card">
            <div>
                <span style="color: #555;">السيد / السادة المحترمين:</span>
                <b style="font-size: 15px; margin-right: 6px;">{{ $quotation->target_customer_name }}</b>
            </div>
            @if($quotation->target_customer_phone)
            <div>
                <span style="color: #555;">رقم الهاتف / واتساب:</span>
                <b style="font-family: monospace; font-size: 14px;" dir="ltr">{{ $quotation->target_customer_phone }}</b>
            </div>
            @endif
            <div>
                <span style="color: #555;">الفرع / المنفذ:</span>
                <b>{{ $quotation->store?->name ?? 'المخزن الرئيسي' }}</b>
            </div>
        </div>

        <!-- Items Table -->
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 5%; text-align: center;">#</th>
                    <th style="width: 15%;">كود الصنف</th>
                    <th style="width: 40%;">اسم وبيان الصنف</th>
                    <th style="width: 12%; text-align: center;">الكمية / الوزن</th>
                    <th style="width: 13%; text-align: left;">سعر الوحدة</th>
                    <th style="width: 15%; text-align: left;">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quotation->items as $index => $item)
                <tr>
                    <td style="text-align: center; font-family: monospace;">{{ $index + 1 }}</td>
                    <td style="font-family: monospace;">{{ $item->item?->code ?? '-' }}</td>
                    <td>
                        <span style="font-size: 13px;">{{ $item->item?->name ?? 'صنف غير معرف' }}</span>
                        @if($item->notes)
                            <div style="font-size: 10.5px; color: #555;">ملاحظة: {{ $item->notes }}</div>
                        @endif
                    </td>
                    <td style="text-align: center; font-family: monospace; font-weight: 900;">
                        {{ number_format($item->quantity, 2) }} {{ $item->item?->unit ?? 'كجم' }}
                    </td>
                    <td style="text-align: left; font-family: monospace;">
                        {{ number_format($item->unit_price, 2) }} ج.م
                    </td>
                    <td style="text-align: left; font-family: monospace; font-weight: 900;">
                        {{ number_format($item->total_price, 2) }} ج.م
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Summary & Terms Section -->
        <div class="summary-wrapper">
            <!-- Terms & Conditions -->
            <div class="terms-box">
                <div class="terms-title">📌 شروط وأحكام العرض والاعتماد:</div>
                <div class="terms-content">{{ $quotation->terms_conditions ?: "• الأسعار الموضحة بالعرض سارية حتى تاريخ انتهاء الصلاحية الموضح أعلاه.\n• التسليم من مقرات ومخازن سرور كوفي ما لم يُتفق على الشحن.\n• السداد نقدًا أو تحويل إلكتروني معتمد عند التوريد." }}</div>
                @if($quotation->notes)
                <div style="margin-top: 8px; font-size: 11.5px; border-top: 1px dashed #ccc; pt-1;">
                    <b>ملاحظات إضافية:</b> {{ $quotation->notes }}
                </div>
                @endif
            </div>

            <!-- Financial Box -->
            <div class="summary-box">
                <div class="summary-row">
                    <span>إجمالي الأصناف:</span>
                    <span style="font-family: monospace;">{{ number_format($quotation->subtotal, 2) }} ج.م</span>
                </div>

                @if(bccomp((string)$quotation->discount_amount, '0.000', 3) > 0)
                <div class="summary-row" style="color: #b91c1c;">
                    <span>الخصم الممنوح:</span>
                    <span style="font-family: monospace;">- {{ number_format($quotation->discount_amount, 2) }} ج.م</span>
                </div>
                @endif

                @if(bccomp((string)$quotation->shipping_cost, '0.000', 3) > 0)
                <div class="summary-row">
                    <span>مصاريف الشحن التقديرية:</span>
                    <span style="font-family: monospace;">+ {{ number_format($quotation->shipping_cost, 2) }} ج.م</span>
                </div>
                @endif

                <div class="summary-row total">
                    <span>صافي العرض المطلوب:</span>
                    <span style="font-family: monospace;">{{ number_format($quotation->net_total, 2) }} ج.م</span>
                </div>
            </div>
        </div>

        <!-- Footer Signatures & Contacts -->
        <div class="footer">
            <div>
                <div>📍 الفرع الرئيسي: الزقازيق - محافظة الشرقية</div>
                <div>📞 مبيعات الجملة والتوزيع: 01012316954 / 01558088841</div>
            </div>
            <div style="text-align: center; border: 1.5px dashed #000; padding: 6px 20px; border-radius: 6px;">
                <div>توقيع وخاتم سرور كوفي</div>
                <div style="height: 35px;"></div>
                <div style="font-size: 10px; color: #555;">معتمد</div>
            </div>
            <div style="text-align: left;">
                <div>تم استخراج العرض بواسطة: <b>{{ $quotation->user?->name ?? 'إدارة المبيعات' }}</b></div>
                <div style="font-family: monospace; font-size: 10px; color: #666;">{{ now()->format('Y-m-d H:i') }}</div>
            </div>
        </div>

    </div>

</body>
</html>
