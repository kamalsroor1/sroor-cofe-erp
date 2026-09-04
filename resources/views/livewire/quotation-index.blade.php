<div class="space-y-5 select-none" dir="rtl">

    <!-- Top Command Header -->
    <div class="bg-white dark:bg-slate-900 p-4 sm:p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-2xl font-bold border border-amber-500/20 shrink-0 shadow-inner">
                📋
            </div>
            <div>
                <h1 class="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>عروض الأسعار للعملاء (Price Quotations)</span>
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    إدارة عروض الأسعار التقديرية بالأسعار (جملة / قطاعي) والطباعة ومشاركتها عبر واتساب وتحويلها لفواتير بيع معتمدة
                </p>
            </div>
        </div>

        <a 
            href="{{ route('quotations.create') }}" 
            class="px-5 py-3 rounded-2xl bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-500 hover:to-amber-400 text-white font-black text-xs sm:text-sm shadow-lg shadow-amber-600/30 hover:shadow-amber-500/40 transition-all flex items-center justify-center gap-2 cursor-pointer active:scale-95 shrink-0"
        >
            <span>➕ إنشاء عرض أسعار جديد</span>
        </a>
    </div>

    <!-- 4 KPI Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <!-- Total -->
        <div class="p-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400 flex items-center justify-between">
                <span>إجمالي العروض</span>
                <span class="text-base">📋</span>
            </div>
            <div class="text-xl sm:text-2xl font-black font-mono text-slate-900 dark:text-white mt-1.5">
                {{ number_format($totalCount) }}
            </div>
        </div>

        <!-- Active -->
        <div class="p-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 flex items-center justify-between">
                <span>عروض سارية الصلاحية</span>
                <span class="text-base">⏳</span>
            </div>
            <div class="text-xl sm:text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400 mt-1.5">
                {{ number_format($activeCount) }}
            </div>
        </div>

        <!-- Converted to Invoices -->
        <div class="p-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-bold text-purple-600 dark:text-purple-400 flex items-center justify-between">
                <span>تحولت لفواتير بيع</span>
                <span class="text-base">⚡</span>
            </div>
            <div class="text-xl sm:text-2xl font-black font-mono text-purple-600 dark:text-purple-400 mt-1.5">
                {{ number_format($convertedCount) }}
            </div>
        </div>

        <!-- Expired -->
        <div class="p-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-bold text-rose-500 dark:text-rose-400 flex items-center justify-between">
                <span>منتهية الصلاحية</span>
                <span class="text-base">⚠️</span>
            </div>
            <div class="text-xl sm:text-2xl font-black font-mono text-rose-500 dark:text-rose-400 mt-1.5">
                {{ number_format($expiredCount) }}
            </div>
        </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
        <!-- Search Input -->
        <div class="relative flex-1">
            <input 
                type="text" 
                wire:model.live.debounce.250ms="search" 
                placeholder="🔍 ابحث برقم العرض أو اسم العميل أو رقم الهاتف..." 
                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-4 py-2.5 text-xs sm:text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500"
            >
            @if($search)
            <button wire:click="$set('search', '')" class="absolute left-3 top-1/2 -translate-y-1/2 w-6 h-6 rounded-md bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center text-xs font-bold hover:bg-slate-300 cursor-pointer">✕</button>
            @endif
        </div>

        <!-- Filters -->
        <div class="flex items-center gap-2 flex-wrap">
            <!-- Pricing Tier Filter -->
            <select wire:model.live="tierFilter" class="h-10 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 text-xs font-bold text-slate-700 dark:text-slate-300">
                <option value="all">🏷️ كل التسعيرات</option>
                <option value="wholesale">🏪 أسعار جملة فقط</option>
                <option value="retail">🏷️ أسعار قطاعي فقط</option>
            </select>

            <!-- Status Filter -->
            <select wire:model.live="statusFilter" class="h-10 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 text-xs font-bold text-slate-700 dark:text-slate-300">
                <option value="all">📌 كل الحالات</option>
                <option value="draft">مسودة</option>
                <option value="sent">تم الإرسال للعميل</option>
                <option value="converted">تم التحويل لفاتورة</option>
                <option value="expired">منتهي الصلاحية</option>
            </select>
        </div>
    </div>

    <!-- Quotations Table -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs text-slate-700 dark:text-slate-300">
                <thead class="bg-slate-50/80 dark:bg-slate-950/60 text-slate-500 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-800 text-[11px]">
                    <tr>
                        <th class="p-3.5">رقم العرض والتاريخ</th>
                        <th class="p-3.5">العميل المستهدف</th>
                        <th class="p-3.5">نوع التسعير</th>
                        <th class="p-3.5">الصلاحية والحالة</th>
                        <th class="p-3.5">إجمالي العرض</th>
                        <th class="p-3.5 text-center">الإجراءات والتحويل</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @forelse($quotations as $quote)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                        <!-- Number & Date -->
                        <td class="p-3.5">
                            <div class="font-mono font-black text-amber-600 dark:text-amber-400 text-xs sm:text-sm">
                                {{ $quote->quotation_number }}
                            </div>
                            <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                {{ $quote->quotation_date->format('Y-m-d') }}
                            </div>
                        </td>

                        <!-- Customer -->
                        <td class="p-3.5">
                            <div class="font-bold text-slate-900 dark:text-white">
                                {{ $quote->target_customer_name }}
                            </div>
                            @if($quote->target_customer_phone)
                            <div class="text-[10px] text-slate-400 font-mono" dir="ltr">
                                📱 {{ $quote->target_customer_phone }}
                            </div>
                            @endif
                        </td>

                        <!-- Pricing Tier Badge -->
                        <td class="p-3.5">
                            @if($quote->pricing_tier === 'wholesale')
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-purple-500/10 text-purple-700 dark:text-purple-300 border border-purple-500/20 font-bold text-[10px]">
                                    <span>🏪 أسعار جملة</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20 font-bold text-[10px]">
                                    <span>🏷️ أسعار قطاعي</span>
                                </span>
                            @endif
                        </td>

                        <!-- Validity & Status -->
                        <td class="p-3.5 space-y-1">
                            <div>
                                @if($quote->isConverted())
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 font-black text-[10px] border border-indigo-500/20">
                                        ⚡ تحول لفاتورة ({{ $quote->convertedInvoice?->invoice_number }})
                                    </span>
                                @elseif($quote->isExpired())
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-rose-500/10 text-rose-700 dark:text-rose-300 font-bold text-[10px] border border-rose-500/20">
                                        ⚠️ منتهي الصلاحية
                                    </span>
                                @elseif($quote->status === 'sent')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 font-bold text-[10px] border border-emerald-500/20">
                                        📲 تم إرساله للعميل
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-bold text-[10px]">
                                        مسودة
                                    </span>
                                @endif
                            </div>
                            @if($quote->valid_until)
                            <div class="text-[10px] text-slate-400 font-mono">
                                ينتهي: {{ $quote->valid_until->format('Y-m-d') }}
                            </div>
                            @endif
                        </td>

                        <!-- Net Total -->
                        <td class="p-3.5">
                            <div class="font-mono font-black text-sm text-slate-900 dark:text-white">
                                {{ number_format($quote->net_total, 2) }} <span class="text-[10px] font-normal">ج.م</span>
                            </div>
                            <div class="text-[10px] text-slate-400">
                                ({{ count($quote->items) }} أصناف)
                            </div>
                        </td>

                        <!-- Actions -->
                        <td class="p-3.5 text-center">
                            <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                <!-- Print A4 -->
                                <a 
                                    href="{{ route('quotations.print', $quote->id) }}" 
                                    target="_blank"
                                    class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-indigo-600 hover:text-white text-slate-700 dark:text-slate-300 transition-colors cursor-pointer"
                                    title="طباعة رسمية A4"
                                >
                                    🖨️
                                </a>

                                <!-- Direct PDF Download -->
                                <a 
                                    href="{{ route('quotations.print', $quote->id) }}?download=1" 
                                    target="_blank"
                                    class="px-2 py-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-600 hover:text-white text-rose-600 dark:text-rose-400 font-bold text-[11px] transition-colors cursor-pointer flex items-center gap-1"
                                    title="تحميل كملف PDF مباشر"
                                >
                                    <span>📥 PDF</span>
                                </a>

                                <!-- WhatsApp Share -->
                                <button 
                                    type="button" 
                                    wire:click="sendWhatsApp({{ $quote->id }})"
                                    class="p-2 rounded-xl bg-emerald-500/10 hover:bg-emerald-600 hover:text-white text-emerald-700 dark:text-emerald-300 transition-colors cursor-pointer"
                                    title="إرسال ومشاركة واتساب"
                                >
                                    📲
                                </button>

                                <!-- Convert to Invoice (If not yet converted) -->
                                @if(!$quote->isConverted())
                                <button 
                                    type="button" 
                                    wire:click="convertQuotationToInvoice({{ $quote->id }})"
                                    wire:confirm="هل أنت متأكد من تحويل عرض السعر رقم [{{ $quote->quotation_number }}] إلى فاتورة مبيعات معتمدة وخصم الأصناف من المخزن؟"
                                    class="px-2.5 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-black text-[11px] shadow-sm transition-all active:scale-95 cursor-pointer flex items-center gap-1"
                                    title="تحويل مباشر لفاتورة مبيعات"
                                >
                                    <span>⚡ تحويل لفاتورة</span>
                                </button>
                                @else
                                <a 
                                    href="{{ route('invoices.show', $quote->converted_invoice_id) }}" 
                                    class="px-2.5 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 font-bold text-[11px] hover:underline"
                                    title="عرض الفاتورة الناتجة"
                                >
                                    عرض الفاتورة ←
                                </a>
                                @endif

                                <!-- Delete (If not converted) -->
                                @if(!$quote->isConverted())
                                <button 
                                    type="button" 
                                    wire:click="deleteQuotation({{ $quote->id }})"
                                    wire:confirm="هل أنت متأكد من حذف عرض السعر؟"
                                    class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500 hover:text-white text-rose-600 transition-colors cursor-pointer"
                                    title="حذف"
                                >
                                    ✕
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="p-12 text-center text-slate-400 text-xs sm:text-sm">
                            لا توجد عروض أسعار مسجلة حالياً. اضغط على "إنشاء عرض أسعار جديد" للبدء.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($quotations->hasPages())
        <div class="p-4 border-t border-slate-100 dark:border-slate-800">
            {{ $quotations->links() }}
        </div>
        @endif
    </div>

</div>
