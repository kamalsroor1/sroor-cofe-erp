<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ __('treasury.daily_journal_report_title', ['date' => $date]) }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@600;700;800;900&family=Tajawal:wght@500;700;900&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm;
        }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0 !important; background: #fff !important; }
            .container { box-shadow: none !important; border: none !important; padding: 0 !important; width: 100% !important; max-width: 100% !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color: #000 !important; }
            .summary-card { border: 1.5px solid #000 !important; }
            .table th { background: #f1f5f9 !important; color: #000 !important; border: 1px solid #000 !important; }
            .table td { border: 1px solid #333 !important; }
        }
        body {
            font-family: 'Cairo', 'Tajawal', sans-serif;
            color: #000000;
            background: #f8fafc;
            padding: 15px;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.4;
        }
        .container {
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
            padding: 20px 25px;
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
            margin-bottom: 15px;
        }
        .brand-title {
            margin: 0;
            color: #000;
            font-size: 24px;
            font-weight: 900;
        }
        .brand-subtitle {
            margin: 3px 0 0 0;
            color: #333;
            font-size: 13px;
            font-weight: 700;
        }
        .report-meta {
            text-align: left;
        }
        .report-title {
            margin: 0;
            font-size: 20px;
            font-weight: 900;
            color: #000;
        }
        .report-date {
            margin: 3px 0;
            font-size: 13px;
            font-weight: 800;
            color: #000;
        }
        .info-bar {
            background: #f8fafc;
            border: 1.5px solid #000;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 12.5px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }
        .summary-card {
            background: #ffffff;
            border: 1.5px solid #000;
            border-radius: 6px;
            padding: 8px 10px;
            text-align: center;
        }
        .summary-label {
            font-size: 11px;
            font-weight: 800;
            color: #333;
            margin-bottom: 3px;
        }
        .summary-val {
            font-size: 15px;
            font-weight: 900;
            color: #000;
            font-family: monospace;
        }
        .section-title {
            font-size: 14px;
            font-weight: 900;
            border-bottom: 2px solid #000;
            padding-bottom: 4px;
            margin: 16px 0 8px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 12px;
        }
        .table th {
            background: #f1f5f9;
            color: #000;
            padding: 6px 8px;
            border: 1.5px solid #000;
            font-weight: 900;
            text-align: center;
        }
        .table td {
            padding: 5px 8px;
            border: 1px solid #333;
            color: #000;
            font-weight: 700;
            text-align: center;
        }
        .table tr:nth-child(even) {
            background: #fafafa;
        }
        .table-total {
            font-weight: 900;
            background: #e2e8f0 !important;
        }
        .signatures {
            margin-top: 25px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            text-align: center;
            border-top: 2px dashed #000;
            padding-top: 15px;
        }
        .sig-box {
            padding: 10px;
        }
        .sig-title {
            font-size: 12px;
            font-weight: 900;
            margin-bottom: 35px;
        }
        .sig-line {
            border-bottom: 1px solid #000;
            margin: 0 auto;
            width: 80%;
        }
    </style>
</head>
<body>

    <!-- Print Action Bar (Hidden on actual print) -->
    <div class="no-print" style="max-width: 210mm; margin: 0 auto 12px auto; display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 12px 18px; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <div style="font-weight: 900; font-size: 14px;">🖨️ {{ __('treasury.daily_journal_print_preview') }}</div>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.print()" style="padding: 8px 18px; background: #059669; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 900; font-family: 'Cairo'; font-size: 13px;">
                🖨️ {{ __('treasury.print_now') }}
            </button>
            <button onclick="window.close()" style="padding: 8px 14px; background: #64748b; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 700; font-family: 'Cairo'; font-size: 13px;">
                ✕ {{ __('common.close') }}
            </button>
        </div>
    </div>

    <div class="container">

        <!-- Header -->
        <div class="header">
            <div style="display: flex; align-items: center; gap: 14px;">
                @php
                    $showLogo = \App\Models\Setting::getBool('show_print_logo', true);
                    $logoPath = public_path('logo.png');
                    $logoSrc = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : asset('logo.png');
                    $companyName = \App\Models\Setting::get('company_name', config('app.name', 'منظومة ERP'));
                    $companySubtitle = \App\Models\Setting::get('company_subtitle', '');
                @endphp
                @if($showLogo)
                    <img src="{{ $logoSrc }}" alt="Logo" style="max-height: 65px; max-width: 120px; object-fit: contain;">
                @endif
                <div>
                    <h1 class="brand-title">{{ $companyName }}</h1>
                    @if($companySubtitle)
                        <p class="brand-subtitle">{{ $companySubtitle }}</p>
                    @endif
                </div>
            </div>

            <div class="report-meta">
                <h2 class="report-title">{{ __('treasury.daily_journal') }}</h2>
                <div class="report-date">📅 {{ __('common.date') }}: {{ $date }}</div>
                <div style="font-size: 11px; color: #444;">{{ __('treasury.print_time') }}: {{ now()->format('Y-m-d h:i A') }}</div>
            </div>
        </div>

        <!-- Info Bar -->
        <div class="info-bar">
            <div><b>🏢 {{ __('common.store') }}:</b> {{ $storeName }}</div>
            <div><b>👤 {{ __('treasury.printed_by') }}:</b> {{ auth()->user()?->name ?? __('common.user') }}</div>
            <div><b>🧾 {{ __('invoices.total_invoices_count') }}:</b> {{ $invoicesCount }} {{ __('invoices.invoice_unit') }}</div>
            <div><b>🔐 {{ __('treasury.shifts_count') }}:</b> {{ $shiftsOnDate->count() }}</div>
        </div>

        <!-- KPI Financial Summary Cards Grid -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-label">{{ __('common.total') }} {{ __('invoices.title') }}</div>
                <div class="summary-val">{{ number_format((float)$totalSales, 2) }} {{ __('common.currency') }}</div>
            </div>

            <div class="summary-card" style="background: #f0fdf4;">
                <div class="summary-label">{{ __('treasury.cash_sales_collected') }}</div>
                <div class="summary-val" style="color: #166534;">{{ number_format((float)$cashSales, 2) }} {{ __('common.currency') }}</div>
            </div>

            <div class="summary-card" style="background: #fffbeb;">
                <div class="summary-label">{{ __('treasury.recorded_credit_sales') }}</div>
                <div class="summary-val" style="color: #92400e;">{{ number_format((float)$creditSales, 2) }} {{ __('common.currency') }}</div>
            </div>

            <div class="summary-card" style="background: #f8fafc;">
                <div class="summary-label">{{ __('treasury.customer_receipts_collections') }}</div>
                <div class="summary-val">{{ number_format((float)$customerPayments, 2) }} {{ __('common.currency') }}</div>
            </div>

            <div class="summary-card" style="background: #fef2f2;">
                <div class="summary-label">{{ __('treasury.expenses_and_withdrawals') }}</div>
                <div class="summary-val" style="color: #991b1b;">{{ number_format((float)$totalExpenses, 2) }} {{ __('common.currency') }}</div>
            </div>

            <div class="summary-card" style="background: #fef2f2;">
                <div class="summary-label">{{ __('treasury.supplier_cash_payments') }}</div>
                <div class="summary-val" style="color: #991b1b;">{{ number_format((float)$totalSupplierPaid, 2) }} {{ __('common.currency') }}</div>
            </div>

            <div class="summary-card">
                <div class="summary-label">{{ __('treasury.opening_float') }}</div>
                <div class="summary-val">{{ number_format((float)$openingCashBalance, 2) }} {{ __('common.currency') }}</div>
            </div>

            <div class="summary-card" style="background: #e0f2fe; border: 2px solid #0284c7;">
                <div class="summary-label" style="color: #0369a1;">{{ __('treasury.calculated_cash_in_drawer') }}</div>
                <div class="summary-val" style="color: #0369a1; font-size: 16px;">{{ number_format((float)$expectedCashInDrawer, 2) }} {{ __('common.currency') }}</div>
            </div>
        </div>

        <!-- 1. Shifts & Drawer Closing Table -->
        @if($shiftsOnDate->isNotEmpty())
        <div class="section-title">
            <span>🔐 {{ __('treasury.shifts_closing_statement') }}</span>
            <span style="font-size: 12px; font-weight: normal;">({{ $shiftsOnDate->count() }})</span>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('treasury.shift_number') }}</th>
                    <th>{{ __('common.cashier') }}</th>
                    <th>{{ __('common.store') }}</th>
                    <th>{{ __('treasury.opening_time') }}</th>
                    <th>{{ __('treasury.closing_time') }}</th>
                    <th>{{ __('treasury.opening_balance_short') }}</th>
                    <th>{{ __('treasury.calculated_short') }}</th>
                    <th>{{ __('treasury.actual_short') }}</th>
                    <th>{{ __('treasury.diff_short') }}</th>
                    <th>{{ __('common.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($shiftsOnDate as $shift)
                <tr>
                    <td style="font-family: monospace; font-weight: 900;">#{{ $shift->shift_number }}</td>
                    <td>{{ $shift->user?->name ?? __('common.unspecified') }}</td>
                    <td>{{ $shift->store?->name ?? __('common.main_store_default') }}</td>
                    <td>{{ $shift->opened_at ? $shift->opened_at->format('h:i A') : '-' }}</td>
                    <td>{{ $shift->closed_at ? $shift->closed_at->format('h:i A') : __('treasury.currently_open') }}</td>
                    <td style="font-family: monospace;">{{ number_format((float)$shift->opening_cash_balance, 2) }}</td>
                    <td style="font-family: monospace;">{{ number_format((float)$shift->expected_cash_balance, 2) }}</td>
                    <td style="font-family: monospace; font-weight: 900;">{{ $shift->actual_cash_balance !== null ? number_format((float)$shift->actual_cash_balance, 2) : '-' }}</td>
                    <td style="font-family: monospace; font-weight: 900;">
                        @if($shift->cash_difference !== null)
                            @if(bccomp((string)$shift->cash_difference, '0.000', 3) < 0)
                                <span style="color: #dc2626;">{{ __('treasury.shortage_prefix') }} {{ number_format(abs((float)$shift->cash_difference), 2) }}</span>
                            @elseif(bccomp((string)$shift->cash_difference, '0.000', 3) > 0)
                                <span style="color: #16a34a;">{{ __('treasury.overage_prefix') }} {{ number_format((float)$shift->cash_difference, 2) }}</span>
                            @else
                                <span>0.00 ({{ __('treasury.exact_match') }})</span>
                            @endif
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $shift->status === 'open' ? '🟢 ' . __('treasury.open_badge') : '🔒 ' . __('treasury.closed_badge') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <!-- 2. Expenses Table -->
        @if($expenses->isNotEmpty())
        <div class="section-title">
            <span>💸 {{ __('treasury.cash_expenses_statement') }}</span>
            <span style="font-size: 12px; font-weight: 900;">{{ __('common.total') }}: {{ number_format((float)$totalExpenses, 2) }} {{ __('common.currency') }}</span>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('treasury.category_item') }}</th>
                    <th>{{ __('treasury.details_desc') }}</th>
                    <th>{{ __('treasury.disbursement_method') }}</th>
                    <th>{{ __('common.store') }}</th>
                    <th>{{ __('common.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($expenses as $idx => $exp)
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td>{{ $exp->category ?? __('expenses.general_expenses') }}</td>
                    <td style="text-align: right;">{{ $exp->notes ?: $exp->description ?: __('expenses.operational_expense') }}</td>
                    <td>{{ $exp->payment_method === 'cash' ? __('treasury.from_drawer_cash') : __('treasury.bank_other') }}</td>
                    <td>{{ $exp->store?->name ?? __('common.main_store_default') }}</td>
                    <td style="font-family: monospace; font-weight: 900;">{{ number_format((float)$exp->amount, 2) }} {{ __('common.currency') }}</td>
                </tr>
                @endforeach
                <tr class="table-total">
                    <td colspan="5" style="text-align: left; font-weight: 900;">{{ __('common.total') }} {{ __('expenses.title') }}:</td>
                    <td style="font-family: monospace; font-weight: 900;">{{ number_format((float)$totalExpenses, 2) }} {{ __('common.currency') }}</td>
                </tr>
            </tbody>
        </table>
        @endif

        <!-- 3. Invoices Summary Table -->
        @if($invoices->isNotEmpty())
        <div class="section-title">
            <span>🧾 {{ __('treasury.issued_invoices_first_30') }}</span>
            <span style="font-size: 12px; font-weight: 900;">{{ __('common.total') }}: {{ number_format((float)$totalSales, 2) }} {{ __('common.currency') }}</span>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('invoices.invoice_number') }}</th>
                    <th>{{ __('common.time') }}</th>
                    <th>{{ __('invoices.customer') }}</th>
                    <th>{{ __('invoices.payment_type') }}</th>
                    <th>{{ __('common.total') }}</th>
                    <th>{{ __('treasury.cash_paid_col') }}</th>
                    <th>{{ __('treasury.credit_remaining_col') }}</th>
                    <th>{{ __('common.store') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoices->take(30) as $inv)
                <tr>
                    <td style="font-family: monospace; font-weight: 900;">#{{ $inv->invoice_number }}</td>
                    <td>{{ $inv->created_at->format('h:i A') }}</td>
                    <td style="text-align: right;">{{ $inv->customer?->name ?? __('treasury.cash_customer') }}</td>
                    <td>
                        @if($inv->payment_type === 'cash') {{ __('invoices.cash') }}
                        @elseif($inv->payment_type === 'credit') {{ __('invoices.credit') }}
                        @else {{ __('invoices.partial') }}
                        @endif
                    </td>
                    <td style="font-family: monospace; font-weight: 900;">{{ number_format((float)$inv->total_amount, 2) }}</td>
                    <td style="font-family: monospace;">{{ number_format((float)$inv->paid_amount, 2) }}</td>
                    <td style="font-family: monospace;">{{ number_format((float)$inv->remaining_amount, 2) }}</td>
                    <td>{{ $inv->store?->name ?? __('common.main_store_default') }}</td>
                </tr>
                @endforeach
                <tr class="table-total">
                    <td colspan="4" style="text-align: left; font-weight: 900;">{{ __('common.total') }}:</td>
                    <td style="font-family: monospace; font-weight: 900;">{{ number_format((float)$totalSales, 2) }}</td>
                    <td style="font-family: monospace; font-weight: 900;">{{ number_format((float)$cashSales, 2) }}</td>
                    <td style="font-family: monospace; font-weight: 900;">{{ number_format((float)$creditSales, 2) }}</td>
                    <td>-</td>
                </tr>
            </tbody>
        </table>
        @endif

        <!-- Signatures & Stamp -->
        <div class="signatures">
            <div class="sig-box">
                <div class="sig-title">{{ __('treasury.treasurer_cashier_sig') }}</div>
                <div class="sig-line"></div>
            </div>
            <div class="sig-box">
                <div class="sig-title">{{ __('treasury.accountant_auditor_sig') }}</div>
                <div class="sig-line"></div>
            </div>
            <div class="sig-box">
                <div class="sig-title">{{ __('treasury.management_stamp_approval') }}</div>
                <div class="sig-line"></div>
            </div>
        </div>

    </div>

    <script>
        window.addEventListener('load', function() {
            // Auto open print dialog if opened in a new tab
            if (window.location.search.includes('autoprint=1')) {
                setTimeout(function() { window.print(); }, 500);
            }
        });
    </script>
</body>
</html>
