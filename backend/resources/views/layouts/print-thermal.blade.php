<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ __('invoices.receipt_title', ['number' => $invoice->invoice_number]) }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@600;700;800;900&display=swap" rel="stylesheet">
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }
        @media print {
            body {
                width: 78mm;
                margin: 0 auto;
                padding: 3mm;
            }
            .no-print {
                display: none !important;
            }
            * {
                color: #000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
        body {
            font-family: 'Cairo', sans-serif;
            font-size: 12px;
            font-weight: 700;
            color: #000;
            background: #fff;
            width: 78mm;
            margin: 0 auto;
            padding: 4mm;
            line-height: 1.3;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .font-black { font-weight: 900; }
        .border-t { border-top: 1.5px dashed #000; }
        .border-b { border-bottom: 1.5px dashed #000; }
        .py-1 { padding-top: 3px; padding-bottom: 3px; }
        .py-2 { padding-top: 6px; padding-bottom: 6px; }
        .my-2 { margin-top: 6px; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 1px; font-size: 11px; font-weight: 700; }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body>

    <div class="no-print" style="margin-bottom: 10px; display: flex; flex-wrap: wrap; gap: 6px; justify-content: center; align-items: center;">
        <button onclick="window.print()" style="padding: 7px 14px; font-family: 'Cairo'; background: #10b981; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 900; font-size: 12px;">🖨️ {{ __('invoices.print_receipt') }}</button>
        <button onclick="downloadReceiptAsImage()" id="btn-thermal-img" style="padding: 7px 14px; font-family: 'Cairo'; background: #d97706; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 900; font-size: 12px;">📸 {{ __('invoices.download_image') }}</button>
        <button onclick="window.history.back()" style="padding: 7px 12px; font-family: 'Cairo'; background: #475569; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 700; font-size: 12px;">{{ __('common.back') }}</button>
    </div>

    <div id="receipt-container" style="background: #ffffff; padding: 4px;">

    @php
        $companyName = \App\Models\Setting::get('company_name', config('app.name', 'منظومة ERP'));
        $companySubtitle = \App\Models\Setting::get('company_subtitle', '');
        $showCompanyName = \App\Models\Setting::getBool('show_print_company_name', true);
        $showSubtitle = \App\Models\Setting::getBool('show_print_subtitle', true);
        $showLogo = \App\Models\Setting::getBool('show_print_logo', true);
        $logoPath = public_path('logo.png');
        $logoSrc = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : asset('logo.png');
    @endphp

    <!-- Header -->
    <div class="text-center">
        @if($showLogo)
            <img src="{{ $logoSrc }}" alt="{{ $companyName }}" style="max-height: 52px; max-width: 50mm; margin: 0 auto 4px auto; display: block; object-fit: contain;">
        @endif
        @if($showCompanyName)
            <h2 class="font-black" style="font-size: 17px; margin: 0; color: #000;">{{ $companyName }}</h2>
        @endif
        @if($showSubtitle && !empty($companySubtitle))
            <p style="margin: 2px 0; font-size: 11px; font-weight: 800;">{{ $companySubtitle }}</p>
        @endif
    </div>

    <div class="border-t border-b py-1 my-2">
        <div style="display: flex; justify-content: space-between;">
            <span><strong>{{ __('invoices.invoice_number') }}:</strong> {{ $invoice->invoice_number }}</span>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span><strong>{{ __('common.date') }}:</strong> {{ $invoice->invoice_date->format('Y-m-d') }}</span>
            <span><strong>{{ __('common.time') }}:</strong> {{ $invoice->created_at->format('H:i') }}</span>
        </div>
        <div>
            <span><strong>{{ __('invoices.customer') }}:</strong> {{ $invoice->customer->name }}</span>
        </div>
    </div>

    <!-- Items Table -->
    <table>
        <thead>
            <tr class="border-b">
                <th class="text-right">{{ __('inventory.item_name') }}</th>
                <th class="text-center">{{ __('common.quantity') }}</th>
                <th class="text-center">{{ __('common.unit_price') }}</th>
                <th class="text-left">{{ __('common.total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
            <tr>
                <td class="text-right">
                    <strong style="font-weight: 900;">{{ $item->item->name }}</strong>
                </td>
                <td class="text-center" style="font-weight: 800;">{{ number_format($item->quantity, 2) }}</td>
                <td class="text-center" style="font-weight: 800;">{{ number_format($item->unit_price, 2) }}</td>
                <td class="text-left font-black">{{ number_format($item->total_price, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totals -->
    <div class="border-t py-1 my-2">
        <div style="display: flex; justify-content: space-between;">
            <span>{{ __('common.subtotal') }}:</span>
            <span style="font-weight: 800;">{{ number_format($invoice->subtotal, 2) }} {{ __('common.currency') }}</span>
        </div>
        @if(bccomp($invoice->discount_amount, '0.000', 3) > 0)
        <div style="display: flex; justify-content: space-between;">
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
            <div style="display: flex; justify-content: space-between;">
                <span>+ {{ $cExp->title }}:</span>
                <span style="font-weight: 800;">+{{ number_format($cExp->amount, 2) }} {{ __('common.currency') }}</span>
            </div>
            @endforeach
        @elseif(bccomp($invoice->shipping_cost, '0.000', 3) > 0)
            <div style="display: flex; justify-content: space-between;">
                <span>+ {{ __('invoices.shipping_delivery') }}:</span>
                <span style="font-weight: 800;">+{{ number_format($invoice->shipping_cost, 2) }} {{ __('common.currency') }}</span>
            </div>
        @endif

        <div style="display: flex; justify-content: space-between; font-size: 14px;" class="font-black">
            <span>{{ __('common.net') }}:</span>
            <span>{{ number_format($invoice->net_total, 2) }} {{ __('common.currency') }}</span>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span>{{ __('common.paid') }}:</span>
            <span>{{ number_format($invoice->paid_amount, 2) }} {{ __('common.currency') }}</span>
        </div>
        <div style="display: flex; justify-content: space-between;" class="font-bold">
            <span>{{ __('common.remaining') }}:</span>
            <span>{{ number_format($invoice->remaining_amount, 2) }} {{ __('common.currency') }}</span>
        </div>

        @php
            $showCustomerBalance = \App\Models\Setting::getBool('thermal_show_customer_balance', true);
            $footerNote = \App\Models\Setting::get('invoice_footer_note', __('invoices.default_thank_you_note'));
            $showQr = \App\Models\Setting::getBool('print_show_qr', true);
            $customer = $invoice->customer;
        @endphp

        @if($showCustomerBalance && $customer && $customer->id != 1)
        <div style="margin-top: 5px; padding-top: 4px; border-top: 1px dashed #000; font-size: 11px;">
            <div style="display: flex; justify-content: space-between;">
                <span>{{ __('contacts.payable_balance_label') }}:</span>
                <span style="font-weight: 900;">{{ number_format($customer->current_balance, 2) }} {{ __('common.currency') }}</span>
            </div>
        </div>
        @endif
    </div>

    @if($showQr)
    <div class="text-center py-1" style="margin-top: 6px;">
        <img 
            src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&data={{ urlencode(url('/invoices/' . $invoice->id)) }}" 
            alt="QR Code" 
            style="width: 75px; height: 75px; margin: 0 auto; display: block;"
        >
        <span style="font-size: 9px; color: #555; display: block; margin-top: 2px;">{{ __('invoices.scan_qr_to_verify') }}</span>
    </div>
    @endif

    <div class="text-center py-2 border-t" style="margin-top: 6px;">
        <p style="margin: 0; font-size: 11px; font-weight: 800;">{{ $footerNote ?: __('invoices.default_thank_you_note') }}</p>
    </div>

    </div>

    <script>
        function downloadReceiptAsImage() {
            const btn = document.getElementById('btn-thermal-img');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span>{{ __("invoices.preparing") }}</span>';
            btn.style.opacity = '0.7';

            const element = document.getElementById('receipt-container');
            
            html2canvas(element, {
                scale: 3,
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'إيصال-{{ $invoice->invoice_number }}.png';
                link.href = canvas.toDataURL('image/png');
                link.click();

                btn.innerHTML = '<span>{{ __("invoices.downloaded_success") }}</span>';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.opacity = '1';
                }, 2000);
            }).catch(err => {
                console.error('Error generating receipt image:', err);
                btn.innerHTML = originalText;
                btn.style.opacity = '1';
                alert('{{ __("common.error") }}');
            });
        }
    </script>
</body>
</html>
