<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900/60 p-4 sm:p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-tajawal flex items-center gap-2.5">
                <span>🗑️ سلة المحذوفات المركزية (Recycle Bin)</span>
                @if($totalTrashed > 0)
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 font-mono">
                    {{ $totalTrashed }} عنصر مؤرشف
                </span>
                @endif
            </h2>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                استعراض واستعادة كافة السجلات المحذوفة (أصناف، عملاء، موردين، فروع، فواتير، مصروفات، مرتجعات) بضغطة زر واحدة
            </p>
        </div>
        <div>
            <span class="px-3 py-1.5 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-xs font-bold border border-emerald-500/20 flex items-center gap-1.5">
                <span>🛡️ حماية الحذف المرن مفعلة (Zero Data Loss)</span>
            </span>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="bg-white dark:bg-slate-900 p-2 sm:p-3 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-wrap gap-1.5 text-xs font-bold">
        <button wire:click="setTab('items')" class="px-3.5 py-2 rounded-xl transition-all flex items-center gap-2 cursor-pointer {{ $activeTab === 'items' ? 'bg-amber-600 text-white shadow-sm' : 'bg-slate-50 dark:bg-slate-950 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
            <span>📦 الأصناف</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $activeTab === 'items' ? 'bg-white text-amber-700' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }} font-mono">{{ $counts['items'] }}</span>
        </button>

        <button wire:click="setTab('customers')" class="px-3.5 py-2 rounded-xl transition-all flex items-center gap-2 cursor-pointer {{ $activeTab === 'customers' ? 'bg-amber-600 text-white shadow-sm' : 'bg-slate-50 dark:bg-slate-950 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
            <span>👥 العملاء</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $activeTab === 'customers' ? 'bg-white text-amber-700' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }} font-mono">{{ $counts['customers'] }}</span>
        </button>

        <button wire:click="setTab('suppliers')" class="px-3.5 py-2 rounded-xl transition-all flex items-center gap-2 cursor-pointer {{ $activeTab === 'suppliers' ? 'bg-amber-600 text-white shadow-sm' : 'bg-slate-50 dark:bg-slate-950 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
            <span>🏭 الموردون</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $activeTab === 'suppliers' ? 'bg-white text-amber-700' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }} font-mono">{{ $counts['suppliers'] }}</span>
        </button>

        <button wire:click="setTab('stores')" class="px-3.5 py-2 rounded-xl transition-all flex items-center gap-2 cursor-pointer {{ $activeTab === 'stores' ? 'bg-amber-600 text-white shadow-sm' : 'bg-slate-50 dark:bg-slate-950 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
            <span>🏬 الفروع والعربات</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $activeTab === 'stores' ? 'bg-white text-amber-700' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }} font-mono">{{ $counts['stores'] }}</span>
        </button>

        <button wire:click="setTab('invoices')" class="px-3.5 py-2 rounded-xl transition-all flex items-center gap-2 cursor-pointer {{ $activeTab === 'invoices' ? 'bg-amber-600 text-white shadow-sm' : 'bg-slate-50 dark:bg-slate-950 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
            <span>📑 فواتير المبيعات</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $activeTab === 'invoices' ? 'bg-white text-amber-700' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }} font-mono">{{ $counts['invoices'] }}</span>
        </button>

        <button wire:click="setTab('purchases')" class="px-3.5 py-2 rounded-xl transition-all flex items-center gap-2 cursor-pointer {{ $activeTab === 'purchases' ? 'bg-amber-600 text-white shadow-sm' : 'bg-slate-50 dark:bg-slate-950 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
            <span>🛒 فواتير المشتريات</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $activeTab === 'purchases' ? 'bg-white text-amber-700' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }} font-mono">{{ $counts['purchases'] }}</span>
        </button>

        <button wire:click="setTab('expenses')" class="px-3.5 py-2 rounded-xl transition-all flex items-center gap-2 cursor-pointer {{ $activeTab === 'expenses' ? 'bg-amber-600 text-white shadow-sm' : 'bg-slate-50 dark:bg-slate-950 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
            <span>💸 المصروفات</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $activeTab === 'expenses' ? 'bg-white text-amber-700' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }} font-mono">{{ $counts['expenses'] }}</span>
        </button>

        <button wire:click="setTab('returns')" class="px-3.5 py-2 rounded-xl transition-all flex items-center gap-2 cursor-pointer {{ $activeTab === 'returns' ? 'bg-amber-600 text-white shadow-sm' : 'bg-slate-50 dark:bg-slate-950 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
            <span>🔄 المرتجعات</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $activeTab === 'returns' ? 'bg-white text-amber-700' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }} font-mono">{{ $counts['returns'] }}</span>
        </button>
    </div>

    <!-- Search in Trashed Records -->
    <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
        <input 
            type="text" 
            wire:model.live.debounce.300ms="search" 
            placeholder="بحث في العناصر المحذوفة في هذا التبويب..." 
            class="w-full sm:w-80 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-4 py-2 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-amber-500"
        >
        <span class="text-xs text-slate-400 font-mono">
            عرض {{ $records->total() }} سجل مؤرشف
        </span>
    </div>

    <!-- Trashed Records Table Container -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="p-3.5">الرمز / المعرّف</th>
                        <th class="p-3.5">الاسم / البيان</th>
                        <th class="p-3.5">التفاصيل / القيمة</th>
                        <th class="p-3.5">تاريخ ووقت الحذف</th>
                        <th class="p-3.5 text-center">إجراء الاستعادة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60">
                    @forelse($records as $rec)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                        @if($activeTab === 'items')
                            <td class="p-3.5 font-mono font-bold text-amber-600 dark:text-amber-400">{{ $rec->code }}</td>
                            <td class="p-3.5 font-bold text-slate-800 dark:text-slate-100">{{ $rec->name }}</td>
                            <td class="p-3.5 text-slate-600 dark:text-slate-400">
                                رصيد: {{ number_format($rec->current_stock, 2) }} {{ $rec->unit }} | سعر: {{ number_format($rec->selling_price, 2) }} ج.م
                            </td>
                            <td class="p-3.5 font-mono text-slate-500 text-[11px]">{{ $rec->deleted_at?->diffForHumans() }}</td>
                            <td class="p-3.5 text-center">
                                <button wire:click="restoreItem({{ $rec->id }})" class="px-3 py-1.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-600 text-emerald-700 dark:text-emerald-400 hover:text-white font-bold text-xs border border-emerald-500/30 transition-colors inline-flex items-center gap-1.5 cursor-pointer">
                                    <span>♻️ استعادة الصنف</span>
                                </button>
                            </td>

                        @elseif($activeTab === 'customers')
                            <td class="p-3.5 font-mono font-bold text-amber-600 dark:text-amber-400">#{{ $rec->id }}</td>
                            <td class="p-3.5 font-bold text-slate-800 dark:text-slate-100">{{ $rec->name }}</td>
                            <td class="p-3.5 text-slate-600 dark:text-slate-400">
                                رصيد: {{ number_format($rec->current_balance, 2) }} ج.م | هاتف: {{ $rec->phone ?? '—' }}
                            </td>
                            <td class="p-3.5 font-mono text-slate-500 text-[11px]">{{ $rec->deleted_at?->diffForHumans() }}</td>
                            <td class="p-3.5 text-center">
                                <button wire:click="restoreCustomer({{ $rec->id }})" class="px-3 py-1.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-600 text-emerald-700 dark:text-emerald-400 hover:text-white font-bold text-xs border border-emerald-500/30 transition-colors inline-flex items-center gap-1.5 cursor-pointer">
                                    <span>♻️ استعادة العميل</span>
                                </button>
                            </td>

                        @elseif($activeTab === 'suppliers')
                            <td class="p-3.5 font-mono font-bold text-amber-600 dark:text-amber-400">#{{ $rec->id }}</td>
                            <td class="p-3.5 font-bold text-slate-800 dark:text-slate-100">{{ $rec->name }}</td>
                            <td class="p-3.5 text-slate-600 dark:text-slate-400">
                                شركة: {{ $rec->company_name ?? '—' }} | مستحق: {{ number_format($rec->current_balance, 2) }} ج.م
                            </td>
                            <td class="p-3.5 font-mono text-slate-500 text-[11px]">{{ $rec->deleted_at?->diffForHumans() }}</td>
                            <td class="p-3.5 text-center">
                                <button wire:click="restoreSupplier({{ $rec->id }})" class="px-3 py-1.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-600 text-emerald-700 dark:text-emerald-400 hover:text-white font-bold text-xs border border-emerald-500/30 transition-colors inline-flex items-center gap-1.5 cursor-pointer">
                                    <span>♻️ استعادة المورد</span>
                                </button>
                            </td>

                        @elseif($activeTab === 'stores')
                            <td class="p-3.5 font-mono font-bold text-amber-600 dark:text-amber-400">{{ $rec->code }}</td>
                            <td class="p-3.5 font-bold text-slate-800 dark:text-slate-100">{{ $rec->name }}</td>
                            <td class="p-3.5 text-slate-600 dark:text-slate-400">
                                نوع: {{ $rec->type === 'wholesale_van' ? 'عربية توزيع' : 'محل / مخزن' }}
                            </td>
                            <td class="p-3.5 font-mono text-slate-500 text-[11px]">{{ $rec->deleted_at?->diffForHumans() }}</td>
                            <td class="p-3.5 text-center">
                                <button wire:click="restoreStore({{ $rec->id }})" class="px-3 py-1.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-600 text-emerald-700 dark:text-emerald-400 hover:text-white font-bold text-xs border border-emerald-500/30 transition-colors inline-flex items-center gap-1.5 cursor-pointer">
                                    <span>♻️ استعادة الفرع</span>
                                </button>
                            </td>

                        @elseif($activeTab === 'invoices')
                            <td class="p-3.5 font-mono font-bold text-amber-600 dark:text-amber-400">{{ $rec->invoice_number }}</td>
                            <td class="p-3.5 font-bold text-slate-800 dark:text-slate-100">{{ $rec->customer->name ?? 'عميل محذوف' }}</td>
                            <td class="p-3.5 text-slate-600 dark:text-slate-400 font-mono">
                                الصافي: {{ number_format($rec->net_total, 2) }} ج.م ({{ $rec->payment_type }})
                            </td>
                            <td class="p-3.5 font-mono text-slate-500 text-[11px]">{{ $rec->deleted_at?->diffForHumans() }}</td>
                            <td class="p-3.5 text-center">
                                <button wire:click="restoreInvoice({{ $rec->id }})" disabled title="لا يمكن الاستعادة: تم عكس الأثر المخزني والمالي عند الحذف" class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-400 font-bold text-xs border border-slate-300 dark:border-slate-700 inline-flex items-center gap-1.5 cursor-not-allowed opacity-60">
                                    <span>♻️ استعادة الفاتورة</span>
                                </button>
                            </td>

                        @elseif($activeTab === 'purchases')
                            <td class="p-3.5 font-mono font-bold text-amber-600 dark:text-amber-400">{{ $rec->purchase_number }}</td>
                            <td class="p-3.5 font-bold text-slate-800 dark:text-slate-100">{{ $rec->supplier->name ?? 'مورد محذوف' }}</td>
                            <td class="p-3.5 text-slate-600 dark:text-slate-400 font-mono">
                                الإجمالي: {{ number_format($rec->net_total, 2) }} ج.م
                            </td>
                            <td class="p-3.5 font-mono text-slate-500 text-[11px]">{{ $rec->deleted_at?->diffForHumans() }}</td>
                            <td class="p-3.5 text-center">
                                <button wire:click="restorePurchase({{ $rec->id }})" disabled title="لا يمكن الاستعادة: تم عكس الأثر المخزني عند الحذف" class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-400 font-bold text-xs border border-slate-300 dark:border-slate-700 inline-flex items-center gap-1.5 cursor-not-allowed opacity-60">
                                    <span>♻️ استعادة التوريد</span>
                                </button>
                            </td>

                        @elseif($activeTab === 'expenses')
                            <td class="p-3.5 font-mono font-bold text-amber-600 dark:text-amber-400">{{ $rec->expense_number }}</td>
                            <td class="p-3.5 font-bold text-slate-800 dark:text-slate-100">{{ $rec->title }} ({{ $rec->category }})</td>
                            <td class="p-3.5 text-slate-600 dark:text-slate-400 font-mono font-bold text-rose-600 dark:text-rose-400">
                                {{ number_format($rec->amount, 2) }} ج.م
                            </td>
                            <td class="p-3.5 font-mono text-slate-500 text-[11px]">{{ $rec->deleted_at?->diffForHumans() }}</td>
                            <td class="p-3.5 text-center">
                                <button wire:click="restoreExpense({{ $rec->id }})" class="px-3 py-1.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-600 text-emerald-700 dark:text-emerald-400 hover:text-white font-bold text-xs border border-emerald-500/30 transition-colors inline-flex items-center gap-1.5 cursor-pointer">
                                    <span>♻️ استعادة المصروف</span>
                                </button>
                            </td>

                        @elseif($activeTab === 'returns')
                            <td class="p-3.5 font-mono font-bold text-amber-600 dark:text-amber-400">{{ $rec->return_number }}</td>
                            <td class="p-3.5 font-bold text-slate-800 dark:text-slate-100">{{ $rec->return_type === 'sales_return' ? 'مرتجع مبيعات' : 'مرتجع مشتريات' }}</td>
                            <td class="p-3.5 text-slate-600 dark:text-slate-400 font-mono font-bold">
                                {{ number_format($rec->total_amount, 2) }} ج.م
                            </td>
                            <td class="p-3.5 font-mono text-slate-500 text-[11px]">{{ $rec->deleted_at?->diffForHumans() }}</td>
                            <td class="p-3.5 text-center">
                                <button wire:click="restoreReturn({{ $rec->id }})" class="px-3 py-1.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-600 text-emerald-700 dark:text-emerald-400 hover:text-white font-bold text-xs border border-emerald-500/30 transition-colors inline-flex items-center gap-1.5 cursor-pointer">
                                    <span>♻️ استعادة المرتجع</span>
                                </button>
                            </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="p-12 text-center text-slate-400">
                            لا توجد عناصر محذوفة في هذا التبويب 🌟
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            {{ $records->links() }}
        </div>
    </div>
</div>
