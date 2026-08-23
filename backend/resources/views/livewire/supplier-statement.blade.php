<div class="space-y-6">
    <!-- 🖨️ Formal A4 Print Header (Visible ONLY on Print) -->
    <div class="print-only mb-6 border-b-2 border-black pb-4">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
                @php
                    $showLogo = \App\Models\Setting::getBool('show_print_logo', true);
                    $logoPath = public_path('logo.png');
                    $logoSrc = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : asset('logo.png');
                    $companyName = \App\Models\Setting::get('company_name', config('app.name', 'منظومة ERP'));
                    $companySubtitle = \App\Models\Setting::get('company_subtitle', '');
                @endphp
                @if($showLogo)
                    <img src="{{ $logoSrc }}" alt="Logo" style="max-height: 60px; max-width: 110px; object-fit: contain;">
                @endif
                <div>
                    <h1 class="text-xl font-black text-black">{{ $companyName }}</h1>
                    @if($companySubtitle)
                        <p class="text-xs text-gray-700 font-bold">{{ $companySubtitle }}</p>
                    @endif
                </div>
            </div>
            <div class="text-left">
                <h2 class="text-lg font-black text-black">كشف حساب مورد تفصيلي</h2>
                <div class="text-xs text-black font-bold">المورد: {{ $supplier->name }}</div>
                <div class="text-[11px] text-gray-700">تاريخ الطباعة: {{ now()->format('Y-m-d h:i A') }}</div>
            </div>
        </div>
    </div>

    <!-- Header (Screen Only) -->
    <div class="no-print flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900/60 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div>
            <h2 class="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>📑 كشف حساب تفصيلي للمورد: {{ $supplier->name }}</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">سجل فواتير التوريد، سندات الصرف، والمرتجعات والرصيد التراكمي المستحق للمورد</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if(bccomp($current_balance, '0.000', 3) > 0)
            <button wire:click="openPaymentModal" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-600/30 flex items-center gap-1.5 transition-all cursor-pointer">
                <span>💵 سداد دفعة وتنزيل مديونية</span>
            </button>
            @endif
            <a href="{{ route('suppliers.export.csv', $supplier->id) }}" class="px-3.5 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 flex items-center gap-1.5 transition-all">
                📊 تصدير إكسيل
            </a>
            <button onclick="window.print()" class="px-3.5 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 flex items-center gap-1.5 transition-all cursor-pointer">
                🖨️ طباعة
            </button>
            <a href="{{ route('suppliers.index') }}" class="px-3 py-2.5 bg-slate-100 dark:bg-slate-950 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-800">
                ← رجوع
            </a>
        </div>
    </div>

    @if (session()->has('success'))
    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-xs flex items-center gap-2">
        <span>✅ {{ session('success') }}</span>
    </div>
    @endif

    <!-- Overview Card & Date Filters -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 text-xs shadow-sm items-center">
        <div>
            <span class="text-slate-500">اسم الشركة / المصنع:</span>
            <div class="font-bold text-slate-900 dark:text-white mt-0.5">{{ $supplier->company_name ?? '—' }}</div>
        </div>
        <div>
            <span class="text-slate-500">رقم الهاتف:</span>
            <div class="font-bold text-slate-900 dark:text-white font-mono mt-0.5">{{ $supplier->phone ?? '—' }}</div>
        </div>
        <div>
            <span class="text-slate-500">الرصيد المستحق للمورد حالياً:</span>
            <div class="font-black text-base font-mono mt-0.5 {{ bccomp($current_balance, '0.000', 3) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                {{ number_format($current_balance, 2) }} ج.م
            </div>
        </div>
        <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-950 p-2 rounded-xl border border-slate-300 dark:border-slate-700">
            <div class="flex items-center gap-1 w-1/2">
                <span class="text-[10px] font-bold text-slate-500 shrink-0">من:</span>
                <x-datepicker wire:model.live="fromDate" class="!h-7 !py-0.5 !px-1.5 !text-xs" placeholder="من تاريخ" />
            </div>
            <div class="flex items-center gap-1 w-1/2">
                <span class="text-[10px] font-bold text-slate-500 shrink-0">إلى:</span>
                <x-datepicker wire:model.live="toDate" class="!h-7 !py-0.5 !px-1.5 !text-xs" placeholder="إلى تاريخ" />
            </div>
        </div>
    </div>

    <!-- Ledger Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="p-3.5">التاريخ</th>
                        <th class="p-3.5">نوع الحركة</th>
                        <th class="p-3.5">رقم السند / الفاتورة</th>
                        <th class="p-3.5 text-amber-600 dark:text-amber-400">مستحق للمورد (+) توريد</th>
                        <th class="p-3.5 text-emerald-600 dark:text-emerald-400">سداد للمورد (-) صرف</th>
                        <th class="p-3.5 font-bold text-slate-900 dark:text-white">الرصيد بعد الحركة</th>
                        <th class="p-3.5">ملاحظات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60">
                    @forelse($entries as $row)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="p-3.5 font-mono text-slate-500 dark:text-slate-400">{{ $row['date'] }}</td>
                        <td class="p-3.5 font-bold text-slate-800 dark:text-slate-200">{{ $row['type'] }}</td>
                        <td class="p-3.5 font-mono text-slate-700 dark:text-slate-300">{{ $row['ref_number'] }}</td>
                        <td class="p-3.5 font-mono font-bold text-amber-600 dark:text-amber-400">
                            {{ bccomp($row['debit'], '0.000', 3) > 0 ? number_format($row['debit'], 2) : '—' }}
                        </td>
                        <td class="p-3.5 font-mono font-bold text-emerald-600 dark:text-emerald-400">
                            {{ bccomp($row['credit'], '0.000', 3) > 0 ? number_format($row['credit'], 2) : '—' }}
                        </td>
                        <td class="p-3.5 font-mono font-black text-slate-900 dark:text-white">
                            {{ number_format($row['balance_after'], 2) }} ج.م
                        </td>
                        <td class="p-3.5 text-slate-500 dark:text-slate-400">{{ $row['notes'] ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-12 text-center text-slate-400">لا توجد حركات مسجلة لهذا المورد</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- 🖨️ Signatures & Official Stamp (Print Only) -->
    <div class="print-only mt-8 pt-6 border-t-2 border-dashed border-black">
        <div class="grid grid-cols-3 gap-6 text-center text-black">
            <div>
                <div class="text-xs font-bold mb-10">توقيع المورد / مندوب التوريد</div>
                <div class="w-3/4 mx-auto border-b border-black"></div>
            </div>
            <div>
                <div class="text-xs font-bold mb-10">توقيع المحاسب / المراجع</div>
                <div class="w-3/4 mx-auto border-b border-black"></div>
            </div>
            <div>
                <div class="text-xs font-bold mb-10">اعتماد الإدارة والختم</div>
                <div class="w-3/4 mx-auto border-b border-black"></div>
            </div>
        </div>
    </div>
</div>

    <!-- Supplier Payment Voucher Modal (سند صرف سداد مديونية) -->
    @if($showPaymentModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-md p-6 space-y-4 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                <h3 class="font-bold text-emerald-600 dark:text-emerald-400 text-base flex items-center gap-2">
                    <span>💵 سند صرف / سداد مديونية مورد</span>
                </h3>
                <button wire:click="$set('showPaymentModal', false)" class="text-slate-400 hover:text-slate-700 dark:hover:text-white">✕</button>
            </div>

            <div class="bg-slate-50 dark:bg-slate-950/60 p-3 rounded-xl border border-slate-200 dark:border-slate-800 text-xs">
                <span class="text-slate-500 dark:text-slate-400">المورد:</span>
                <strong class="text-slate-900 dark:text-white text-sm block mt-0.5">{{ $supplier->name }}</strong>
            </div>

            <form wire:submit.prevent="savePayment" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">المبلغ المسدد للمورد (ج.م):</label>
                    <input type="number" step="0.001" wire:model="paymentAmount" required class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl p-3 text-sm text-slate-900 dark:text-white font-mono font-bold focus:outline-none focus:border-emerald-500">
                    @error('paymentAmount') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">طريقة الدفع:</label>
                    <select wire:model="paymentMethod" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-xs text-slate-900 dark:text-white">
                        <option value="cash">💵 نقدي (خزينة الكاشير)</option>
                        <option value="instapay">⚡ تحويل إنستاباي (InstaPay)</option>
                        <option value="e_wallet">📲 محفظة إلكترونية (فودافون/أورانج/اتصالات)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">ملاحظات / رقم الإيصال:</label>
                    <textarea wire:model="paymentNotes" rows="2" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-xs text-slate-900 dark:text-white" placeholder="مثال: سداد باقي دفعة التوريد"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <button type="button" wire:click="$set('showPaymentModal', false)" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold cursor-pointer">إلغاء</button>
                    <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-600/30 cursor-pointer">
                        💾 حفظ السند وخصم المديونية
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
