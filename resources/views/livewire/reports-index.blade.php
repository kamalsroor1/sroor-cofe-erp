<div class="space-y-6">
    <!-- Header & Period Filter Toolbar -->
    <div class="bg-white dark:bg-slate-900 p-4 sm:p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white flex items-center gap-2 font-tajawal">
                    <span>📈 التقارير المالية ومجمل الأرباح ومؤشرات المبيعات</span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    لوحة تحكم إدارية متكاملة لمتابعة المبيعات، الإيرادات، التكاليف، أداء الفروع وعربيات التوزيع، وحسابات الأرباح
                </p>
            </div>

            <!-- Store Selector & Print Button -->
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-2xl px-3 py-2 shrink-0">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">🏬 نطاق الفرع:</span>
                    <select wire:model.live="selectedStoreId" class="bg-transparent text-xs font-black text-slate-900 dark:text-white focus:outline-none cursor-pointer [&>option]:bg-white [&>option]:text-slate-900 dark:[&>option]:bg-slate-900 dark:[&>option]:text-slate-100">
                        <option class="bg-white dark:bg-slate-900 text-slate-900 dark:text-white" value="all">🏢 إجمالي كافة الفروع والمخازن وعربات التوزيع</option>
                        @foreach($stores as $st)
                        <option class="bg-white dark:bg-slate-900 text-slate-900 dark:text-white" value="{{ $st->id }}">
                            @if($st->type === 'wholesale_van') 🚚 @elseif($st->type === 'main_warehouse') 🏢 @else 🏬 @endif
                            {{ $st->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <a 
                    href="{{ route('reports.print', ['tab' => $activeTab, 'store_id' => $selectedStoreId, 'from' => $fromDate, 'to' => $toDate]) }}" 
                    target="_blank"
                    class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-2xl text-xs font-bold shadow-lg shadow-emerald-600/30 flex items-center gap-1.5 transition-all cursor-pointer shrink-0"
                >
                    <span>🖨️ طباعة التقرير A4</span>
                </a>
            </div>
        </div>

        <!-- Date Filters Buttons & Custom Date Pickers -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
            <!-- Preset Period Buttons -->
            <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                <button 
                    type="button"
                    wire:click="setFilter('today')" 
                    class="px-3.5 py-2 rounded-xl text-xs font-black transition-all cursor-pointer {{ $dateFilter === 'today' ? 'bg-amber-600 text-white shadow-md shadow-amber-600/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200' }}"
                >
                    ☀️ مبيعات اليوم
                </button>
                <button 
                    type="button"
                    wire:click="setFilter('this_week')" 
                    class="px-3.5 py-2 rounded-xl text-xs font-black transition-all cursor-pointer {{ $dateFilter === 'this_week' ? 'bg-amber-600 text-white shadow-md shadow-amber-600/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200' }}"
                >
                    📅 هذا الأسبوع
                </button>
                <button 
                    type="button"
                    wire:click="setFilter('this_month')" 
                    class="px-3.5 py-2 rounded-xl text-xs font-black transition-all cursor-pointer {{ $dateFilter === 'this_month' ? 'bg-amber-600 text-white shadow-md shadow-amber-600/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200' }}"
                >
                    🗓️ هذا الشهر
                </button>
                <button 
                    type="button"
                    wire:click="setFilter('this_year')" 
                    class="px-3.5 py-2 rounded-xl text-xs font-black transition-all cursor-pointer {{ $dateFilter === 'this_year' ? 'bg-amber-600 text-white shadow-md shadow-amber-600/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200' }}"
                >
                    📊 هذا العام ({{ date('Y') }})
                </button>
                <button 
                    type="button"
                    wire:click="setFilter('custom')" 
                    class="px-3.5 py-2 rounded-xl text-xs font-black transition-all cursor-pointer {{ $dateFilter === 'custom' ? 'bg-amber-600 text-white shadow-md shadow-amber-600/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200' }}"
                >
                    🎯 فترة مخصصة
                </button>
            </div>

            <!-- Custom Date Inputs -->
            <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-950 p-1.5 rounded-2xl border border-slate-300 dark:border-slate-700 text-xs">
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 shrink-0">📅 من:</span>
                    <x-datepicker wire:model.live="fromDate" class="!h-8 !w-32 !py-1 !px-2 !text-xs" placeholder="من تاريخ" />
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 shrink-0">إلى:</span>
                    <x-datepicker wire:model.live="toDate" class="!h-8 !w-32 !py-1 !px-2 !text-xs" placeholder="إلى تاريخ" />
                </div>
            </div>
        </div>
    </div>

    <!-- 📑 Navigation Tabs Bar -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-slate-200 dark:border-slate-800">
        <button 
            type="button"
            wire:click="setTab('sales')" 
            class="px-4 py-3 rounded-2xl text-xs sm:text-sm font-black whitespace-nowrap transition-all cursor-pointer flex items-center gap-2 {{ $activeTab === 'sales' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950 shadow-md' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-900' }}"
        >
            <span>📊 مبيعات وإيرادات الفترة</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono {{ $activeTab === 'sales' ? 'bg-amber-500 text-white' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                {{ $periodic['invoice_count'] }} فواتير
            </span>
        </button>

        <button 
            type="button"
            wire:click="setTab('items')" 
            class="px-4 py-3 rounded-2xl text-xs sm:text-sm font-black whitespace-nowrap transition-all cursor-pointer flex items-center gap-2 {{ $activeTab === 'items' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950 shadow-md' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-900' }}"
        >
            <span>📦 حركة وربحية الأصناف</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono {{ $activeTab === 'items' ? 'bg-amber-500 text-white' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                {{ count($itemProfits) }} صنف
            </span>
        </button>

        <button 
            type="button"
            wire:click="setTab('stores')" 
            class="px-4 py-3 rounded-2xl text-xs sm:text-sm font-black whitespace-nowrap transition-all cursor-pointer flex items-center gap-2 {{ $activeTab === 'stores' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950 shadow-md' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-900' }}"
        >
            <span>🏬 مقارنة أداء الفروع والعربيات</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono {{ $activeTab === 'stores' ? 'bg-amber-500 text-white' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                {{ count($storeBreakdown) }} فرع
            </span>
        </button>

        <button 
            type="button"
            wire:click="setTab('customers')" 
            class="px-4 py-3 rounded-2xl text-xs sm:text-sm font-black whitespace-nowrap transition-all cursor-pointer flex items-center gap-2 {{ $activeTab === 'customers' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950 shadow-md' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-900' }}"
        >
            <span>👥 مبيعات وحسابات العملاء</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono {{ $activeTab === 'customers' ? 'bg-amber-500 text-white' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                {{ count($customerSales) }} عميل
            </span>
        </button>

        <button 
            type="button"
            wire:click="setTab('expenses')" 
            class="px-4 py-3 rounded-2xl text-xs sm:text-sm font-black whitespace-nowrap transition-all cursor-pointer flex items-center gap-2 {{ $activeTab === 'expenses' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950 shadow-md' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-900' }}"
        >
            <span>💸 المصروفات وصافي الدخل</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono {{ $activeTab === 'expenses' ? 'bg-amber-500 text-white' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                {{ number_format($totalExpenses, 0) }} ج.م
            </span>
        </button>

        <button 
            type="button"
            wire:click="setTab('inventory')" 
            class="px-4 py-3 rounded-2xl text-xs sm:text-sm font-black whitespace-nowrap transition-all cursor-pointer flex items-center gap-2 {{ $activeTab === 'inventory' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950 shadow-md' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-900' }}"
        >
            <span>🏢 تقييم بضاعة المخزن</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono {{ $activeTab === 'inventory' ? 'bg-amber-500 text-white' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                {{ count($allItems) }} صنف
            </span>
        </button>

        <button 
            type="button"
            wire:click="setTab('treasury')" 
            class="px-4 py-3 rounded-2xl text-xs sm:text-sm font-black whitespace-nowrap transition-all cursor-pointer flex items-center gap-2 {{ $activeTab === 'treasury' ? 'bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-md' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-900' }}"
        >
            <span>💰 الخزائن والسيولة والتحويلات</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono {{ $activeTab === 'treasury' ? 'bg-white text-emerald-800 font-black' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                {{ number_format($treasuryData['total_liquidity'], 0) }} ج.م
            </span>
        </button>

        <button 
            type="button"
            wire:click="setTab('abc')" 
            class="px-4 py-3 rounded-2xl text-xs sm:text-sm font-black whitespace-nowrap transition-all cursor-pointer flex items-center gap-2 {{ $activeTab === 'abc' ? 'bg-gradient-to-r from-purple-600 to-indigo-600 text-white shadow-md' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-900' }}"
        >
            <span>📊 حركة البضاعة (ABC)</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono {{ $activeTab === 'abc' ? 'bg-white text-purple-800 font-black' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                {{ $abcData['total_items_count'] }} صنف
            </span>
        </button>

        <button 
            type="button"
            wire:click="setTab('pnl')" 
            class="px-4 py-3 rounded-2xl text-xs sm:text-sm font-black whitespace-nowrap transition-all cursor-pointer flex items-center gap-2 {{ $activeTab === 'pnl' ? 'bg-gradient-to-r from-blue-600 to-cyan-600 text-white shadow-md' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-900' }}"
        >
            <span>🏢 أرباح وخسائر الفروع (P&L)</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono {{ $activeTab === 'pnl' ? 'bg-white text-blue-800 font-black' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                {{ count($pnlData['stores']) }} فرع
            </span>
        </button>
    </div>

    <!-- ======================================================== -->
    <!-- 📊 TAB 1: مؤشرات المبيعات والإيرادات (Sales Dashboard)     -->
    <!-- ======================================================== -->
    @if($activeTab === 'sales')
    <div class="space-y-6">
        <!-- 6 Main KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3.5">
            <!-- 1. Total Sales -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-3xl shadow-sm space-y-2">
                <div class="flex items-center justify-between text-xs font-bold text-slate-500 dark:text-slate-400">
                    <span>إجمالي المبيعات</span>
                    <span class="text-emerald-500">💰</span>
                </div>
                <div class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-mono">
                    {{ number_format($periodic['total_sales'], 2) }}
                    <span class="text-xs font-normal text-emerald-600">ج.م</span>
                </div>
                <div class="text-[11px] text-slate-400 font-bold">
                    {{ $periodic['invoice_count'] }} فاتورة معتمدة
                </div>
            </div>

            <!-- 2. Cash Collected -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-3xl shadow-sm space-y-2">
                <div class="flex items-center justify-between text-xs font-bold text-slate-500 dark:text-slate-400">
                    <span>التحصيل النقدي</span>
                    <span class="text-indigo-500">💵</span>
                </div>
                <div class="text-xl sm:text-2xl font-black text-indigo-600 dark:text-indigo-400 font-mono">
                    {{ number_format($periodic['total_paid'], 2) }}
                    <span class="text-xs font-normal">ج.م</span>
                </div>
                <div class="text-[11px] text-slate-400 font-bold">
                    تم سدادها واستلامها في الدرج
                </div>
            </div>

            <!-- 3. Credit / Receivables in Period -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-3xl shadow-sm space-y-2">
                <div class="flex items-center justify-between text-xs font-bold text-slate-500 dark:text-slate-400">
                    <span>المبيعات الآجلة (المتبقي)</span>
                    <span class="text-amber-500">⏳</span>
                </div>
                <div class="text-xl sm:text-2xl font-black text-amber-600 dark:text-amber-400 font-mono">
                    {{ number_format($periodic['total_remaining'], 2) }}
                    <span class="text-xs font-normal">ج.م</span>
                </div>
                <div class="text-[11px] text-slate-400 font-bold">
                    متبقي فواتير الفترة (المديونية الكلية: {{ number_format($periodic['total_customer_debt'], 2) }} ج.م)
                </div>
            </div>

            <!-- 4. COGS -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-3xl shadow-sm space-y-2">
                <div class="flex items-center justify-between text-xs font-bold text-slate-500 dark:text-slate-400">
                    <span>تكلفة البضاعة المباعة</span>
                    <span class="text-rose-500">📦</span>
                </div>
                <div class="text-xl sm:text-2xl font-black text-rose-600 dark:text-rose-400 font-mono">
                    {{ number_format($periodic['total_cost'], 2) }}
                    <span class="text-xs font-normal">ج.م</span>
                </div>
                <div class="text-[11px] text-slate-400 font-bold">
                    تكلفة شراء الأصناف المباعة
                </div>
            </div>

            <!-- 5. Gross Profit -->
            <div class="bg-white dark:bg-slate-900 border border-emerald-500/40 p-4 rounded-3xl shadow-sm space-y-2 bg-gradient-to-b from-white dark:from-slate-900 to-emerald-500/5">
                <div class="flex items-center justify-between text-xs font-bold text-emerald-600 dark:text-emerald-400">
                    <span>مجمل أرباح المبيعات</span>
                    <span>📈</span>
                </div>
                <div class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono">
                    {{ number_format($periodic['gross_profit'], 2) }}
                    <span class="text-xs font-normal">ج.م</span>
                </div>
                <div class="text-[11px] text-emerald-700 dark:text-emerald-300 font-bold font-mono">
                    هامش الربح: {{ $periodic['margin_percentage'] }}%
                </div>
            </div>

            <!-- 6. Net Profit After Expenses -->
            <div class="bg-white dark:bg-slate-900 border border-indigo-500/40 p-4 rounded-3xl shadow-sm space-y-2 bg-gradient-to-b from-white dark:from-slate-900 to-indigo-500/5">
                <div class="flex items-center justify-between text-xs font-bold text-indigo-600 dark:text-indigo-400">
                    <span>صافي دخل النشاط</span>
                    <span>🏆</span>
                </div>
                <div class="text-xl sm:text-2xl font-black {{ bccomp($netProfitAfterExpenses, '0.000', 3) >= 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-rose-600' }} font-mono">
                    {{ number_format($netProfitAfterExpenses, 2) }}
                    <span class="text-xs font-normal">ج.م</span>
                </div>
                <div class="text-[11px] text-slate-400 font-bold">
                    بعد خصم مصروفات ({{ number_format($totalExpenses, 2) }} ج.م)
                </div>
            </div>
        </div>

        <!-- Quick Store Sales Summary Card -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm sm:text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🏬 مقارنة أداء ومبيعات الفروع وعربيات التوزيع</span>
                </h3>
                <button wire:click="setTab('stores')" class="text-xs font-bold text-amber-600 hover:underline cursor-pointer">
                    عرض المقارنة التفصيلية ←
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($storeBreakdown as $sb)
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <div class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                            <span>{{ $sb['store']->type === 'wholesale_van' ? '🚚' : ($sb['store']->is_main ? '🏢' : '🏬') }}</span>
                            <span>{{ $sb['store']->name }}</span>
                        </div>
                        <div class="text-[11px] text-slate-400 font-bold mt-1">
                            {{ $sb['invoice_count'] }} فاتورة | مساهمة: {{ $sb['share_pct'] }}%
                        </div>
                    </div>
                    <div class="text-left">
                        <div class="text-sm font-black font-mono text-emerald-600 dark:text-emerald-400">
                            {{ number_format($sb['total_sales'], 2) }} ج.م
                        </div>
                        <div class="text-[10px] text-slate-500 font-bold">
                            ربح: {{ number_format($sb['gross_profit'], 2) }} ج.م
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- ======================================================== -->
    <!-- 📦 TAB 2: حركة وربحية الأصناف (Items Profitability)        -->
    <!-- ======================================================== -->
    @if($activeTab === 'items')
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm">
        <div class="p-4 sm:p-5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <h3 class="text-sm sm:text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>📦 تحليل مبيعات وربحية الأصناف مرتبة حسب الأعلى إيراداً</span>
            </h3>
            <span class="text-xs text-slate-500">{{ count($itemProfits) }} صنف مباع</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 font-bold border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="p-3.5">#</th>
                        <th class="p-3.5">الصنف</th>
                        <th class="p-3.5 text-center">الكمية المباعة</th>
                        <th class="p-3.5">إجمالي المبيعات (الإيراد)</th>
                        <th class="p-3.5">تكلفة الشراء (COGS)</th>
                        <th class="p-3.5">مجمل الربح</th>
                        <th class="p-3.5 text-center">هامش الربح %</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse($itemProfits as $index => $row)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="p-3.5 font-bold font-mono text-slate-400">{{ $index + 1 }}</td>
                        <td class="p-3.5">
                            <span class="font-extrabold text-slate-900 dark:text-white text-xs sm:text-sm">{{ $row['item']->name ?? 'صنف غير معروف' }}</span>
                            @if($row['item']?->code)
                            <span class="block text-[10px] text-slate-400 font-mono">كود: {{ $row['item']->code }}</span>
                            @endif
                        </td>
                        <td class="p-3.5 text-center font-black font-mono text-slate-900 dark:text-white">
                            {{ number_format($row['total_qty'], 2) }} {{ $row['item']?->unit }}
                        </td>
                        <td class="p-3.5 font-black font-mono text-emerald-600 dark:text-emerald-400">
                            {{ number_format($row['total_revenue'], 2) }} ج.م
                        </td>
                        <td class="p-3.5 font-bold font-mono text-rose-600 dark:text-rose-400">
                            {{ number_format($row['total_cogs'], 2) }} ج.م
                        </td>
                        <td class="p-3.5 font-black font-mono text-slate-900 dark:text-white">
                            {{ number_format($row['profit'], 2) }} ج.م
                        </td>
                        <td class="p-3.5 text-center">
                            <span class="px-2.5 py-1 rounded-xl text-[11px] font-black font-mono {{ (float)$row['margin'] >= 20 ? 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-600 border border-amber-500/20' }}">
                                {{ $row['margin'] }}%
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-slate-400">لا توجد مبيعات أصناف مسجلة في هذه الفترة</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- ======================================================== -->
    <!-- 🏬 TAB 3: مقارنة أداء الفروع والتوزيع (Stores Performance)  -->
    <!-- ======================================================== -->
    @if($activeTab === 'stores')
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm">
        <div class="p-4 sm:p-5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <h3 class="text-sm sm:text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>🏬 مقارنة الأداء والمبيعات عبر الفروع وعربات التوزيع</span>
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 font-bold border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="p-3.5">الفرع / النقطة</th>
                        <th class="p-3.5 text-center">النوع</th>
                        <th class="p-3.5 text-center">عدد الفواتير</th>
                        <th class="p-3.5">إجمالي المبيعات</th>
                        <th class="p-3.5">التحصيل النقدي</th>
                        <th class="p-3.5">الآجل (المتبقي)</th>
                        <th class="p-3.5">مجمل الربح</th>
                        <th class="p-3.5 text-center">هامش الربح %</th>
                        <th class="p-3.5 text-center">نسبة المساهمة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @foreach($storeBreakdown as $sb)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="p-3.5 font-black text-slate-900 dark:text-white text-xs sm:text-sm">
                            {{ $sb['store']->name }}
                        </td>
                        <td class="p-3.5 text-center">
                            <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-bold {{ $sb['store']->type === 'wholesale_van' ? 'bg-amber-500/10 text-amber-600' : ($sb['store']->is_main ? 'bg-indigo-500/10 text-indigo-600' : 'bg-emerald-500/10 text-emerald-600') }}">
                                {{ $sb['store']->type === 'wholesale_van' ? '🚚 سيارة توزيع' : ($sb['store']->is_main ? '🏢 رئيسي' : '🏬 فرع') }}
                            </span>
                        </td>
                        <td class="p-3.5 text-center font-bold font-mono">{{ $sb['invoice_count'] }}</td>
                        <td class="p-3.5 font-black font-mono text-emerald-600 dark:text-emerald-400">
                            {{ number_format($sb['total_sales'], 2) }} ج.م
                        </td>
                        <td class="p-3.5 font-bold font-mono text-indigo-600 dark:text-indigo-400">
                            {{ number_format($sb['total_paid'], 2) }} ج.م
                        </td>
                        <td class="p-3.5 font-bold font-mono text-amber-600 dark:text-amber-400">
                            {{ number_format($sb['total_remaining'], 2) }} ج.م
                        </td>
                        <td class="p-3.5 font-black font-mono text-slate-900 dark:text-white">
                            {{ number_format($sb['gross_profit'], 2) }} ج.م
                        </td>
                        <td class="p-3.5 text-center font-black font-mono text-emerald-600">
                            {{ $sb['margin'] }}%
                        </td>
                        <td class="p-3.5 text-center font-black font-mono text-indigo-600">
                            {{ $sb['share_pct'] }}%
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- ======================================================== -->
    <!-- 👥 TAB 4: حسابات وديون العملاء (Customers Analytics)        -->
    <!-- ======================================================== -->
    @if($activeTab === 'customers')
    <div class="space-y-4">
        <!-- Customer Debt Banner -->
        <div class="bg-gradient-to-r from-amber-600 to-amber-500 rounded-3xl p-5 text-white shadow-lg flex flex-col sm:flex-row items-center justify-between gap-4">
            <div>
                <h3 class="text-base sm:text-lg font-black">إجمالي مديونيات وحسابات كافة العملاء المسجلة بالنظام</h3>
                <p class="text-xs text-amber-100 mt-0.5">مجموع أرصدة الذمم المستحقة على العملاء حتى هذه اللحظة ({{ count($debtCustomersList) }} عميل عليهم مديونية)</p>
            </div>
            <div class="text-2xl sm:text-3xl font-black font-mono bg-white/20 px-4 py-2 rounded-2xl backdrop-blur-sm">
                {{ number_format($totalAllCustomersDebt, 2) }} <span class="text-xs font-normal">ج.م</span>
            </div>
        </div>

        <!-- ⚠️ Quick Debtors Cards -->
        @if(count($debtCustomersList) > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($debtCustomersList as $dc)
            <div class="bg-amber-500/10 dark:bg-amber-500/5 border border-amber-500/30 p-4 rounded-2xl flex items-center justify-between">
                <div class="space-y-0.5">
                    <div class="text-xs font-black text-amber-900 dark:text-amber-300 flex items-center gap-1.5">
                        <span>👤</span>
                        <span>{{ $dc->name }}</span>
                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono" dir="ltr">
                        {{ $dc->phone ?: 'بدون هاتف' }}
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-xs font-bold text-slate-500">الرصيد المتبقي:</div>
                    <div class="text-base font-black text-amber-600 dark:text-amber-400 font-mono">
                        {{ number_format($dc->current_balance, 2) }} ج.م
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm">
            <div class="p-4 sm:p-5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <h3 class="text-sm sm:text-base font-black text-slate-900 dark:text-white">
                    👥 مبيعات وتعاملات كافة العملاء خلال الفترة ({{ count($customerSales) }} عميل)
                </h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 font-bold border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="p-3.5">العميل</th>
                            <th class="p-3.5">رقم الهاتف</th>
                            <th class="p-3.5 text-center">عدد الفواتير</th>
                            <th class="p-3.5">إجمالي المشتريات</th>
                            <th class="p-3.5">المسدد نقداً</th>
                            <th class="p-3.5">المتبقي بالفترة</th>
                            <th class="p-3.5">الرصيد الإجمالي الحالي</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse($customerSales as $cs)
                        @php
                            $hasDebt = ($cs->customer?->current_balance ?? 0) > 0;
                        @endphp
                        <tr class="{{ $hasDebt ? 'bg-amber-500/10 dark:bg-amber-500/5' : 'hover:bg-slate-50/50 dark:hover:bg-slate-800/30' }} transition-colors">
                            <td class="p-3.5 font-black text-slate-900 dark:text-white text-xs sm:text-sm flex items-center gap-1.5">
                                @if($hasDebt)
                                <span class="w-2 h-2 rounded-full bg-amber-500 inline-block animate-pulse"></span>
                                @endif
                                <span>{{ $cs->customer?->name ?? 'عميل غير مسجل' }}</span>
                            </td>
                            <td class="p-3.5 font-mono text-slate-500" dir="ltr">
                                {{ $cs->customer?->phone ?? '-' }}
                            </td>
                            <td class="p-3.5 text-center font-bold font-mono">{{ $cs->total_invoices }}</td>
                            <td class="p-3.5 font-black font-mono text-emerald-600 dark:text-emerald-400">
                                {{ number_format($cs->total_bought, 2) }} ج.م
                            </td>
                            <td class="p-3.5 font-bold font-mono text-indigo-600 dark:text-indigo-400">
                                {{ number_format($cs->total_paid, 2) }} ج.م
                            </td>
                            <td class="p-3.5 font-bold font-mono text-amber-600 dark:text-amber-400">
                                {{ number_format($cs->total_debt_in_period, 2) }} ج.م
                            </td>
                            <td class="p-3.5 font-black font-mono {{ $hasDebt ? 'text-amber-600 dark:text-amber-400 font-black' : 'text-slate-900 dark:text-white' }}">
                                {{ number_format($cs->customer?->current_balance ?? 0, 2) }} ج.م
                                @if($hasDebt)
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-700 dark:text-amber-300 mr-1 font-sans">مستحق</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400">لا توجد حركات مبيعات عملاء في هذه الفترة</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- ======================================================== -->
    <!-- 💸 TAB 5: المصروفات وصافي الدخل (Expenses & Net Income)     -->
    <!-- ======================================================== -->
    @if($activeTab === 'expenses')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Expenses Summary Cards -->
        <div class="space-y-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 rounded-3xl shadow-sm space-y-3">
                <div class="text-xs font-bold text-slate-500">إجمالي مجمل الربح من المبيعات</div>
                <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono">
                    {{ number_format($periodic['gross_profit'], 2) }} ج.م
                </div>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-rose-500/30 p-5 rounded-3xl shadow-sm space-y-3 bg-gradient-to-b from-white dark:from-slate-900 to-rose-500/5">
                <div class="text-xs font-bold text-rose-500">إجمالي المصروفات والنثريات التشغيلية</div>
                <div class="text-2xl font-black text-rose-600 dark:text-rose-400 font-mono">
                    -{{ number_format($totalExpenses, 2) }} ج.م
                </div>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-indigo-500/40 p-5 rounded-3xl shadow-sm space-y-3 bg-gradient-to-b from-white dark:from-slate-900 to-indigo-500/10">
                <div class="text-xs font-bold text-indigo-600 dark:text-indigo-400">الصافي الفعلي للأرباح (Net Income)</div>
                <div class="text-3xl font-black {{ bccomp($netProfitAfterExpenses, '0.000', 3) >= 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-rose-600' }} font-mono">
                    {{ number_format($netProfitAfterExpenses, 2) }} <span class="text-xs font-normal">ج.م</span>
                </div>
                <p class="text-[11px] text-slate-500">صافي الأرباح الصريحة بعد استبعاد كافة النفقات والرواتب والمصروفات</p>
            </div>
        </div>

        <!-- Expenses by Category Table -->
        <div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm">
            <div class="p-4 sm:p-5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <h3 class="text-sm sm:text-base font-black text-slate-900 dark:text-white">
                    💸 تفصيل المصروفات حسب بنود الصرف خلال الفترة
                </h3>
                <a href="{{ route('expenses.index') }}" class="text-xs font-bold text-amber-600 hover:underline">
                    إدارة المصروفات ←
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 font-bold border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="p-3.5">بند / تصنيف المصروف</th>
                            <th class="p-3.5 text-center">عدد الحركات</th>
                            <th class="p-3.5">إجمالي المبلغ المنصرف</th>
                            <th class="p-3.5 text-center">النسبة من إجمالي المصاريف</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse($expensesByCategory as $ec)
                        @php
                            $catPct = '0.0';
                            if (bccomp($totalExpenses, '0.000', 3) > 0) {
                                $catPct = bcmul(bcdiv($ec->total_amount, $totalExpenses, 4), '100', 1);
                            }
                        @endphp
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="p-3.5 font-bold text-slate-900 dark:text-white">{{ $ec->category }}</td>
                            <td class="p-3.5 text-center font-bold font-mono">{{ $ec->count }}</td>
                            <td class="p-3.5 font-black font-mono text-rose-600 dark:text-rose-400">
                                {{ number_format($ec->total_amount, 2) }} ج.م
                            </td>
                            <td class="p-3.5 text-center font-black font-mono text-slate-700 dark:text-slate-300">
                                {{ $catPct }}%
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="p-8 text-center text-slate-400">لا توجد مصروفات مسجلة في هذه الفترة</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- ======================================================== -->
    <!-- 🏢 TAB 6: تقييم المخزون (Inventory Valuation)              -->
    <!-- ======================================================== -->
    @if($activeTab === 'inventory')
    <div class="space-y-5">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 rounded-3xl shadow-sm space-y-2">
                <div class="text-xs font-bold text-slate-500">
                    قيمة البضاعة بسعر التكلفة 
                    @if($selectedStore) ({{ $selectedStore->name }}) @endif
                </div>
                <div class="text-2xl sm:text-3xl font-black text-amber-600 dark:text-amber-400 font-mono">
                    {{ number_format($stockCostValuation, 2) }} <span class="text-xs font-normal">ج.م</span>
                </div>
                <div class="text-[11px] text-slate-400">رأس المال المستثمر في البضاعة حالياً</div>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 rounded-3xl shadow-sm space-y-2">
                <div class="text-xs font-bold text-slate-500">
                    قيمة البضاعة بسعر البيع المتوقع
                    @if($selectedStore) ({{ $selectedStore->name }}) @endif
                </div>
                <div class="text-2xl sm:text-3xl font-black text-emerald-600 dark:text-emerald-400 font-mono">
                    {{ number_format($stockSellingValuation, 2) }} <span class="text-xs font-normal">ج.م</span>
                </div>
                <div class="text-[11px] text-slate-400">المردود المتوقع عند بيع كامل المخزون</div>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-indigo-500/40 p-5 rounded-3xl shadow-sm space-y-2 bg-gradient-to-b from-white dark:from-slate-900 to-indigo-500/5">
                <div class="text-xs font-bold text-indigo-600 dark:text-indigo-400">
                    الأرباح المتوقعة في المخزن
                    @if($selectedStore) ({{ $selectedStore->name }}) @endif
                </div>
                <div class="text-2xl sm:text-3xl font-black text-indigo-600 dark:text-indigo-400 font-mono">
                    {{ number_format($expectedStockProfit, 2) }} <span class="text-xs font-normal">ج.م</span>
                </div>
                <div class="text-[11px] text-slate-400">فارق سعر البيع عن سعر التكلفة</div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm">
            <div class="p-4 sm:p-5 border-b border-slate-200 dark:border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-3">
                <h3 class="text-sm sm:text-base font-black text-slate-900 dark:text-white flex flex-wrap items-center gap-2">
                    <span>🏢 جرد وتقييم الأصناف:</span>
                    @if($selectedStore)
                        <span class="px-2.5 py-0.5 rounded-xl bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20 text-xs font-bold">
                            @if($selectedStore->type === 'wholesale_van') 🚚 @elseif($selectedStore->type === 'main_warehouse') 🏢 @else 🏬 @endif {{ $selectedStore->name }}
                        </span>
                    @else
                        <span class="px-2.5 py-0.5 rounded-xl bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20 text-xs font-bold">
                            🏢 إجمالي كافة الفروع والمخازن
                        </span>
                    @endif
                </h3>

                <!-- Stock Quantity Filter Buttons -->
                <div class="flex flex-wrap items-center gap-1.5 bg-slate-100 dark:bg-slate-950 p-1 rounded-2xl border border-slate-200 dark:border-slate-800 text-xs">
                    <button 
                        type="button" 
                        wire:click="$set('inventoryStockFilter', 'all')" 
                        class="px-3 py-1.5 rounded-xl font-bold transition-all cursor-pointer flex items-center gap-1.5 {{ $inventoryStockFilter === 'all' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm border border-slate-200 dark:border-slate-700' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <span>🏢 كل الأصناف</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-slate-200 dark:bg-slate-700">{{ $totalInventoryCount }}</span>
                    </button>
                    <button 
                        type="button" 
                        wire:click="$set('inventoryStockFilter', 'in_stock')" 
                        class="px-3 py-1.5 rounded-xl font-bold transition-all cursor-pointer flex items-center gap-1.5 {{ $inventoryStockFilter === 'in_stock' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/30' : 'text-slate-600 dark:text-slate-300 hover:text-emerald-600 dark:hover:text-emerald-400' }}"
                    >
                        <span>📦 متوفر كمية فقط (> 0)</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold {{ $inventoryStockFilter === 'in_stock' ? 'bg-white text-emerald-700' : 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300' }}">{{ $inStockCount }}</span>
                    </button>
                    <button 
                        type="button" 
                        wire:click="$set('inventoryStockFilter', 'zero_stock')" 
                        class="px-3 py-1.5 rounded-xl font-bold transition-all cursor-pointer flex items-center gap-1.5 {{ $inventoryStockFilter === 'zero_stock' ? 'bg-rose-600 text-white shadow-md shadow-rose-600/30' : 'text-slate-600 dark:text-slate-300 hover:text-rose-600 dark:hover:text-rose-400' }}"
                    >
                        <span>🚫 الرصيد صفر (0)</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold {{ $inventoryStockFilter === 'zero_stock' ? 'bg-white text-rose-700' : 'bg-rose-500/20 text-rose-700 dark:text-rose-300' }}">{{ $zeroStockCount }}</span>
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 font-bold border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="p-3.5">#</th>
                            <th class="p-3.5">الصنف</th>
                            <th class="p-3.5 text-center">{{ $selectedStore ? 'الرصيد في الفرع' : 'الرصيد الكلي' }}</th>
                            <th class="p-3.5">سعر التكلفة</th>
                            <th class="p-3.5">سعر البيع</th>
                            <th class="p-3.5">القيمة بالتكلفة</th>
                            <th class="p-3.5">القيمة بسعر البيع</th>
                            <th class="p-3.5">الربح المتوقع</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach($inventoryItems as $index => $item)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="p-3.5 font-bold font-mono text-slate-400">{{ $index + 1 }}</td>
                            <td class="p-3.5">
                                <span class="font-extrabold text-slate-900 dark:text-white text-xs sm:text-sm">{{ $item->name }}</span>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-[10px] text-slate-400 font-mono">كود: {{ $item->code }}</span>
                                    @if(!empty($item->has_custom_price))
                                    <span class="text-[9px] px-1.5 py-0.5 bg-amber-500/15 text-amber-700 dark:text-amber-400 rounded font-bold">سعر مخصص للفرع</span>
                                    @endif
                                </div>
                            </td>
                            <td class="p-3.5 text-center font-black font-mono {{ bccomp($item->current_stock, '0.000', 3) <= 0 ? 'text-rose-600' : 'text-slate-900 dark:text-white' }}">
                                {{ number_format($item->current_stock, 2) }} {{ $item->unit }}
                            </td>
                            <td class="p-3.5 font-mono text-slate-600 dark:text-slate-400">
                                {{ number_format($item->cost_price, 2) }} ج.م
                            </td>
                            <td class="p-3.5 font-bold font-mono text-slate-900 dark:text-white">
                                {{ number_format($item->selling_price, 2) }} ج.م
                            </td>
                            <td class="p-3.5 font-black font-mono text-amber-600 dark:text-amber-400">
                                {{ number_format($item->cost_val, 2) }} ج.م
                            </td>
                            <td class="p-3.5 font-black font-mono text-emerald-600 dark:text-emerald-400">
                                {{ number_format($item->sell_val, 2) }} ج.م
                            </td>
                            <td class="p-3.5 font-black font-mono {{ bccomp($item->profit, '0.000', 3) >= 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-rose-600' }}">
                                {{ number_format($item->profit, 2) }} ج.م
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- ======================================================== -->
    <!-- 💰 TAB 7: تقرير الخزائن والسيولة والتحويلات المالية         -->
    <!-- ======================================================== -->
    @if($activeTab === 'treasury')
    <div class="space-y-6">
        <!-- Top KPI Cards: Individual Treasuries + Grand Total Combined Liquidity -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. Cash Drawer -->
            @php $cashAcc = $treasuryData['accounts']['cash'] ?? null; @endphp
            <div class="bg-white dark:bg-slate-900 p-5 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm relative overflow-hidden group hover:border-emerald-400 transition-all">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">💵 درج النقدية (كاش)</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 font-mono">
                        {{ $cashAcc['liquidity_share'] ?? '0' }}% من السيولة
                    </span>
                </div>
                <div class="text-2xl font-black font-mono text-slate-900 dark:text-white mt-2">
                    {{ number_format((float)($cashAcc['closing_balance'] ?? 0), 2) }} <span class="text-xs text-slate-400">ج.م</span>
                </div>
                <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 font-mono">
                    <span class="text-emerald-600 font-bold">وارد: +{{ number_format((float)($cashAcc['inflows'] ?? 0), 2) }}</span>
                    <span class="text-rose-500 font-bold">صادر: -{{ number_format((float)($cashAcc['outflows'] ?? 0), 2) }}</span>
                </div>
            </div>

            <!-- 2. InstaPay -->
            @php $instaAcc = $treasuryData['accounts']['instapay'] ?? null; @endphp
            <div class="bg-white dark:bg-slate-900 p-5 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm relative overflow-hidden group hover:border-purple-400 transition-all">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">⚡ حساب إنستاباي (InstaPay)</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 font-mono">
                        {{ $instaAcc['liquidity_share'] ?? '0' }}% من السيولة
                    </span>
                </div>
                <div class="text-2xl font-black font-mono text-purple-700 dark:text-purple-400 mt-2">
                    {{ number_format((float)($instaAcc['closing_balance'] ?? 0), 2) }} <span class="text-xs text-slate-400">ج.م</span>
                </div>
                <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 font-mono">
                    <span class="text-emerald-600 font-bold">وارد: +{{ number_format((float)($instaAcc['inflows'] ?? 0), 2) }}</span>
                    <span class="text-rose-500 font-bold">صادر: -{{ number_format((float)($instaAcc['outflows'] ?? 0), 2) }}</span>
                </div>
            </div>

            <!-- 3. Smart E-Wallet -->
            @php $walletAcc = $treasuryData['accounts']['e_wallet'] ?? null; @endphp
            <div class="bg-white dark:bg-slate-900 p-5 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm relative overflow-hidden group hover:border-amber-400 transition-all">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">📲 المحافظ الذكية (فودافون/أورنج)</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 font-mono">
                        {{ $walletAcc['liquidity_share'] ?? '0' }}% من السيولة
                    </span>
                </div>
                <div class="text-2xl font-black font-mono text-amber-600 dark:text-amber-400 mt-2">
                    {{ number_format((float)($walletAcc['closing_balance'] ?? 0), 2) }} <span class="text-xs text-slate-400">ج.م</span>
                </div>
                <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 font-mono">
                    <span class="text-emerald-600 font-bold">وارد: +{{ number_format((float)($walletAcc['inflows'] ?? 0), 2) }}</span>
                    <span class="text-rose-500 font-bold">صادر: -{{ number_format((float)($walletAcc['outflows'] ?? 0), 2) }}</span>
                </div>
            </div>

            <!-- 4. Grand Total Combined Liquidity (الكامل في الجميع) -->
            <div class="bg-gradient-to-tr from-slate-950 via-slate-900 to-emerald-950 text-white p-5 rounded-3xl border border-emerald-500/30 shadow-xl relative overflow-hidden group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-emerald-300">💰 إجمالي السيولة المجمعة (الكامل في الجميع)</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-500 text-slate-950">100% كاش متاح</span>
                </div>
                <div class="text-2xl sm:text-3xl font-black font-mono text-emerald-400 mt-2">
                    {{ number_format((float)$treasuryData['total_liquidity'], 2) }} <span class="text-xs text-emerald-200">ج.م</span>
                </div>
                <div class="flex items-center justify-between text-[11px] text-emerald-200/80 mt-3 pt-3 border-t border-emerald-800/40 font-mono">
                    <span>مقبوضات: +{{ number_format((float)$treasuryData['total_inflows'], 2) }}</span>
                    <span>مدفوعات: -{{ number_format((float)$treasuryData['total_outflows'], 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Action / Sub-Filters Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-100/80 dark:bg-slate-900/60 p-3 rounded-2xl border border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-2 overflow-x-auto">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 whitespace-nowrap">عرض كشف حساب:</span>
                <button 
                    type="button" 
                    wire:click="$set('selectedTreasuryMethod', 'all')"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $selectedTreasuryMethod === 'all' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-950 shadow-sm' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200' }}"
                >
                    كل الخزن والحسابات
                </button>
                <button 
                    type="button" 
                    wire:click="$set('selectedTreasuryMethod', 'cash')"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $selectedTreasuryMethod === 'cash' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200' }}"
                >
                    💵 درج الكاش
                </button>
                <button 
                    type="button" 
                    wire:click="$set('selectedTreasuryMethod', 'instapay')"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $selectedTreasuryMethod === 'instapay' ? 'bg-purple-600 text-white shadow-sm' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200' }}"
                >
                    ⚡ إنستاباي
                </button>
                <button 
                    type="button" 
                    wire:click="$set('selectedTreasuryMethod', 'e_wallet')"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $selectedTreasuryMethod === 'e_wallet' ? 'bg-amber-600 text-white shadow-sm' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200' }}"
                >
                    📲 المحافظ الذكية
                </button>
            </div>

            <div class="flex items-center gap-2 self-end sm:self-center">
                <a 
                    href="{{ route('reports.print', ['tab' => 'treasury', 'store_id' => $selectedStoreId, 'from' => $fromDate, 'to' => $toDate, 'method' => $selectedTreasuryMethod]) }}" 
                    target="_blank"
                    class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-100 dark:hover:bg-white dark:text-slate-950 rounded-xl font-bold text-xs shadow-sm flex items-center gap-1.5 transition-all cursor-pointer"
                >
                    <span>🖨️ طباعة تقرير الخزينة A4</span>
                </a>
            </div>
        </div>

        <!-- 1. Multi-Account Comparison Summary Table -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
            <div class="p-4 sm:p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-950/50">
                <div>
                    <h3 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white">
                        📑 جدول ملخص ومقارنة الخزائن وحسابات الدفع (Balances & Movements Summary)
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        يوضح الرصيد الافتتاحي، وحركات الفترة، وصافي التحويلات، والرصيد النهائي لكل خزينة
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-100 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 font-extrabold border-b border-slate-200 dark:border-slate-700">
                            <th class="p-3.5">الخزينة / الحساب</th>
                            <th class="p-3.5 text-center">رصيد أول المدة</th>
                            <th class="p-3.5 text-center text-emerald-700 dark:text-emerald-400">مقبوضات الفترة (+)</th>
                            <th class="p-3.5 text-center text-rose-700 dark:text-rose-400">مدفوعات ومصروفات (-)</th>
                            <th class="p-3.5 text-center text-sky-700 dark:text-sky-400">تحويلات واردة (+)</th>
                            <th class="p-3.5 text-center text-orange-700 dark:text-orange-400">تحويلات وعمولات (-)</th>
                            <th class="p-3.5 text-center text-indigo-700 dark:text-indigo-400 font-black">الرصيد الختامي الحالي</th>
                            <th class="p-3.5 text-center">نسبة السيولة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                        @foreach($treasuryData['accounts'] as $acc)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="p-3.5 font-bold flex items-center gap-2">
                                <span class="text-base">{{ $acc['icon'] }}</span>
                                <span class="text-slate-900 dark:text-white font-black">{{ $acc['label'] }}</span>
                            </td>
                            <td class="p-3.5 text-center font-mono text-slate-500 dark:text-slate-400">
                                {{ number_format((float)$acc['opening_balance'], 2) }} ج.م
                            </td>
                            <td class="p-3.5 text-center font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                +{{ number_format((float)$acc['inflows'], 2) }} ج.م
                            </td>
                            <td class="p-3.5 text-center font-mono font-bold text-rose-600 dark:text-rose-400">
                                -{{ number_format((float)$acc['outflows'], 2) }} ج.م
                            </td>
                            <td class="p-3.5 text-center font-mono font-bold text-sky-600 dark:text-sky-400">
                                +{{ number_format((float)$acc['transfers_in'], 2) }} ج.م
                            </td>
                            <td class="p-3.5 text-center font-mono font-bold text-orange-600 dark:text-orange-400">
                                -{{ number_format((float)bcadd((string)$acc['transfers_out'], (string)$acc['fees'], 3), 2) }} ج.م
                            </td>
                            <td class="p-3.5 text-center font-mono font-black text-sm text-slate-900 dark:text-white bg-slate-50/80 dark:bg-slate-800/60">
                                {{ number_format((float)$acc['closing_balance'], 2) }} ج.م
                            </td>
                            <td class="p-3.5 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $acc['badge_class'] }}">
                                    {{ $acc['liquidity_share'] }}%
                                </span>
                            </td>
                        </tr>
                        @endforeach
                        <!-- Grand Total Row -->
                        <tr class="bg-slate-900 text-white dark:bg-slate-950 font-black text-xs sm:text-sm border-t-2 border-slate-700">
                            <td class="p-4">
                                💰 الإجمالي الشامل (الكامل في الجميع)
                            </td>
                            <td class="p-4 text-center font-mono">
                                {{ number_format((float)$treasuryData['total_opening'], 2) }} ج.م
                            </td>
                            <td class="p-4 text-center font-mono text-emerald-400">
                                +{{ number_format((float)$treasuryData['total_inflows'], 2) }} ج.م
                            </td>
                            <td class="p-4 text-center font-mono text-rose-400">
                                -{{ number_format((float)$treasuryData['total_outflows'], 2) }} ج.م
                            </td>
                            <td class="p-4 text-center font-mono text-sky-400">
                                +{{ number_format((float)$treasuryData['total_transfers_in'], 2) }} ج.م
                            </td>
                            <td class="p-4 text-center font-mono text-orange-400">
                                -{{ number_format((float)bcadd((string)$treasuryData['total_transfers_out'], (string)$treasuryData['total_fees'], 3), 2) }} ج.م
                            </td>
                            <td class="p-4 text-center font-mono text-emerald-400 text-base font-black bg-slate-950/60">
                                {{ number_format((float)$treasuryData['total_liquidity'], 2) }} ج.م
                            </td>
                            <td class="p-4 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] bg-emerald-500 text-slate-950 font-black">100%</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. Inter-Treasury Transfers History Table -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
            <div class="p-4 sm:p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-950/50">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-purple-500/10 text-purple-600 flex items-center justify-center font-bold text-base">
                        🔄
                    </div>
                    <div>
                        <h3 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white">
                            سجل التحويلات المالية بين الخزن (Inter-Treasury Transfers Log)
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            كافة التحويلات بين الكاش والإنستاباي والمحافظ مع تفاصيل العمولات والمستخدم المنفذ
                        </p>
                    </div>
                </div>
                <span class="px-2.5 py-1 bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 font-mono font-bold text-xs rounded-xl">
                    {{ count($treasuryData['transfers']) }} تحويل
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-100 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 font-extrabold border-b border-slate-200 dark:border-slate-700">
                            <th class="p-3.5">رقم التحويل</th>
                            <th class="p-3.5">التاريخ والوقت</th>
                            <th class="p-3.5">من خزينة</th>
                            <th class="p-3.5">إلى خزينة</th>
                            <th class="p-3.5 text-center">المبلغ المحول</th>
                            <th class="p-3.5 text-center">عمولة السحب</th>
                            <th class="p-3.5">الفرع</th>
                            <th class="p-3.5">المسؤول</th>
                            <th class="p-3.5">البيان والملاحظات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($treasuryData['transfers'] as $trf)
                        @php
                            $fromEnum = \App\Enums\PaymentMethod::tryFrom($trf->from_method);
                            $toEnum   = \App\Enums\PaymentMethod::tryFrom($trf->to_method);
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="p-3.5 font-mono font-bold text-purple-600 dark:text-purple-400">
                                {{ $trf->transfer_number }}
                            </td>
                            <td class="p-3.5 font-mono text-slate-500">
                                {{ $trf->transfer_date->format('Y-m-d') }}
                                <span class="text-[10px] text-slate-400">({{ $trf->created_at->format('H:i') }})</span>
                            </td>
                            <td class="p-3.5">
                                <span class="inline-flex items-center gap-1 font-bold text-rose-600 dark:text-rose-400">
                                    <span>{{ $fromEnum?->icon() }}</span>
                                    <span>{{ $fromEnum?->shortLabel() ?? $trf->from_method }}</span>
                                </span>
                            </td>
                            <td class="p-3.5">
                                <span class="inline-flex items-center gap-1 font-bold text-emerald-600 dark:text-emerald-400">
                                    <span>{{ $toEnum?->icon() }}</span>
                                    <span>{{ $toEnum?->shortLabel() ?? $trf->to_method }}</span>
                                </span>
                            </td>
                            <td class="p-3.5 text-center font-mono font-black text-slate-900 dark:text-white">
                                {{ number_format((float)$trf->amount, 2) }} ج.م
                            </td>
                            <td class="p-3.5 text-center font-mono {{ bccomp((string)$trf->transfer_fee, '0.000', 3) > 0 ? 'text-amber-600 font-bold' : 'text-slate-400' }}">
                                {{ number_format((float)$trf->transfer_fee, 2) }} ج.م
                            </td>
                            <td class="p-3.5 text-slate-600 dark:text-slate-400">
                                {{ $trf->store?->name ?? 'المركز الرئيسي' }}
                            </td>
                            <td class="p-3.5 font-medium text-slate-700 dark:text-slate-300">
                                {{ $trf->creator?->name ?? 'النظام' }}
                            </td>
                            <td class="p-3.5 text-slate-500 dark:text-slate-400">
                                {{ $trf->notes ?: '—' }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="p-8 text-center text-slate-400">
                                لا توجد حركات تحويل مسجلة بين الخزن خلال هذه الفترة.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. Running Chronological Ledger Table -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
            <div class="p-4 sm:p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-950/50">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold text-base">
                        📜
                    </div>
                    <div>
                        <h3 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white">
                            كشف حركة الخزينة التسلسلي والرصيد اللحظي (Running Treasury Ledger)
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            عرض تفصيلي زمني لكافة المقبوضات والمدفوعات والمصروفات والتحويلات مع الرصيد التراكمي
                        </p>
                    </div>
                </div>
                <span class="px-2.5 py-1 bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 font-mono font-bold text-xs rounded-xl">
                    {{ count($treasuryData['ledger_entries']) }} حركة
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-100 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 font-extrabold border-b border-slate-200 dark:border-slate-700">
                            <th class="p-3.5">التاريخ والوقت</th>
                            <th class="p-3.5">رقم السند / المستند</th>
                            <th class="p-3.5">نوع الحركة</th>
                            <th class="p-3.5">الخزينة</th>
                            <th class="p-3.5">الطرف والبيان</th>
                            <th class="p-3.5 text-center text-emerald-700 dark:text-emerald-400">المقبوضات (وارد +)</th>
                            <th class="p-3.5 text-center text-rose-700 dark:text-rose-400">المدفوعات (صادر -)</th>
                            <th class="p-3.5 text-center text-indigo-700 dark:text-indigo-400 font-black">الرصيد بعد الحركة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($treasuryData['ledger_entries'] as $ent)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="p-3.5 font-mono text-slate-500">
                                {{ $ent['date'] }} <span class="text-[10px] text-slate-400">({{ $ent['time'] }})</span>
                            </td>
                            <td class="p-3.5 font-mono font-bold text-slate-900 dark:text-white">
                                {{ $ent['doc_number'] }}
                            </td>
                            <td class="p-3.5">
                                <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold {{ $ent['debit'] > 0 ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' : 'bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300' }}">
                                    {{ $ent['type_label'] }}
                                </span>
                            </td>
                            <td class="p-3.5 font-bold text-slate-700 dark:text-slate-300">
                                {{ $ent['method_label'] }}
                            </td>
                            <td class="p-3.5">
                                <span class="font-bold text-slate-900 dark:text-white">{{ $ent['party'] }}</span>
                                <span class="text-[10px] text-slate-400 block">{{ $ent['notes'] }}</span>
                            </td>
                            <td class="p-3.5 text-center font-mono font-bold {{ bccomp($ent['debit'], '0.000', 3) > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-300 dark:text-slate-600' }}">
                                {{ bccomp($ent['debit'], '0.000', 3) > 0 ? '+' . number_format((float)$ent['debit'], 2) : '—' }}
                            </td>
                            <td class="p-3.5 text-center font-mono font-bold {{ bccomp($ent['credit'], '0.000', 3) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-300 dark:text-slate-600' }}">
                                {{ bccomp($ent['credit'], '0.000', 3) > 0 ? '-' . number_format((float)$ent['credit'], 2) : '—' }}
                            </td>
                            <td class="p-3.5 text-center font-mono font-black text-slate-900 dark:text-white bg-slate-50/50 dark:bg-slate-800/30">
                                {{ number_format((float)$ent['running_balance'], 2) }} ج.م
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="p-8 text-center text-slate-400">
                                لا توجد حركات مسجلة بالخزينة خلال هذه الفترة.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- ======================================================== -->
    <!-- 📊 TAB 8: تحليل حركة البضاعة والأصناف (ABC Analysis)      -->
    <!-- ======================================================== -->
    @if($activeTab === 'abc')
    <div class="space-y-6">
        <!-- ABC 3 Classification KPI Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <!-- Class A: Gold -->
            <div class="bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent border-2 border-amber-500/30 dark:border-amber-500/40 rounded-3xl p-5 shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-2xl bg-amber-500 text-white font-black text-sm flex items-center justify-center shadow-md shadow-amber-500/30">A</span>
                        <span class="text-xs font-black text-amber-600 dark:text-amber-400">الأصناف الذهبية (Top 80%)</span>
                    </div>
                    <span class="px-2.5 py-1 rounded-xl bg-amber-500/20 text-amber-700 dark:text-amber-300 font-mono font-black text-xs">
                        {{ $abcData['class_a']['count'] }} صنف
                    </span>
                </div>
                <div class="text-2xl sm:text-3xl font-black font-mono text-slate-900 dark:text-white" dir="ltr">
                    {{ number_format((float)$abcData['class_a']['profit'], 2) }} <span class="text-xs font-tajawal font-bold text-slate-500">ج.م أرباح</span>
                </div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 flex items-center justify-between">
                    <span>نسبة المساهمة في أرباح النشاط:</span>
                    <span class="font-bold font-mono text-amber-600 dark:text-amber-400">{{ $abcData['class_a']['share'] }}%</span>
                </div>
            </div>

            <!-- Class B: Silver -->
            <div class="bg-gradient-to-br from-indigo-500/10 via-indigo-500/5 to-transparent border-2 border-indigo-500/30 dark:border-indigo-500/40 rounded-3xl p-5 shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-2xl bg-indigo-600 text-white font-black text-sm flex items-center justify-center shadow-md shadow-indigo-600/30">B</span>
                        <span class="text-xs font-black text-indigo-600 dark:text-indigo-400">الأصناف المتوسطة (15%)</span>
                    </div>
                    <span class="px-2.5 py-1 rounded-xl bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 font-mono font-black text-xs">
                        {{ $abcData['class_b']['count'] }} صنف
                    </span>
                </div>
                <div class="text-2xl sm:text-3xl font-black font-mono text-slate-900 dark:text-white" dir="ltr">
                    {{ number_format((float)$abcData['class_b']['profit'], 2) }} <span class="text-xs font-tajawal font-bold text-slate-500">ج.م أرباح</span>
                </div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 flex items-center justify-between">
                    <span>نسبة المساهمة في أرباح النشاط:</span>
                    <span class="font-bold font-mono text-indigo-600 dark:text-indigo-400">{{ $abcData['class_b']['share'] }}%</span>
                </div>
            </div>

            <!-- Class C: Bronze / Slow -->
            <div class="bg-gradient-to-br from-slate-500/10 via-slate-500/5 to-transparent border-2 border-slate-400/30 dark:border-slate-700 rounded-3xl p-5 shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-2xl bg-slate-600 text-white font-black text-sm flex items-center justify-center shadow-md">C</span>
                        <span class="text-xs font-black text-slate-600 dark:text-slate-400">الأصناف الراكدة / بطيئة الحركة (5%)</span>
                    </div>
                    <span class="px-2.5 py-1 rounded-xl bg-slate-500/20 text-slate-700 dark:text-slate-300 font-mono font-black text-xs">
                        {{ $abcData['class_c']['count'] }} صنف
                    </span>
                </div>
                <div class="text-2xl sm:text-3xl font-black font-mono text-slate-900 dark:text-white" dir="ltr">
                    {{ number_format((float)$abcData['class_c']['profit'], 2) }} <span class="text-xs font-tajawal font-bold text-slate-500">ج.م أرباح</span>
                </div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 flex items-center justify-between">
                    <span>نسبة المساهمة في أرباح النشاط:</span>
                    <span class="font-bold font-mono text-slate-600 dark:text-slate-400">{{ $abcData['class_c']['share'] }}%</span>
                </div>
            </div>
        </div>

        @if(count($abcData['dead_stock']) > 0)
        <!-- Dead Stock Alert Box -->
        <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 flex items-start gap-3">
            <span class="text-2xl">⚠️</span>
            <div>
                <h4 class="text-sm font-bold text-rose-800 dark:text-rose-300">تنبيه بضاعة راكدة بالمخازن (Dead Stock):</h4>
                <p class="text-xs text-rose-700 dark:text-rose-400 mt-0.5">
                    يوجد <strong>{{ count($abcData['dead_stock']) }}</strong> صنف لديه رصيد مخزني حالي ولكن لم يُسجل أي حركة بيع خلال هذه الفترة ({{ $abcData['days_in_period'] }} يوم). يُوصى بعمل عروض ترويجية أو تخفيضات لتصفيتها وتحرير السيولة.
                </p>
            </div>
        </div>
        @endif

        <!-- ABC Items Table -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white font-tajawal">جدول تصنيف حركة البضاعة والأرباح</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">معدل البيع اليومي، الإيرادات، التكلفة، ومجمل ربح كل صنف</p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="exportAbc"
                        class="px-3.5 py-2 bg-emerald-500/10 hover:bg-emerald-600 text-emerald-700 dark:text-emerald-400 hover:text-white rounded-xl text-xs font-bold border border-emerald-500/30 transition-all flex items-center gap-1.5 cursor-pointer"
                    >
                        <span>📥 تصدير Excel</span>
                    </button>
                    <a
                        href="{{ route('reports.print', ['tab' => 'abc', 'store_id' => $selectedStoreId, 'from' => $fromDate, 'to' => $toDate]) }}"
                        target="_blank"
                        class="px-3.5 py-2 bg-purple-600 hover:bg-purple-500 text-white rounded-xl text-xs font-bold shadow-md shadow-purple-600/20 transition-all flex items-center gap-1.5 cursor-pointer"
                    >
                        <span>🖨️ طباعة A4 / PDF</span>
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950/80 text-xs font-bold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="p-3.5 text-center">التصنيف</th>
                            <th class="p-3.5">الكود</th>
                            <th class="p-3.5">اسم الصنف</th>
                            <th class="p-3.5 text-center">الرصيد بالمخزن</th>
                            <th class="p-3.5 text-center">السحب اليومي</th>
                            <th class="p-3.5 text-center">الكمية المباعة</th>
                            <th class="p-3.5 text-center">إجمالي الإيراد</th>
                            <th class="p-3.5 text-center">مجمل الربح</th>
                            <th class="p-3.5 text-center">هامش الربح %</th>
                            <th class="p-3.5 text-center">مساهمة الربح %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-sans">
                        @foreach($abcData['items'] as $item)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="p-3.5 text-center">
                                @if($item['abc_class'] === 'A')
                                    <span class="px-2.5 py-1 rounded-xl bg-amber-500 text-white font-black font-mono text-xs shadow-sm shadow-amber-500/30">Class A 👑</span>
                                @elseif($item['abc_class'] === 'B')
                                    <span class="px-2.5 py-1 rounded-xl bg-indigo-600 text-white font-black font-mono text-xs shadow-sm">Class B ⚖️</span>
                                @else
                                    <span class="px-2.5 py-1 rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold font-mono text-xs">Class C 💤</span>
                                @endif
                            </td>
                            <td class="p-3.5 font-mono text-slate-500" dir="ltr">{{ $item['code'] }}</td>
                            <td class="p-3.5 font-bold text-slate-900 dark:text-white">{{ $item['name'] }}</td>
                            <td class="p-3.5 text-center font-mono font-bold text-slate-700 dark:text-slate-300">
                                {{ number_format((float)$item['current_stock'], 2) }} {{ $item['unit'] }}
                            </td>
                            <td class="p-3.5 text-center font-mono font-bold text-cyan-600 dark:text-cyan-400">
                                {{ number_format((float)$item['velocity'], 2) }} / يوم
                            </td>
                            <td class="p-3.5 text-center font-mono font-bold text-slate-900 dark:text-white">
                                {{ number_format((float)$item['quantity_sold'], 2) }}
                            </td>
                            <td class="p-3.5 text-center font-mono font-bold text-slate-700 dark:text-slate-300">
                                {{ number_format((float)$item['revenue'], 2) }} ج.م
                            </td>
                            <td class="p-3.5 text-center font-mono font-black text-emerald-600 dark:text-emerald-400">
                                {{ number_format((float)$item['gross_profit'], 2) }} ج.م
                            </td>
                            <td class="p-3.5 text-center font-mono font-bold text-slate-600 dark:text-slate-400">
                                {{ $item['profit_margin'] }}%
                            </td>
                            <td class="p-3.5 text-center font-mono font-bold text-amber-600 dark:text-amber-400">
                                {{ $item['profit_share'] }}%
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- ======================================================== -->
    <!-- 🏢 TAB 9: أرباح وخسائر الفروع والمراكز (P&L per Branch)    -->
    <!-- ======================================================== -->
    @if($activeTab === 'pnl')
    <div class="space-y-6">
        <!-- Grand Totals 5 Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 block mb-1">📈 صافي المبيعات الإجمالية:</span>
                <span class="text-xl font-black font-mono text-slate-900 dark:text-white" dir="ltr">{{ number_format((float)$pnlData['grand_revenue'], 2) }} ج.م</span>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 block mb-1">📦 تكلفة البضاعة المباعة (COGS):</span>
                <span class="text-xl font-black font-mono text-slate-600 dark:text-slate-300" dir="ltr">{{ number_format((float)$pnlData['grand_cogs'], 2) }} ج.م</span>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 block mb-1">💰 مجمل الربح الإجمالي:</span>
                <span class="text-xl font-black font-mono text-emerald-600 dark:text-emerald-400" dir="ltr">{{ number_format((float)$pnlData['grand_gross_profit'], 2) }} ج.م</span>
                <span class="text-[10px] text-emerald-500 block font-bold mt-0.5">هامش: {{ $pnlData['grand_gross_margin'] }}%</span>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 block mb-1">💸 المصروفات التشغيلية:</span>
                <span class="text-xl font-black font-mono text-rose-600 dark:text-rose-400" dir="ltr">{{ number_format((float)$pnlData['grand_expenses'], 2) }} ج.م</span>
            </div>

            <div class="bg-gradient-to-br from-emerald-600 to-teal-700 text-white rounded-3xl p-4 shadow-md">
                <span class="text-xs font-bold text-emerald-100 block mb-1">🏆 صافي الربح التشغيلي:</span>
                <span class="text-xl font-black font-mono" dir="ltr">{{ number_format((float)$pnlData['grand_net_profit'], 2) }} ج.م</span>
                <span class="text-[10px] text-emerald-200 block font-bold mt-0.5">صافي العائد: {{ $pnlData['grand_net_margin'] }}%</span>
            </div>
        </div>

        <!-- Comparative Branch P&L Table -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white font-tajawal">مقارنة ربحية وأداء الفروع وعربات التوزيع</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">قائمة الدخل التفصيلية وصافي أرباح كل فرع بعد خصم مصروفاته المباشرة</p>
                </div>
                <div>
                    <a
                        href="{{ route('reports.print', ['tab' => 'pnl', 'store_id' => $selectedStoreId, 'from' => $fromDate, 'to' => $toDate]) }}"
                        target="_blank"
                        class="px-3.5 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-600/20 transition-all flex items-center gap-1.5 cursor-pointer"
                    >
                        <span>🖨️ طباعة قائمة الدخل (A4 / PDF)</span>
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950/80 text-xs font-bold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="p-3.5">الفرع / النقطة</th>
                            <th class="p-3.5 text-center">الفواتير</th>
                            <th class="p-3.5 text-center">إجمالي المبيعات</th>
                            <th class="p-3.5 text-center">المرتجعات</th>
                            <th class="p-3.5 text-center">صافي الإيراد</th>
                            <th class="p-3.5 text-center">تكلفة البضاعة (COGS)</th>
                            <th class="p-3.5 text-center">مجمل الربح</th>
                            <th class="p-3.5 text-center">المصروفات</th>
                            <th class="p-3.5 text-center font-black">صافي الربح النهائي</th>
                            <th class="p-3.5 text-center">هامش الصافي %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-sans">
                        @foreach($pnlData['stores'] as $st)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="p-3.5">
                                <span class="font-bold text-slate-900 dark:text-white block">{{ $st['store_name'] }}</span>
                                <span class="text-[10px] text-slate-400 font-mono" dir="ltr">{{ $st['store_code'] }}</span>
                            </td>
                            <td class="p-3.5 text-center font-mono font-bold text-slate-700 dark:text-slate-300">
                                {{ $st['invoices_count'] }}
                            </td>
                            <td class="p-3.5 text-center font-mono font-bold text-slate-700 dark:text-slate-300">
                                {{ number_format((float)$st['gross_sales'], 2) }} ج.م
                            </td>
                            <td class="p-3.5 text-center font-mono font-bold text-rose-500">
                                {{ number_format((float)$st['returns_amount'], 2) }} ج.م
                            </td>
                            <td class="p-3.5 text-center font-mono font-bold text-slate-900 dark:text-white">
                                {{ number_format((float)$st['net_revenue'], 2) }} ج.م
                            </td>
                            <td class="p-3.5 text-center font-mono text-slate-500 dark:text-slate-400">
                                {{ number_format((float)$st['cogs'], 2) }} ج.م
                            </td>
                            <td class="p-3.5 text-center font-mono font-black text-emerald-600 dark:text-emerald-400">
                                {{ number_format((float)$st['gross_profit'], 2) }} ج.م
                            </td>
                            <td class="p-3.5 text-center font-mono font-bold text-rose-600 dark:text-rose-400">
                                {{ number_format((float)$st['expenses_total'], 2) }} ج.م
                            </td>
                            <td class="p-3.5 text-center font-mono font-black text-sm {{ bccomp((string)$st['net_operating_profit'], '0.000', 3) >= 0 ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-50/50 dark:bg-emerald-950/20' : 'text-rose-600 bg-rose-50/50' }}">
                                {{ number_format((float)$st['net_operating_profit'], 2) }} ج.م
                            </td>
                            <td class="p-3.5 text-center font-mono font-bold {{ bccomp((string)$st['net_margin'], '0.00', 2) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500' }}">
                                {{ $st['net_margin'] }}%
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Cost Centers Breakdown -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-sm space-y-4">
            <h3 class="text-base font-bold text-slate-900 dark:text-white font-tajawal">توزيع المصروفات التشغيلية حسب مراكز التكلفة</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                @php
                    $costCenterLabels = [
                        'rent'        => '🏢 إيجارات الفروع',
                        'utilities'   => '💡 كهرباء ومرافق',
                        'salaries'    => '👥 رواتب وعمالة',
                        'vehicles'    => '🚚 سيارات التوزيع',
                        'maintenance' => '⚙️ صيانة ومعدات',
                        'packaging'   => '📦 كراتين وتغليف',
                        'hospitality' => '☕ ضيافة وبوفيه',
                        'marketing'   => '📢 تسويق وإعلانات',
                        'shipping'    => '✈️ شحن وتوصيل',
                        'operational' => '📑 نثريات عامة',
                    ];
                @endphp
                @foreach($pnlData['grand_cost_centers'] as $ccKey => $ccAmount)
                <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800">
                    <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 block mb-1">
                        {{ $costCenterLabels[$ccKey] ?? $ccKey }}
                    </span>
                    <span class="text-sm font-black font-mono text-slate-900 dark:text-white" dir="ltr">
                        {{ number_format((float)$ccAmount, 2) }} ج.م
                    </span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>
