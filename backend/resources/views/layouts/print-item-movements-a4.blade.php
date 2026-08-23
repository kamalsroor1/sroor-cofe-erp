<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('inventory.item_movements_card_title', ['name' => $item->name]) }}</title>
    
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
                <h1 class="text-sm font-bold">{{ __('inventory.item_movements_preview_title') }}</h1>
                <p class="text-[11px] text-slate-400">{{ __('inventory.item_movements_preview_desc') }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 shadow-md cursor-pointer">
                <span>🖨️ {{ __('inventory.print_now_shortcut') }}</span>
            </button>
            <button onclick="window.close()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-xl text-xs cursor-pointer">
                {{ __('common.close') }}
            </button>
        </div>
    </div>

    @php
        $companyName = \App\Models\Setting::get('company_name', config('app.name', 'منظومة ERP'));
        $companySubtitle = \App\Models\Setting::get('company_subtitle', '');
    @endphp

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

            <div class="text-left bg-slate-100 p-3 rounded-xl border border-slate-300 min-w-[200px]">
                <h2 class="text-sm font-black text-slate-900 mb-1 border-b border-slate-300 pb-1">{{ __('inventory.item_card_heading') }}</h2>
                <div class="text-[11px] font-bold text-slate-700">
                    {{ __('common.store') }}: <span class="text-slate-900">{{ $storeName }}</span>
                </div>
                <div class="text-[10px] text-slate-600 font-mono mt-0.5">
                    {{ __('inventory.period_range') }} {{ $fromDate ?: __('inventory.beginning_of_period') }} {{ __('inventory.to_date_label') }} {{ $toDate ?: now()->toDateString() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Item Information Grid -->
    <div class="bg-slate-50 border border-slate-300 rounded-xl p-3 mb-4 grid grid-cols-4 gap-3 text-xs">
        <div>
            <span class="text-slate-500 text-[10px] block">{{ __('inventory.item_name') }}:</span>
            <strong class="text-slate-950 text-sm block">{{ $item->name }}</strong>
        </div>
        <div>
            <span class="text-slate-500 text-[10px] block">{{ __('inventory.code') }}:</span>
            <strong class="font-mono text-slate-900 text-sm block">{{ $item->code }}</strong>
        </div>
        <div>
            <span class="text-slate-500 text-[10px] block">{{ __('inventory.category_unit_label') }}</span>
            <span class="font-bold text-slate-900 block">{{ $item->category ?: __('inventory.general_category') }} ({{ $item->unit }})</span>
        </div>
        <div>
            <span class="text-slate-500 text-[10px] block">{{ __('inventory.approved_selling_price') }}</span>
            <span class="font-mono font-bold text-slate-900 block">{{ number_format($item->selling_price, 2) }} {{ __('common.currency') }}</span>
        </div>
    </div>

    <!-- Summary KPI Box -->
    <div class="grid grid-cols-4 gap-2 mb-4">
        <div class="border border-slate-300 rounded-lg p-2 text-center bg-slate-50">
            <span class="text-[10px] text-slate-500 block">{{ __('inventory.total_inbound_period') }}</span>
            <span class="font-mono font-black text-sm text-emerald-700 block mt-0.5">+{{ number_format($totalIn, 3) }} {{ $item->unit }}</span>
        </div>
        <div class="border border-slate-300 rounded-lg p-2 text-center bg-slate-50">
            <span class="text-[10px] text-slate-500 block">{{ __('inventory.total_outbound_period') }}</span>
            <span class="font-mono font-black text-sm text-rose-700 block mt-0.5">-{{ number_format($totalOut, 3) }} {{ $item->unit }}</span>
        </div>
        <div class="border border-slate-300 rounded-lg p-2 text-center bg-slate-50">
            <span class="text-[10px] text-slate-500 block">{{ __('inventory.net_period_movement') }}</span>
            <span class="font-mono font-black text-sm text-slate-900 block mt-0.5">{{ bccomp($netMovement, '0.000', 3) > 0 ? '+' : '' }}{{ number_format($netMovement, 3) }} {{ $item->unit }}</span>
        </div>
        <div class="border-2 border-slate-900 rounded-lg p-2 text-center bg-slate-100">
            <span class="text-[10px] text-slate-600 font-bold block">{{ __('inventory.current_actual_stock') }}</span>
            <span class="font-mono font-black text-base text-slate-950 block mt-0.5">{{ number_format($currentScopeStock, 3) }} {{ $item->unit }}</span>
        </div>
    </div>

    <!-- Movements Table -->
    <table class="w-full border-collapse border border-slate-400 text-right text-[11px] mb-6">
        <thead>
            <tr class="bg-slate-200 text-slate-900 font-bold border-b border-slate-400">
                <th class="border border-slate-400 p-2 text-center w-8">#</th>
                <th class="border border-slate-400 p-2">{{ __('inventory.movement_date_time') }}</th>
                <th class="border border-slate-400 p-2">{{ __('inventory.movement_type') }}</th>
                <th class="border border-slate-400 p-2">{{ __('inventory.document_number') }}</th>
                <th class="border border-slate-400 p-2">{{ __('common.store') }}</th>
                <th class="border border-slate-400 p-2 text-emerald-800">{{ __('inventory.inbound_plus') }}</th>
                <th class="border border-slate-400 p-2 text-rose-800">{{ __('inventory.outbound_minus') }}</th>
                <th class="border border-slate-400 p-2 text-slate-950 font-black">{{ __('inventory.balance_after_movement') }}</th>
                <th class="border border-slate-400 p-2">{{ __('common.user') }}</th>
                <th class="border border-slate-400 p-2">{{ __('inventory.notes_and_statement') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-300">
            @php
                $inboundTypes = [
                    'purchase_in', 'stock_deposit_in', 'stock_adjustment_in',
                    'cancellation_in', 'transfer_in', 'sales_return_in', 'purchase_restore_in'
                ];
            @endphp
            @forelse($movements as $idx => $row)
            @php
                $isIn = in_array($row->movement_type, $inboundTypes);
                $typeLabel = match ($row->movement_type) {
                    'sales_out'            => '🛒 ' . __('invoices.sales_invoice'),
                    'purchase_in'          => '📦 ' . __('purchases.title'),
                    'purchase_cancel_out'  => '🚫 ' . __('purchases.cancel_purchase'),
                    'purchase_restore_in'  => '♻️ ' . __('purchases.title'),
                    'cancellation_in'      => '↩️ ' . __('invoices.cancel_invoice'),
                    'stock_adjustment_in'  => '⚖️ ' . __('inventory.movement_adj_in'),
                    'stock_adjustment_out' => '⚖️ ' . __('inventory.movement_adj_out'),
                    'stock_deposit_in'     => '📥 ' . __('inventory.movement_deposit'),
                    'transfer_in'          => '🚚 ' . __('inventory.transfers_title'),
                    'transfer_out'         => '🚚 ' . __('inventory.transfers_title'),
                    'sales_return_in'      => '🔁 ' . __('returns.sales_return'),
                    default                => $row->movement_type,
                };
            @endphp
            <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-slate-50' }}">
                <td class="border border-slate-400 p-1.5 text-center font-mono text-[10px]">{{ $idx + 1 }}</td>
                <td class="border border-slate-400 p-1.5 font-mono">{{ $row->created_at->format('Y-m-d H:i') }}</td>
                <td class="border border-slate-400 p-1.5 font-bold">{{ $typeLabel }}</td>
                <td class="border border-slate-400 p-1.5 font-mono font-bold">{{ $row->document_number ?: '—' }}</td>
                <td class="border border-slate-400 p-1.5">{{ $row->store?->name ?? __('common.main_store_default') }}</td>
                <td class="border border-slate-400 p-1.5 font-mono font-bold text-emerald-800">
                    {{ $isIn ? '+' . number_format($row->quantity, 3) : '—' }}
                </td>
                <td class="border border-slate-400 p-1.5 font-mono font-bold text-rose-800">
                    {{ !$isIn ? '-' . number_format($row->quantity, 3) : '—' }}
                </td>
                <td class="border border-slate-400 p-1.5 font-mono font-black text-slate-950">
                    {{ number_format($row->stock_after, 3) }}
                </td>
                <td class="border border-slate-400 p-1.5 text-[10px]">{{ $row->user?->name ?? __('common.system') }}</td>
                <td class="border border-slate-400 p-1.5 text-[10px] text-slate-600">{{ $row->notes ?: '—' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="10" class="border border-slate-400 p-6 text-center text-slate-500 font-bold">
                    {{ __('inventory.no_movements_period') }}
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Official Signatures Box -->
    <div class="page-break-inside-avoid mt-8 pt-4 border-t-2 border-slate-900">
        <div class="grid grid-cols-3 gap-6 text-center text-xs font-bold text-slate-900">
            <div>
                <p class="mb-10">{{ __('inventory.storekeeper_sig') }}</p>
                <p class="text-[10px] text-slate-400">{{ __('inventory.signature_line') }}</p>
            </div>
            <div>
                <p class="mb-10">{{ __('inventory.financial_accountant_sig') }}</p>
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
