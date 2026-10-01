<div class="space-y-6 max-w-4xl mx-auto">
    <!-- Header Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900/60 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div>
            <h2 class="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>فاتورة مبيعات: {{ $invoice->invoice_number }}</span>
                @if($invoice->status === 'cancelled')
                    <span class="px-2 py-0.5 rounded text-xs font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">ملغاة</span>
                @else
                    <span class="px-2 py-0.5 rounded text-xs font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">معتمدة</span>
                @endif
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">تاريخ الإصدار: {{ $invoice->invoice_date->format('Y-m-d') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if($invoice->status !== 'cancelled')
            @can('invoices.edit')
            <a href="{{ route('invoices.edit', $invoice->id) }}" class="px-3 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-xs rounded-xl shadow-lg shadow-amber-500/20 flex items-center gap-1.5 transition-all cursor-pointer">
                <span>✏️ تعديل</span>
            </a>
            @endcan
            @endif
            <a href="{{ route('invoices.print.thermal', $invoice->id) }}" target="_blank" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-600/30 flex items-center gap-1.5 transition-all">
                <span>🖨️ إيصال كاشير</span>
            </a>
            <a href="{{ route('invoices.print.a4', $invoice->id) }}" target="_blank" class="px-3 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 flex items-center gap-1.5 transition-all">
                <span>📄 فاتورة A4 / PDF</span>
            </a>
            <button onclick="downloadCardAsImage()" id="btn-show-img" class="px-3 py-2 bg-amber-500/10 hover:bg-amber-500 text-amber-700 dark:text-amber-300 hover:text-slate-950 text-xs font-bold rounded-xl border border-amber-500/30 flex items-center gap-1.5 transition-all cursor-pointer">
                <span>📸 تحميل صورة (PNG)</span>
            </button>
            @can('invoices.delete')
            <button
                wire:click="deleteInvoice"
                wire:confirm="هل أنت متأكد من حذف هذه الفاتورة نهائياً؟ سيتم إرجاع البضاعة للمخزن وتحديث الرصيد وحذف السجل تماماً."
                class="px-3 py-2 bg-rose-500/10 hover:bg-rose-600 text-rose-600 hover:text-white dark:text-rose-400 text-xs font-bold rounded-xl border border-rose-500/30 flex items-center gap-1.5 transition-all cursor-pointer"
                title="حذف نهائي للفاتورة"
            >
                <span>🗑️ حذف</span>
            </button>
            @endcan
            <a href="{{ route('invoices.index') }}" class="px-3 py-2 bg-slate-100 dark:bg-slate-950 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-800 transition-colors">
                ← رجوع
            </a>
        </div>
    </div>

    <!-- Invoice Details Card -->
    <div id="invoice-card-container" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 space-y-6 shadow-sm">
        <!-- Customer & Store & Info Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 text-xs">
            <div>
                <span class="text-slate-500">بيانات العميل:</span>
                <div class="font-bold text-slate-900 dark:text-white text-sm mt-1">{{ $invoice->customer->name }}</div>
                <div class="text-slate-500 dark:text-slate-400 mt-0.5">الهاتف: {{ $invoice->customer->phone ?? '—' }}</div>
            </div>
            <div>
                <span class="text-slate-500">الفرع / نقطة البيع المصدرة:</span>
                @if($invoice->store)
                <div class="font-bold text-slate-900 dark:text-white text-sm mt-1 flex items-center gap-1.5">
                    <span>{{ $invoice->store->type === 'wholesale_van' ? '🚚' : ($invoice->store->is_main ? '🏢' : '🏬') }}</span>
                    <span>{{ $invoice->store->name }}</span>
                    <span class="text-xs text-emerald-600 dark:text-emerald-400 font-mono">({{ $invoice->store->code ?: 'B'.$invoice->store->id }})</span>
                </div>
                <div class="text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ $invoice->store->address ?: ($invoice->store->type === 'wholesale_van' ? 'عربية توزيع متنقلة' : 'المقر الرئيسي') }}
                    @if($invoice->store->phone) | هاتف: {{ $invoice->store->phone }} @endif
                </div>
                @else
                <div class="font-bold text-slate-900 dark:text-white text-sm mt-1">المخزن الرئيسي</div>
                @endif
                <div class="text-[11px] text-slate-400 mt-0.5">مسؤول البيع: {{ $invoice->user?->name ?? 'المدير' }}</div>
            </div>
            <div>
                <span class="text-slate-500">حالة السداد:</span>
                <div class="font-bold text-sm mt-1">
                    @if($invoice->payment_status === 'paid')
                        <span class="text-emerald-600 dark:text-emerald-400">مدفوعة بالكامل{{ $invoice->payment_type === 'credit' ? ' (بسند قبض)' : ' (نقدي)' }}</span>
                    @elseif($invoice->payment_status === 'partially_paid')
                        <span class="text-amber-600 dark:text-amber-400">مسددة جزئياً</span>
                    @else
                        <span class="text-rose-600 dark:text-rose-400">غير مسددة (آجل على الحساب)</span>
                    @endif
                </div>
                <div class="text-slate-500 dark:text-slate-400 mt-0.5">نوع الفاتورة: {{ ['cash' => 'كاش فوري', 'credit' => 'آجل (ذمم)', 'partial' => 'دفع جزئي'][$invoice->payment_type] ?? $invoice->payment_type }}</div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-xl">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="p-3">#</th>
                        <th class="p-3">الصنف</th>
                        <th class="p-3 text-center">الكمية</th>
                        <th class="p-3 text-center">سعر الوحدة</th>
                        <th class="p-3 text-center">الخصم</th>
                        <th class="p-3 text-left">الإجمالي</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60">
                    @foreach($invoice->items as $idx => $line)
                    <tr>
                        <td class="p-3 text-slate-400 font-mono">{{ $idx + 1 }}</td>
                        <td class="p-3 font-bold text-slate-800 dark:text-slate-200">
                            {{ $line->item->name }}
                            <div class="text-[10px] text-slate-500 font-mono">كود: {{ $line->item->code }}</div>
                        </td>
                        <td class="p-3 text-center font-mono font-bold text-slate-800 dark:text-slate-200">{{ number_format($line->quantity, 2) }} {{ $line->item->unit }}</td>
                        <td class="p-3 text-center font-mono text-slate-700 dark:text-slate-300">{{ number_format($line->unit_price, 2) }}</td>
                        <td class="p-3 text-center font-mono text-rose-600 dark:text-rose-400">{{ number_format($line->discount_amount, 2) }}</td>
                        <td class="p-3 text-left font-mono font-bold text-slate-900 dark:text-white">{{ number_format($line->total_price, 2) }} ج.م</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Additional Expenses Breakdown (If Any) -->
        @if($invoice->additionalExpenses && $invoice->additionalExpenses->count() > 0)
        <div>
            <h4 class="font-bold text-slate-800 dark:text-slate-200 text-xs mb-2">🚚 تفاصيل المصاريف الإضافية / الشحن المحملة:</h4>
            <div class="overflow-x-auto border border-amber-500/20 rounded-xl bg-amber-500/5">
                <table class="w-full text-right text-xs">
                    <thead class="bg-amber-500/10 text-amber-900 dark:text-amber-300 font-bold border-b border-amber-500/20">
                        <tr>
                            <th class="p-2.5">بند المصروف</th>
                            <th class="p-2.5 text-center">المبلغ</th>
                            <th class="p-2.5 text-center">طريقة السداد / التحميل</th>
                            <th class="p-2.5 text-left">ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-amber-500/10 text-xs">
                        @foreach($invoice->additionalExpenses as $exp)
                        <tr>
                            <td class="p-2.5 font-bold text-slate-900 dark:text-white">{{ $exp->title }}</td>
                            <td class="p-2.5 text-center font-mono font-black text-amber-600 dark:text-amber-400">+{{ number_format($exp->amount, 2) }} ج.م</td>
                            <td class="p-2.5 text-center">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-700 dark:text-amber-400">
                                    {{ $exp->paid_by_label }}
                                </span>
                            </td>
                            <td class="p-2.5 text-left text-slate-500 font-mono">{{ $exp->notes ?: '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <!-- Summary Totals -->
        <div class="w-full sm:w-80 mr-auto p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
            <div class="flex justify-between text-slate-500 dark:text-slate-400">
                <span>المجموع الفرعي:</span>
                <span class="font-mono font-bold text-slate-900 dark:text-white">{{ number_format($invoice->subtotal, 2) }} ج.م</span>
            </div>
            @if(bccomp($invoice->discount_amount, '0.000', 3) > 0)
            <div class="flex justify-between text-rose-600 dark:text-rose-400">
                <span>خصم الفاتورة:</span>
                <span class="font-mono font-bold">-{{ number_format($invoice->discount_amount, 2) }} ج.م</span>
            </div>
            @endif
            @if(bccomp($invoice->shipping_cost ?? '0.000', '0.000', 3) > 0)
            <div class="flex justify-between text-amber-600 dark:text-amber-400">
                <span>مصاريف الشحن / التوصيل:</span>
                <span class="font-mono font-bold">+{{ number_format($invoice->shipping_cost, 2) }} ج.م</span>
            </div>
            @endif
            <div class="flex justify-between text-base font-black text-emerald-600 dark:text-emerald-400 pt-2 border-t border-slate-200 dark:border-slate-800">
                <span>الصافي المطلوب:</span>
                <span class="font-mono">{{ number_format($invoice->net_total, 2) }} ج.م</span>
            </div>
            <div class="flex justify-between text-slate-700 dark:text-slate-300">
                <span>المدفوع:</span>
                <span class="font-mono font-bold">{{ number_format($invoice->paid_amount, 2) }} ج.م</span>
            </div>
            <div class="flex justify-between font-bold {{ bccomp($invoice->remaining_amount, '0.000', 3) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-400' }}">
                <span>المتبقي:</span>
                <span class="font-mono">{{ number_format($invoice->remaining_amount, 2) }} ج.م</span>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        function downloadCardAsImage() {
            const btn = document.getElementById('btn-show-img');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span>جاري التجهيز... ⏳</span>';
            btn.style.opacity = '0.7';

            const card = document.getElementById('invoice-card-container');
            if (!card) {
                alert('تعذر العثور على الفاتورة');
                btn.innerHTML = originalText;
                btn.style.opacity = '1';
                return;
            }

            html2canvas(card, {
                scale: 2,
                useCORS: true,
                backgroundColor: document.documentElement.classList.contains('dark') ? '#0f172a' : '#ffffff',
                logging: false
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'فاتورة-مبيعات-{{ $invoice->invoice_number }}.png';
                link.href = canvas.toDataURL('image/png');
                link.click();

                btn.innerHTML = '<span>تم التحميل ✅</span>';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.opacity = '1';
                }, 2000);
            }).catch(err => {
                console.error(err);
                btn.innerHTML = originalText;
                btn.style.opacity = '1';
                alert('حدث خطأ أثناء حفظ الفاتورة.');
            });
        }
    </script>
</div>
