<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $companyName = \App\Models\Setting::get('company_name', config('app.name', 'منظومة ERP'));
        $companySubtitle = \App\Models\Setting::get('company_subtitle', '');
    @endphp
    <title>{{ $reportTitle }} - A4 ({{ $companyName }})</title>
    
    <!-- Cairo / Tajawal Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@400;500;700;800&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 10mm 15mm 10mm;
        }

        body {
            font-family: 'Cairo', 'Tajawal', sans-serif;
            background-color: #ffffff;
            color: #0f172a;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                padding: 0 !important;
                margin: 0 !important;
            }

            .page-break-inside-avoid {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body class="p-6 max-w-5xl mx-auto text-slate-900 text-xs">

    <!-- Top Action Bar (Screen Only) -->
    <div class="no-print mb-6 p-4 bg-slate-900 text-white rounded-2xl flex items-center justify-between shadow-lg">
        <div class="flex items-center gap-3">
            <span class="text-xl">🖨️</span>
            <div>
                <h1 class="text-sm font-bold">{{ __('reports.report_preview_title', ['title' => $reportTitle]) }}</h1>
                <p class="text-[11px] text-slate-400">{{ __('reports.report_preview_desc') }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 shadow-md cursor-pointer">
                <span>🖨️ {{ __('reports.print_report_btn') }}</span>
            </button>
            <button onclick="window.close()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-xl text-xs cursor-pointer">
                {{ __('common.close') }}
            </button>
        </div>
    </div>

    <!-- Official Header -->
    <div class="border-b-2 border-slate-900 pb-4 mb-4">
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <h1 class="text-xl font-black text-slate-950 flex items-center gap-2">
                    <span>🏢 {{ $companyName }}</span>
                </h1>
                <p class="text-xs font-bold text-slate-600">{{ $companySubtitle }}</p>
                <div class="text-[10px] text-slate-500 font-mono">
                    {{ __('inventory.report_generated_at') }} {{ now()->format('Y-m-d H:i') }} | {{ __('common.by') }}: {{ auth()->user()?->name ?? __('common.user') }}
                </div>
            </div>

            <div class="text-left bg-slate-100 p-3 rounded-xl border border-slate-300 min-w-[220px]">
                <h2 class="text-sm font-black text-slate-900 mb-1 border-b border-slate-300 pb-1">{{ $reportTitle }}</h2>
                <div class="text-[11px] font-bold text-slate-700">
                    {{ __('reports.store_scope') }} <span class="text-slate-900">{{ $storeName }}</span>
                </div>
                <div class="text-[10px] text-slate-600 font-mono mt-0.5">
                    {{ __('inventory.period_range') }} {{ $fromDate ?: __('inventory.beginning_of_period') }} {{ __('inventory.to_date_label') }} {{ $toDate ?: now()->toDateString() }}
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Summary Grid -->
    @if(isset($kpis) && count($kpis) > 0)
    <div class="grid grid-cols-{{ count($kpis) }} gap-2 mb-4">
        @foreach($kpis as $kpi)
        <div class="border border-slate-300 rounded-xl p-2.5 text-center bg-slate-50">
            <span class="text-[10px] text-slate-500 block font-bold">{{ $kpi['label'] }}</span>
            <span class="font-mono font-black text-sm block mt-0.5 {{ $kpi['class'] ?? 'text-slate-950' }}">
                {{ $kpi['value'] }}
            </span>
            @if(isset($kpi['subtext']))
            <span class="text-[9px] text-slate-400 block">{{ $kpi['subtext'] }}</span>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    <!-- Report Table Content -->
    <table class="w-full border-collapse border border-slate-400 text-right text-[11px] mb-6">
        <thead>
            <tr class="bg-slate-200 text-slate-900 font-bold border-b border-slate-400">
                <th class="border border-slate-400 p-2 text-center w-8">#</th>
                @foreach($tableHeaders as $header)
                <th class="border border-slate-400 p-2 {{ $header['align'] ?? '' }}">{{ $header['title'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-300">
            @forelse($tableRows as $idx => $row)
            <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-slate-50' }}">
                <td class="border border-slate-400 p-1.5 text-center font-mono text-[10px]">{{ $idx + 1 }}</td>
                @foreach($row as $cell)
                <td class="border border-slate-400 p-1.5 {{ $cell['class'] ?? '' }}">
                    {!! $cell['value'] !!}
                </td>
                @endforeach
            </tr>
            @empty
            <tr>
                <td colspan="{{ count($tableHeaders) + 1 }}" class="border border-slate-400 p-6 text-center text-slate-500 font-bold">
                    {{ __('reports.no_report_data') }}
                </td>
            </tr>
            @endforelse
        </tbody>
        @if(isset($tableTotals) && count($tableTotals) > 0)
        <tfoot>
            <tr class="bg-slate-200 font-black border-t-2 border-slate-500 text-slate-950">
                <td class="border border-slate-400 p-2 text-center">∑</td>
                @foreach($tableTotals as $totalCell)
                <td class="border border-slate-400 p-2 {{ $totalCell['class'] ?? '' }}">
                    {{ $totalCell['value'] }}
                </td>
                @endforeach
            </tr>
        </tfoot>
        @endif
    </table>

    <!-- Official Signatures Box -->
    <div class="page-break-inside-avoid mt-8 pt-4 border-t-2 border-slate-900">
        <div class="grid grid-cols-3 gap-6 text-center text-xs font-bold text-slate-900">
            <div>
                <p class="mb-10">{{ __('reports.prepared_by_accountant') }}</p>
                <p class="text-[10px] text-slate-400">{{ __('inventory.signature_line') }}</p>
            </div>
            <div>
                <p class="mb-10">{{ __('reports.financial_audit_review') }}</p>
                <p class="text-[10px] text-slate-400">{{ __('inventory.signature_line') }}</p>
            </div>
            <div>
                <p class="mb-10">{{ __('treasury.management_stamp_approval') }}</p>
                <p class="text-[10px] text-slate-400">{{ __('inventory.signature_line') }}</p>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('autoprint') === '1' || urlParams.get('print') === '1') {
                setTimeout(() => window.print(), 400);
            }
        });
    </script>
</body>
</html>
