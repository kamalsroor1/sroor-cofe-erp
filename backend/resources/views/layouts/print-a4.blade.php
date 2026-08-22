<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ __('invoices.sales_invoice') }} - {{ $invoice->invoice_number }}</title>
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
            font-size: 14px;
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
            font-size: 15px;
            font-weight: 800;
        }
        .invoice-meta {
            text-align: left;
        }
        .invoice-title {
            margin: 0;
            font-size: 22px;
            font-weight: 900;
            color: #000;
        }
        .invoice-num {
            margin: 3px 0;
            font-weight: 900;
            font-size: 15px;
            color: #000;
            font-family: monospace;
        }
        .invoice-date {
            margin: 2px 0;
            font-size: 13px;
            font-weight: 700;
            color: #000;
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
            font-size: 13px;
            font-weight: 900;
            text-align: right;
            border: 1.5px solid #000000;
        }
        .table td {
            padding: 8px 10px;
            border: 1.5px solid #000000;
            font-size: 13px;
            font-weight: 700;
            color: #000000;
        }
        .totals-card {
            width: 340px;
            margin-right: auto;
            background: #ffffff;
            border: 2px solid #000000;
            border-radius: 6px;
            padding: 12px 16px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 14px;
            font-weight: 800;
            color: #000000;
        }
        .totals-row.final-net {
            font-size: 17px;
            font-weight: 900;
            border-top: 2px solid #000000;
            padding-top: 6px;
            margin-top: 4px;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 35px;
            padding-top: 15px;
            border-top: 2px dashed #000000;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body>

    <div class="no-print" style="max-width: 210mm; margin: 0 auto 15px auto; display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
        <button onclick="window.print()" style="padding: 10px 22px; background: #059669; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 900; font-family: 'Cairo'; font-size: 14px; display: inline-flex; align-items: center; gap: 6px;">
            <span>🖨️ {{ __('invoices.print_a4') }}</span>
        </button>
        <button onclick="downloadAsImage()" id="btn-download-img" style="padding: 10px 22px; background: #d97706; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 900; font-family: 'Cairo'; font-size: 14px; display: inline-flex; align-items: center; gap: 6px;">
            <span>📸 {{ __('invoices.download_image') }}</span>
        </button>
        <button onclick="window.history.back()" style="padding: 10px 20px; background: #475569; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-family: 'Cairo'; font-weight: 700; font-size: 14px;">
            {{ __('common.back') }}
        </button>
    </div>

    @php
        $companyName = \App\Models\Setting::get('company_name', 'سرور كوفي');
        $companySubtitle = \App\Models\Setting::get('company_subtitle', 'لتوريدات خامات مطاحن البن');
        $showCompanyName = \App\Models\Setting::getBool('show_print_company_name', true);
        $showSubtitle = \App\Models\Setting::getBool('show_print_subtitle', true);
        $showLogo = \App\Models\Setting::getBool('show_print_logo', true);
        $logoPath = public_path('logo.png');
        $logoSrc = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : asset('logo.png');
    @endphp

    @php
        $hasBrandHeader = $showLogo || $showCompanyName || ($showSubtitle && !empty($companySubtitle));
    @endphp

    <div class="container">
        <!-- Header -->
        <div class="header" style="{{ !$hasBrandHeader ? 'display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; border-bottom: 2px solid #000; padding-bottom: 12px; margin-bottom: 15px;' : '' }}">
            @if($hasBrandHeader)
            <div style="display: flex; align-items: center; gap: 14px;">
                @if($showLogo)
                    <img src="{{ $logoSrc }}" alt="{{ $companyName }}" style="max-height: 75px; max-width: 130px; object-fit: contain;">
                @endif
                @if($showCompanyName || ($showSubtitle && !empty($companySubtitle)))
                <div>
                    @if($showCompanyName)
                        <h1 class="brand-title">{{ $companyName }}</h1>
                    @endif
                    @if($showSubtitle && !empty($companySubtitle))
                        <p class="brand-subtitle">{{ $companySubtitle }}</p>
                    @endif
                </div>
                @endif
            </div>
            @endif

            <div class="invoice-meta" style="{{ !$hasBrandHeader ? 'text-align: center; width: 100%; margin: 0 auto;' : '' }}">
                <h2 class="invoice-title" style="{{ !$hasBrandHeader ? 'font-size: 24px; text-decoration: underline; margin-bottom: 4px;' : '' }}">{{ __('invoices.sales_invoice') }}</h2>
                <p class="invoice-num" style="{{ !$hasBrandHeader ? 'font-size: 16px; margin: 4px 0;' : '' }}">{{ $invoice->invoice_number }}</p>
                <p class="invoice-date" style="{{ !$hasBrandHeader ? 'font-size: 13px;' : '' }}">{{ __('common.date') }}: {{ $invoice->invoice_date->format('Y-m-d') }}</p>
            </div>
        </div>

        <!-- Customer Info Row -->
        <div style="margin-bottom: 16px;">
            <div class="customer-card" style="margin-bottom: 0;">
                <div>
                    <span style="font-size: 13px; font-weight: 900; color: #000;">{{ __('invoices.customer') }}:</span>
                    <span style="font-size: 15px; font-weight: 900; color: #000; margin-right: 6px;">{{ $invoice->customer->name }}</span>
                </div>
                @if($invoice->customer->phone)
                <div>
                    <span style="font-size: 12px; font-weight: 900;">{{ __('common.phone') }}:</span>
                    <span style="font-size: 13px; font-weight: 800; font-family: monospace;" dir="ltr">{{ $invoice->customer->phone }}</span>
                </div>
                @endif
                @if($invoice->customer->address)
                <div>
                    <span style="font-size: 12px; font-weight: 900;">{{ __('common.address') }}:</span>
                    <span style="font-size: 13px; font-weight: 700;">{{ $invoice->customer->address }}</span>
                </div>
                @endif
            </div>
        </div>

        <!-- Items Table -->
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">#</th>
                    <th>{{ __('invoices.item_description') }}</th>
                    <th style="text-align: center; width: 100px;">{{ __('common.quantity') }}</th>
                    <th style="text-align: center; width: 110px;">{{ __('common.unit_price') }}</th>
                    <th style="text-align: center; width: 90px;">{{ __('common.discount') }}</th>
                    <th style="text-align: left; width: 130px;">{{ __('common.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $index => $item)
                <tr>
                    <td style="text-align: center; font-weight: 900;">{{ $index + 1 }}</td>
                    <td>
                        <strong style="font-size: 14px; font-weight: 900;">{{ $item->item->name }}</strong>
                        @if($item->item->code)
                            <div style="font-size: 11px; font-weight: 700;">{{ __('inventory.item_code') }}: {{ $item->item->code }}</div>
                        @endif
                    </td>
                    <td style="text-align: center; font-weight: 800;">
                        {{ number_format($item->quantity, 2) }} {{ $item->item->unit }}
                    </td>
                    <td style="text-align: center; font-weight: 800;">
                        {{ number_format($item->unit_price, 2) }}
                    </td>
                    <td style="text-align: center; font-weight: 800;">
                        {{ number_format($item->discount_amount, 2) }}
                    </td>
                    <td style="text-align: left; font-weight: 900; font-size: 14px;">
                        {{ number_format($item->total_price, 2) }} {{ __('common.currency') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals Card -->
        <div class="totals-card">
            <div class="totals-row">
                <span>{{ __('common.subtotal') }}:</span>
                <span>{{ number_format($invoice->subtotal, 2) }} {{ __('common.currency') }}</span>
            </div>
            @if(bccomp($invoice->discount_amount, '0.000', 3) > 0)
            <div class="totals-row">
                <span>{{ __('common.discount') }}:</span>
                <span>-{{ number_format($invoice->discount_amount, 2) }} {{ __('common.currency') }}</span>
            </div>
            @endif

            {{-- Additional Expenses Charged to Customer (e.g. Shipping / Delivery / Packing) --}}
            @php
                $customerExpenses = $invoice->additionalExpenses ? $invoice->additionalExpenses->whereIn('paid_by', ['customer_account', 'supplier_account']) : collect();
            @endphp
            @if($customerExpenses->count() > 0)
                @foreach($customerExpenses as $cExp)
                <div class="totals-row">
                    <span>+ {{ $cExp->title }}:</span>
                    <span>+{{ number_format($cExp->amount, 2) }} {{ __('common.currency') }}</span>
                </div>
                @endforeach
            @elseif(bccomp($invoice->shipping_cost, '0.000', 3) > 0)
                <div class="totals-row">
                    <span>+ {{ __('invoices.shipping_cost') }}:</span>
                    <span>+{{ number_format($invoice->shipping_cost, 2) }} {{ __('common.currency') }}</span>
                </div>
            @endif

            <div class="totals-row final-net">
                <span>{{ __('common.net') }}:</span>
                <span>{{ number_format($invoice->net_total, 2) }} {{ __('common.currency') }}</span>
            </div>
            <div class="totals-row">
                <span>{{ __('common.paid') }}:</span>
                <span>{{ number_format($invoice->paid_amount, 2) }} {{ __('common.currency') }}</span>
            </div>
            <div class="totals-row" style="border-top: 1px solid #000; margin-top: 4px; padding-top: 4px;">
                <span>{{ __('common.remaining') }}:</span>
                <span style="font-weight: 900;">{{ number_format($invoice->remaining_amount, 2) }} {{ __('common.currency') }}</span>
            </div>
        </div>

        <!-- Signatures -->
        <div class="signatures">
            <div style="text-align: center;">
                <p style="margin: 0; font-weight: 900;">{{ __('invoices.recipient_signature') }}</p>
                <p style="margin: 30px 0 0 0;">....................................</p>
            </div>
            <div style="text-align: center;">
                <p style="margin: 0; font-weight: 900;">{{ __('invoices.management_stamp_signature') }}</p>
                <p style="margin: 30px 0 0 0;">....................................</p>
            </div>
        </div>
    </div>

    <script>
        function downloadAsImage() {
            const btn = document.getElementById('btn-download-img');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span>{{ __("invoices.preparing") }}</span>';
            btn.style.opacity = '0.7';

            const element = document.querySelector('.container');
            
            html2canvas(element, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'فاتورة-مبيعات-{{ $invoice->invoice_number }}.png';
                link.href = canvas.toDataURL('image/png');
                link.click();

                btn.innerHTML = '<span>{{ __("invoices.downloaded_success") }}</span>';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.opacity = '1';
                }, 2000);
            }).catch(err => {
                console.error('Error generating invoice image:', err);
                btn.innerHTML = originalText;
                btn.style.opacity = '1';
                alert('{{ __("common.error") }}');
            });
        }
    </script>
</body>
</html>
