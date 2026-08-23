<div class="space-y-6">
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900/60 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 backdrop-blur-sm shadow-sm">
        <div>
            <h2 class="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>مرحباً بك في لوحة تحكم {{ \App\Models\Setting::get('company_name', config('app.name', 'ERP')) }}</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">نظرة عامة على المبيعات، رصيد الخزينة، المخزون، وحسابات العملاء</p>
        </div>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 w-full sm:w-auto">
            <a href="{{ route('invoices.create') }}" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2 transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>فاتورة بيع سريعة (POS)</span>
            </a>
            <a href="{{ route('purchases.create') }}" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 flex items-center justify-center gap-2 transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                <span>فاتورة شراء (توريد)</span>
            </a>
        </div>
    </div>

    <!-- 4 Key Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Today Sales -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-5 rounded-2xl shadow-sm relative overflow-hidden group hover:border-emerald-500/40 transition-all">
            <div class="absolute -top-10 -left-10 w-28 h-28 bg-emerald-500/10 rounded-full blur-2xl group-hover:bg-emerald-500/20 transition-all"></div>
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">مبيعات اليوم</span>
                <span class="p-2 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">{{ number_format($todaySales, 2) }} <span class="text-xs text-emerald-600 dark:text-emerald-400">ج.م</span></div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $todayInvoicesCount }} فاتورة معتمدة اليوم</div>
            </div>
        </div>

        <!-- Monthly Gross Profit -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-5 rounded-2xl shadow-sm relative overflow-hidden group hover:border-teal-500/40 transition-all">
            <div class="absolute -top-10 -left-10 w-28 h-28 bg-teal-500/10 rounded-full blur-2xl group-hover:bg-teal-500/20 transition-all"></div>
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">مجمل أرباح الشهر</span>
                <span class="p-2 rounded-xl bg-teal-500/10 text-teal-600 dark:text-teal-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl font-black text-teal-600 dark:text-teal-400 font-mono">{{ number_format($monthlyGrossProfit, 2) }} <span class="text-xs text-teal-500 dark:text-teal-300">ج.م</span></div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">هامش ربح: <span class="text-slate-900 dark:text-white font-bold font-mono">{{ $monthlyMargin }}%</span></div>
            </div>
        </div>

        <!-- Outstanding Debts -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-5 rounded-2xl shadow-sm relative overflow-hidden group hover:border-amber-500/40 transition-all">
            <div class="absolute -top-10 -left-10 w-28 h-28 bg-amber-500/10 rounded-full blur-2xl group-hover:bg-amber-500/20 transition-all"></div>
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">إجمالي ديون العملاء (الآجل)</span>
                <span class="p-2 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl font-black text-amber-600 dark:text-amber-400 font-mono">{{ number_format($totalCustomersDebt, 2) }} <span class="text-xs text-amber-500 dark:text-amber-300">ج.م</span></div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">مستحقات واجبة التحصيل</div>
            </div>
        </div>

        <!-- Monthly Sales -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-5 rounded-2xl shadow-sm relative overflow-hidden group hover:border-indigo-500/40 transition-all">
            <div class="absolute -top-10 -left-10 w-28 h-28 bg-indigo-500/10 rounded-full blur-2xl group-hover:bg-indigo-500/20 transition-all"></div>
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">مبيعات الشهر الحالي</span>
                <span class="p-2 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl font-black text-indigo-600 dark:text-indigo-300 font-mono">{{ number_format($monthlySales, 2) }} <span class="text-xs text-indigo-500 dark:text-indigo-400">ج.م</span></div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">إجمالي تعاملات الشهر</div>
            </div>
        </div>
    </div>

    <!-- 2 Column Section: Recent Invoices & Low Stock Alert -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recent Invoices (2 Cols) -->
        <div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span>آخر فواتير المبيعات الصادرة</span>
                </h3>
                <a href="{{ route('invoices.index') }}" class="text-xs text-emerald-600 dark:text-emerald-400 hover:underline font-bold">عرض الكل ←</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950/60 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="p-3">رقم الفاتورة</th>
                            <th class="p-3">العميل</th>
                            <th class="p-3">التاريخ</th>
                            <th class="p-3">الإجمالي</th>
                            <th class="p-3">الحالة</th>
                            <th class="p-3 text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60">
                        @forelse($recentInvoices as $inv)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="p-3 font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ $inv->invoice_number }}</td>
                            <td class="p-3 font-bold text-slate-800 dark:text-slate-200">{{ $inv->customer?->name ?? 'عميل نقدي' }}</td>
                            <td class="p-3 text-slate-500 dark:text-slate-400 font-mono">{{ $inv->invoice_date?->format('Y-m-d') ?? '—' }}</td>
                            <td class="p-3 font-mono font-bold text-slate-900 dark:text-white">{{ number_format($inv->net_total, 2) }} ج.م</td>
                            <td class="p-3">
                                @if($inv->status === 'cancelled')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">ملغاة</span>
                                @elseif($inv->payment_status === 'paid')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">مدفوعة</span>
                                @elseif($inv->payment_status === 'partially_paid')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">جزئي</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">آجل</span>
                                @endif
                            </td>
                            <td class="p-3 text-center">
                                <a href="{{ route('invoices.show', $inv->id) }}" class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold text-[11px] transition-colors border border-slate-200 dark:border-slate-700">
                                    معاينة / طباعة
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="p-6 text-center text-slate-400">لا توجد فواتير مسجلة حتى الآن</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Low Stock Alerts (1 Col) -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                    <span>تنبيهات النواقص بالمخزن</span>
                </h3>
                <a href="{{ route('purchases.reorder') }}" class="text-xs text-amber-600 dark:text-amber-400 hover:underline font-bold">مساعد المشتريات ←</a>
            </div>

            <div class="p-3 divide-y divide-slate-200 dark:divide-slate-800/60">
                @forelse($lowStockItems as $item)
                <div class="py-3 flex items-center justify-between">
                    <div>
                        <div class="font-bold text-slate-800 dark:text-slate-200 text-xs">{{ $item->name }}</div>
                        <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono mt-0.5">كود: {{ $item->code }}</div>
                    </div>
                    <div class="text-left">
                        <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                            {{ number_format($item->current_stock, 2) }} {{ $item->unit }}
                        </span>
                        <div class="text-[10px] text-slate-400 mt-0.5">الحد الأدنى: {{ number_format($item->min_stock_level, 2) }}</div>
                    </div>
                </div>
                @empty
                <div class="p-6 text-center text-slate-400 text-xs">
                    جميع الأصناف متوفرة فوق الحد الأدنى 👍
                </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- 📈 INTERACTIVE EXECUTIVE ANALYTICS (Charts & Peak Hours) -->
    <!-- ======================================================== -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- 7-Day Sales Trend Bar Chart (2 Cols) -->
        <div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>📊 حركة ومبيعات آخر 7 أيام</span>
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">معدل البيع اليومي وعدد الفواتير الصادرة</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-left">
                        <span class="text-[10px] text-slate-400 font-bold block">متوسط قيمة الفاتورة (Basket Size):</span>
                        <span class="text-sm font-black font-mono text-emerald-600 dark:text-emerald-400" dir="ltr">
                            {{ number_format((float)$analytics['period']['basket_size'], 2) }} ج.م
                        </span>
                    </div>
                </div>
            </div>

            @php
                $maxDailySales = max(1, collect($analytics['daily_trend'])->max('sales'));
            @endphp
            <!-- Visual CSS Bar Chart -->
            <div class="grid grid-cols-7 gap-2 items-end h-44 pt-6 pb-2">
                @foreach($analytics['daily_trend'] as $day)
                @php
                    $barHeight = $maxDailySales > 0 ? max(8, round(($day['sales'] / $maxDailySales) * 100)) : 8;
                @endphp
                <div class="flex flex-col items-center gap-1.5 h-full justify-end group relative">
                    <!-- Tooltip -->
                    <div class="absolute -top-10 opacity-0 group-hover:opacity-100 transition-opacity bg-slate-900 text-white text-[10px] font-mono px-2 py-1 rounded-lg pointer-events-none whitespace-nowrap shadow-lg z-10">
                        {{ $day['sales_formatted'] }} ({{ $day['invoices'] }} فاتورة)
                    </div>
                    
                    <span class="text-[10px] font-mono font-bold text-slate-600 dark:text-slate-300">
                        {{ $day['sales'] > 0 ? number_format($day['sales'], 0) : '0' }}
                    </span>

                    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-xl overflow-hidden flex items-end h-28">
                        <div 
                            style="height: {{ $barHeight }}%"
                            class="w-full rounded-xl transition-all duration-500 {{ $loop->last ? 'bg-gradient-to-t from-emerald-600 to-teal-500 shadow-md shadow-emerald-500/30' : 'bg-gradient-to-t from-amber-500 to-amber-400 group-hover:from-amber-600' }}"
                        ></div>
                    </div>

                    <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 truncate w-full text-center">
                        {{ $day['label'] }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Peak Hours & Payment Split (1 Col) -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-sm space-y-4 flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>⚡ توزيع ساعات الذروة (24 ساعة)</span>
                    </h3>
                    @if($analytics['peak_hour'] && bccomp((string)$analytics['peak_hour']['sales'], '0.000', 3) > 0)
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-lg bg-amber-500/20 text-amber-600 dark:text-amber-400">
                        الذروة: {{ $analytics['peak_hour']['label'] }}
                    </span>
                    @endif
                </div>

                <!-- 24-Hour Micro Heatmap Grid -->
                <div class="space-y-1.5">
                    <div class="grid grid-cols-12 gap-1 pt-1">
                        @foreach($analytics['hourly_sales'] as $hData)
                        <div class="group relative flex flex-col items-center">
                            <!-- Tooltip -->
                            <div class="absolute -top-12 opacity-0 group-hover:opacity-100 transition-opacity bg-slate-900 text-white text-[10px] font-mono px-2 py-1 rounded-lg pointer-events-none whitespace-nowrap shadow-xl z-20">
                                <strong>{{ $hData['label'] }}</strong>: {{ $hData['sales_formatted'] }} ({{ $hData['invoices'] }} فاتورة)
                            </div>

                            <div 
                                class="w-full h-8 rounded-lg transition-all cursor-pointer flex items-end justify-center overflow-hidden border border-slate-200/50 dark:border-slate-800 {{ $hData['intensity'] >= 75 ? 'bg-amber-500 text-white shadow-sm shadow-amber-500/30' : ($hData['intensity'] >= 40 ? 'bg-amber-400/80 text-slate-900' : ($hData['intensity'] > 0 ? 'bg-amber-200/70 dark:bg-amber-950/60 text-slate-600' : 'bg-slate-100 dark:bg-slate-800/60')) }}"
                                title="{{ $hData['label'] }}: {{ $hData['sales_formatted'] }}"
                            >
                                @if($hData['intensity'] > 0)
                                <div class="w-full bg-amber-600/40" style="height: {{ max(15, $hData['intensity']) }}%"></div>
                                @endif
                            </div>
                            <span class="text-[8px] font-mono text-slate-400 mt-0.5">{{ $hData['hour'] }}</span>
                        </div>
                        @endforeach
                    </div>
                    <div class="flex items-center justify-between text-[9px] text-slate-400 font-mono px-1">
                        <span>12ص (منتصف الليل)</span>
                        <span>12م (ظهراً)</span>
                        <span>11م (مساءً)</span>
                    </div>
                </div>

                @if($analytics['peak_hour'] && bccomp((string)$analytics['peak_hour']['sales'], '0.000', 3) > 0)
                <div class="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-between">
                    <span class="text-[11px] font-bold text-amber-700 dark:text-amber-300">أعلى ساعة مبيعات: {{ $analytics['peak_hour']['label'] }}</span>
                    <span class="text-xs font-black font-mono text-amber-600 dark:text-amber-400" dir="ltr">{{ $analytics['peak_hour']['sales_formatted'] }} ({{ $analytics['peak_hour']['invoices'] }} فواتير)</span>
                </div>
                @endif
            </div>

            <!-- Payment Methods Split -->
            <div class="space-y-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-2">💳 توزيع طرق التحصيل والدفع:</span>
                <div class="space-y-1.5">
                    @foreach($analytics['payment_distribution'] as $pMethod)
                    @if($pMethod['percentage'] > 0)
                    <div>
                        <div class="flex items-center justify-between text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-0.5">
                            <span>{{ $pMethod['label'] }}</span>
                            <span class="font-mono text-slate-900 dark:text-white">{{ $pMethod['percentage'] }}% ({{ number_format((float)$pMethod['amount'], 0) }} ج.م)</span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-slate-800 h-1.5 rounded-full overflow-hidden">
                            <div 
                                class="h-full rounded-full {{ $pMethod['key'] === 'cash' ? 'bg-emerald-500' : ($pMethod['key'] === 'instapay' ? 'bg-purple-600' : ($pMethod['key'] === 'e_wallet' ? 'bg-amber-500' : 'bg-cyan-500')) }}" 
                                style="width: {{ $pMethod['percentage'] }}%"
                            ></div>
                        </div>
                    </div>
                    @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
