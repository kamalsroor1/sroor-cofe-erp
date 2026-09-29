<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900/60 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div>
            <h2 class="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>📑 سجل فواتير المبيعات الصادرة</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">متابعة الفواتير المعتمدة، حالات السداد والآجل، وإلغاء الفواتير وفق الأصول المحاسبية</p>
        </div>
        <a href="{{ route('invoices.create') }}" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2 transition-all cursor-pointer">
            <span>+ فاتورة بيع جديدة (F2)</span>
        </a>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
            <div class="flex flex-col sm:flex-row items-center gap-2 w-full lg:w-auto">
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="بحث برقم الفاتورة أو اسم العميل..." 
                    class="w-full sm:w-64 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-4 py-2 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-emerald-500"
                >
                <select 
                    wire:model.live="selectedStore" 
                    class="w-full sm:w-56 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500 font-bold [&>option]:bg-white [&>option]:text-slate-900 dark:[&>option]:bg-slate-900 dark:[&>option]:text-slate-100"
                >
                    <option class="bg-white dark:bg-slate-900 text-slate-900 dark:text-white" value="">🏢 كل الفروع ونقاط البيع</option>
                    @foreach($stores as $st)
                        <option class="bg-white dark:bg-slate-900 text-slate-900 dark:text-white" value="{{ $st->id }}">{{ $st->type === 'wholesale_van' ? '🚚 ' : ($st->is_main ? '🏢 ' : '🏬 ') }}{{ $st->name }} ({{ $st->code ?: 'B'.$st->id }})</option>
                    @endforeach
                </select>
            </div>

            <!-- Date Filter Inputs -->
            <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-950 p-1.5 rounded-xl border border-slate-300 dark:border-slate-700 text-xs">
                <div class="flex items-center gap-1">
                    <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 shrink-0">📅 من:</span>
                    <x-datepicker wire:model.live="fromDate" class="!h-8 !w-32 !py-1 !px-2 !text-xs" placeholder="من تاريخ" />
                </div>
                <div class="flex items-center gap-1">
                    <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 shrink-0">إلى:</span>
                    <x-datepicker wire:model.live="toDate" class="!h-8 !w-32 !py-1 !px-2 !text-xs" placeholder="إلى تاريخ" />
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-slate-500 dark:text-slate-400 text-[11px] hidden sm:inline">الحالة:</span>
                <button wire:click="$set('filterStatus', 'active')" class="px-2.5 py-1.5 rounded-lg font-bold border transition-colors cursor-pointer text-xs {{ $filterStatus === 'active' ? 'bg-emerald-600 text-white border-emerald-500' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400' }}">النشطة</button>
                <button wire:click="$set('filterStatus', 'trashed')" class="px-2.5 py-1.5 rounded-lg font-bold border transition-colors cursor-pointer text-xs flex items-center gap-1 {{ $filterStatus === 'trashed' ? 'bg-rose-600 text-white border-rose-500' : 'border-slate-200 dark:border-slate-800 text-rose-600 dark:text-rose-400' }}">
                    <span>سلة المحذوفات</span>
                    @if($trashedCount > 0)
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $filterStatus === 'trashed' ? 'bg-white text-rose-600' : 'bg-rose-500/20 text-rose-600' }} font-mono font-bold">{{ $trashedCount }}</span>
                    @endif
                </button>
                <button wire:click="$set('filterStatus', 'all')" class="px-2.5 py-1.5 rounded-lg font-bold border transition-colors cursor-pointer text-xs {{ $filterStatus === 'all' ? 'bg-slate-700 text-white border-slate-600' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400' }}">الكل</button>
            </div>

            <!-- Payment Status & Method Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex flex-wrap items-center gap-1">
                    <span class="text-slate-500 dark:text-slate-400 text-[11px] hidden sm:inline">السداد:</span>
                    <button wire:click="$set('paymentStatus', 'all')" class="px-2 py-1 rounded-lg font-bold border transition-colors cursor-pointer text-xs {{ $paymentStatus === 'all' ? 'bg-slate-200 dark:bg-slate-800 border-slate-300 dark:border-slate-600 text-slate-900 dark:text-white' : 'border-transparent text-slate-500 dark:text-slate-400' }}">الكل</button>
                    <button wire:click="$set('paymentStatus', 'unpaid')" class="px-2 py-1 rounded-lg font-bold border transition-colors cursor-pointer text-xs {{ $paymentStatus === 'unpaid' ? 'bg-rose-500/20 border-rose-500/40 text-rose-700 dark:text-rose-400' : 'border-transparent text-slate-500 dark:text-slate-400' }}">آجل</button>
                    <button wire:click="$set('paymentStatus', 'partially_paid')" class="px-2 py-1 rounded-lg font-bold border transition-colors cursor-pointer text-xs {{ $paymentStatus === 'partially_paid' ? 'bg-amber-500/20 border-amber-500/40 text-amber-700 dark:text-amber-400' : 'border-transparent text-slate-500 dark:text-slate-400' }}">جزئي</button>
                    <button wire:click="$set('paymentStatus', 'paid')" class="px-2 py-1 rounded-lg font-bold border transition-colors cursor-pointer text-xs {{ $paymentStatus === 'paid' ? 'bg-emerald-500/20 border-emerald-500/40 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-slate-500 dark:text-slate-400' }}">مدفوع</button>
                </div>

                <!-- Payment Method Filter Buttons -->
                <div class="flex flex-wrap items-center gap-1 border-r border-slate-200 dark:border-slate-800 pr-2">
                    <span class="text-slate-500 dark:text-slate-400 text-[11px] hidden sm:inline">وسيلة الدفع:</span>
                    <button wire:click="$set('paymentMethod', 'all')" class="px-2 py-1 rounded-lg font-bold border transition-colors cursor-pointer text-xs {{ $paymentMethod === 'all' ? 'bg-slate-200 dark:bg-slate-800 border-slate-300 dark:border-slate-600 text-slate-900 dark:text-white' : 'border-transparent text-slate-500 dark:text-slate-400' }}">الكل</button>
                    <button wire:click="$set('paymentMethod', 'cash')" class="px-2 py-1 rounded-lg font-bold border transition-colors cursor-pointer text-xs flex items-center gap-1 {{ $paymentMethod === 'cash' ? 'bg-emerald-500/20 border-emerald-500/40 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-slate-500 dark:text-slate-400' }}">
                        <span>💵</span>
                        <span>كاش</span>
                    </button>
                    <button wire:click="$set('paymentMethod', 'instapay')" class="px-2 py-1 rounded-lg font-bold border transition-colors cursor-pointer text-xs flex items-center gap-1 {{ $paymentMethod === 'instapay' ? 'bg-purple-500/20 border-purple-500/40 text-purple-700 dark:text-purple-400' : 'border-transparent text-slate-500 dark:text-slate-400' }}">
                        <span>⚡</span>
                        <span>إنستاباي</span>
                    </button>
                    <button wire:click="$set('paymentMethod', 'e_wallet')" class="px-2 py-1 rounded-lg font-bold border transition-colors cursor-pointer text-xs flex items-center gap-1 {{ $paymentMethod === 'e_wallet' ? 'bg-rose-500/20 border-rose-500/40 text-rose-700 dark:text-rose-400' : 'border-transparent text-slate-500 dark:text-slate-400' }}">
                        <span>📲</span>
                        <span>محفظة</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Cards View (Visible on screens < 640px) -->
    <div class="sm:hidden space-y-3">
        @forelse($invoices as $inv)
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 space-y-3 shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800/80 pb-2.5">
                <div>
                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm">{{ $inv->invoice_number }}</span>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ $inv->invoice_date->format('Y-m-d') }}</span>
                        @if($inv->store)
                        <span class="text-slate-300 dark:text-slate-700">•</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                            {{ $inv->store->type === 'wholesale_van' ? '🚚 ' : ($inv->store->is_main ? '🏢 ' : '🏬 ') }}{{ $inv->store->name }}
                        </span>
                        @endif
                    </div>
                </div>
                <div>
                    @if($inv->status === 'cancelled')
                        <span class="px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">ملغاة</span>
                    @elseif($inv->payment_status === 'paid')
                        <span class="px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">مدفوعة</span>
                    @elseif($inv->payment_status === 'partially_paid')
                        <span class="px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">مسدد جزئي</span>
                    @else
                        <span class="px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">آجل</span>
                    @endif
                </div>
            </div>

            <div class="flex items-center justify-between text-xs">
                <div>
                    <span class="text-slate-500 dark:text-slate-400">العميل:</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200 mr-1">{{ $inv->customer->name }}</span>
                </div>
                <div class="font-mono font-bold text-slate-900 dark:text-white text-sm">
                    {{ number_format($inv->net_total, 2) }} ج.م
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 p-2 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800 text-[11px] font-mono">
                <div>
                    <span class="text-slate-500">المدفوع:</span>
                    <span class="text-emerald-600 dark:text-emerald-400 font-bold mr-1">{{ number_format($inv->paid_amount, 2) }}</span>
                </div>
                <div class="text-left">
                    <span class="text-slate-500">المتبقي:</span>
                    <span class="font-bold {{ bccomp($inv->remaining_amount, '0.000', 3) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-400' }} mr-1">{{ number_format($inv->remaining_amount, 2) }}</span>
                </div>
            </div>

            <div class="flex items-center gap-1.5 pt-1">
                <a href="{{ route('invoices.show', $inv->id) }}" class="flex-1 py-1.5 text-center rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold text-xs border border-slate-300 dark:border-slate-700">
                    عرض / طباعة
                </a>
                <a href="{{ route('invoices.print.thermal', $inv->id) }}" target="_blank" class="px-3 py-1.5 text-center rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-sm">
                    🖨️
                </a>
                @if($inv->status !== 'cancelled')
                @can('invoices.edit')
                <a href="{{ route('invoices.edit', $inv->id) }}" class="px-3 py-1.5 text-center rounded-xl bg-amber-500/10 hover:bg-amber-500 text-amber-600 hover:text-slate-950 dark:text-amber-400 font-bold text-xs border border-amber-500/30">
                    ✏️
                </a>
                @endcan
                @endif
                @can('invoices.delete')
                <button
                    wire:click="deleteInvoice({{ $inv->id }})"
                    wire:confirm="هل أنت متأكد من حذف الفاتورة رقم {{ $inv->invoice_number }} نهائياً؟"
                    class="px-3 py-1.5 text-center rounded-xl bg-rose-500/10 hover:bg-rose-600 text-rose-600 hover:text-white dark:text-rose-400 font-bold text-xs border border-rose-500/30 cursor-pointer"
                >
                    🗑️
                </button>
                @endcan
            </div>
        </div>
        @empty
        <div class="p-8 text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl text-slate-400 text-xs">
            لا توجد فواتير مسجلة
        </div>
        @endforelse
    </div>

    <!-- Desktop / Tablet Table View (Hidden on screens < 640px) -->
    <div class="hidden sm:block bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="p-3.5">رقم الفاتورة</th>
                        <th class="p-3.5">العميل</th>
                        <th class="p-3.5">الفرع / نقطة البيع</th>
                        <th class="p-3.5">التاريخ</th>
                        <th class="p-3.5">نوع الدفع</th>
                        <th class="p-3.5">الصافي المطلوب</th>
                        <th class="p-3.5">المدفوع</th>
                        <th class="p-3.5">المتبقي</th>
                        <th class="p-3.5">الحالة</th>
                        <th class="p-3.5 text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60">
                    @forelse($invoices as $inv)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="p-3.5 font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ $inv->invoice_number }}</td>
                        <td class="p-3.5 font-bold text-slate-800 dark:text-slate-100">{{ $inv->customer->name }}</td>
                        <td class="p-3.5">
                            @if($inv->store)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold {{ $inv->store->type === 'wholesale_van' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20' : ($inv->store->is_main ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20' : 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20') }}">
                                    <span>{{ $inv->store->type === 'wholesale_van' ? '🚚' : ($inv->store->is_main ? '🏢' : '🏬') }}</span>
                                    <span>{{ $inv->store->name }}</span>
                                    <span class="text-[10px] opacity-75 font-mono">({{ $inv->store->code ?: 'B'.$inv->store->id }})</span>
                                </span>
                            @else
                                <span class="text-slate-400 text-xs">—</span>
                            @endif
                        </td>
                        <td class="p-3.5 font-mono text-slate-500 dark:text-slate-400">{{ $inv->invoice_date->format('Y-m-d') }}</td>
                        <td class="p-3.5 text-slate-700 dark:text-slate-300">
                            <div class="flex flex-col gap-1">
                                <span class="font-bold text-xs">
                                    @if($inv->payment_type === 'cash') كاش فوري
                                    @elseif($inv->payment_type === 'credit') آجل (ذمم)
                                    @else دفع جزئي
                                    @endif
                                </span>
                                @if($inv->payment_type !== 'credit')
                                    @php $pm = $inv->payment_method ?? 'cash'; @endphp
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 rounded-md w-fit border
                                        {{ $pm === 'cash' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' : ($pm === 'instapay' ? 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20' : ($pm === 'e_wallet' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20' : ($pm === 'visa' ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20' : 'bg-slate-100 text-slate-600 border-slate-200'))) }}">
                                        @if($pm === 'cash') <span>💵 كاش</span>
                                        @elseif($pm === 'instapay') <span>⚡ إنستاباي</span>
                                        @elseif($pm === 'e_wallet') <span>📲 محفظة</span>
                                        @elseif($pm === 'visa') <span>💳 فيزا</span>
                                        @elseif($pm === 'bank_transfer') <span>🏦 تحويل</span>
                                        @else <span>{{ $pm }}</span>
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="p-3.5 font-mono font-bold text-slate-900 dark:text-white">{{ number_format($inv->net_total, 2) }} ج.م</td>
                        <td class="p-3.5 font-mono text-emerald-600 dark:text-emerald-400">{{ number_format($inv->paid_amount, 2) }}</td>
                        <td class="p-3.5 font-mono font-bold {{ bccomp($inv->remaining_amount, '0.000', 3) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-400' }}">
                            {{ number_format($inv->remaining_amount, 2) }}
                        </td>
                        <td class="p-3.5">
                            @if($inv->status === 'cancelled')
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">ملغاة</span>
                            @elseif($inv->payment_status === 'paid')
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">مدفوعة</span>
                            @elseif($inv->payment_status === 'partially_paid')
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">مسدد جزئي</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">آجل</span>
                            @endif
                        </td>
                        <td class="p-3.5 text-center flex items-center justify-center gap-1.5">
                            @if($inv->trashed())
                                <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 font-bold text-[11px] border border-slate-300 dark:border-slate-700" title="تم عكس أثر الفاتورة المخزني والمالي عند الحذف؛ أنشئ فاتورة جديدة بدلاً من الاستعادة">
                                    🗑️ محذوفة
                                </span>
                            @else
                                <a href="{{ route('invoices.show', $inv->id) }}" class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white font-bold text-[11px] transition-colors border border-slate-300 dark:border-slate-700">
                                    تفاصيل / طباعة
                                </a>
                                @if($inv->status !== 'cancelled')
                                @can('invoices.edit')
                                <a href="{{ route('invoices.edit', $inv->id) }}" class="px-2 py-1 rounded-lg bg-amber-500/10 hover:bg-amber-600 hover:text-slate-950 text-amber-600 dark:text-amber-400 text-[11px] font-bold border border-amber-500/30 transition-all flex items-center gap-1 cursor-pointer" title="تعديل الفاتورة">
                                    <span>✏️ تعديل</span>
                                </a>
                                @endcan
                                @endif
                                @can('invoices.delete')
                                <button
                                    wire:click="deleteInvoice({{ $inv->id }})"
                                    wire:confirm="هل أنت متأكد من أرشفة الفاتورة رقم {{ $inv->invoice_number }} ونقلها لسلة المحذوفات؟"
                                    class="px-2 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-600 hover:text-white text-rose-600 dark:text-rose-400 text-[11px] font-bold border border-rose-500/30 transition-all flex items-center gap-1 cursor-pointer"
                                    title="نقل لسلة المحذوفات"
                                >
                                    <span>🗑️</span>
                                </button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="p-12 text-center text-slate-400">لا توجد فواتير مسجلة</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            {{ $invoices->links() }}
        </div>
    </div>

    <!-- Pagination for Mobile -->
    <div class="sm:hidden p-2">
        {{ $invoices->links() }}
    </div>

    <!-- Cancel Invoice Modal -->
    @if($showCancelModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-md p-6 space-y-4 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                <h3 class="font-bold text-rose-600 dark:text-rose-400 text-base">إلغاء فاتورة مبيعات معتمدة</h3>
                <button wire:click="$set('showCancelModal', false)" class="text-slate-400 hover:text-slate-700 dark:hover:text-white">✕</button>
            </div>

            @if($errorMessage)
            <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300 text-xs">
                {{ $errorMessage }}
            </div>
            @endif

            <p class="text-xs text-slate-600 dark:text-slate-300">
                تنبيه: إلغاء الفاتورة سيعيد بضاعة الفاتورة بالكامل للمخزن، ويخصم المبلغ من حساب العميل، ولن يتم حذف الفاتورة بل ستحتفظ بسجل الإلغاء.
            </p>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">سبب الإلغاء (إلزامي):</label>
                <textarea wire:model="cancelReason" rows="3" placeholder="اكتب سبب إلغاء الفاتورة..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl p-3 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-rose-500"></textarea>
                @error('cancelReason') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                <button type="button" wire:click="$set('showCancelModal', false)" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold cursor-pointer">إلغاء الأمر</button>
                <button type="button" wire:click="confirmCancel" class="px-5 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-rose-600/30 cursor-pointer">تأكيد الإلغاء وعكس المخزن</button>
            </div>
        </div>
    </div>
    @endif
</div>
