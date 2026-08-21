<template>
  <SpaLayout>
    <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Page Header -->
      <PageHeader
        :title="$t('treasury.journal_title')"
        :subtitle="$t('treasury.journal_subtitle')"
        :icon="'📖'"
      >
        <template #actions>
          <div class="flex items-center gap-2 flex-wrap">
            <!-- Date Filter Picker -->
            <div class="flex items-center gap-1.5 bg-slate-950/80 border border-slate-800 rounded-xl px-3 py-1.5 shadow-sm">
              <Calendar class="w-4 h-4 text-amber-400" />
              <input
                v-model="selectedDate"
                @change="fetchDailyJournal"
                type="date"
                class="bg-transparent border-0 text-xs font-mono text-white focus:outline-none focus:ring-0 cursor-pointer"
              >
            </div>

            <!-- Quick Add Expense in Journal Button -->
            <button
              type="button"
              @click="showExpenseModal = true"
              class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-rose-400 border border-slate-700 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer shadow-sm"
            >
              <MinusCircle class="w-4 h-4" />
              <span>تسجيل مصروف اليومية</span>
            </button>

            <!-- Shift Control Button -->
            <button
              v-if="!activeShift"
              type="button"
              @click="showOpenShiftModal = true"
              class="px-4 py-2 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-400 hover:to-emerald-500 text-slate-950 rounded-xl text-xs font-black transition-all flex items-center gap-2 shadow-lg shadow-emerald-500/20 cursor-pointer"
            >
              <Play class="w-4 h-4 fill-slate-950" />
              <span>{{ $t('treasury.open_shift') }}</span>
            </button>

            <button
              v-else
              type="button"
              @click="openCloseShiftModal"
              class="px-4 py-2 bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-400 hover:to-rose-500 text-white rounded-xl text-xs font-black transition-all flex items-center gap-2 shadow-lg shadow-rose-500/20 cursor-pointer"
            >
              <Lock class="w-4 h-4" />
              <span>{{ $t('treasury.close_shift') }} (Z-Report)</span>
            </button>
          </div>
        </template>
      </PageHeader>

      <!-- Active Shift Status Banner -->
      <div
        v-if="activeShift"
        class="p-4 rounded-2xl bg-gradient-to-r from-emerald-950/40 via-slate-900 to-slate-950 border border-emerald-500/30 shadow-lg flex flex-col md:flex-row items-start md:items-center justify-between gap-4"
      >
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center shrink-0">
            <ShieldCheck class="w-5 h-5" />
          </div>
          <div>
            <div class="flex items-center gap-2">
              <span class="text-xs font-black text-white font-mono">{{ activeShift.shift_number }}</span>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                وردية نشطة ومفتوحة
              </span>
            </div>
            <p class="text-[11px] text-slate-400 mt-0.5">
              الكاشير: <span class="font-bold text-slate-200">{{ activeShift.user_name || 'الكاشير' }}</span> — فُتحت في: <span class="font-mono text-slate-300">{{ activeShift.opened_at }}</span>
            </p>
          </div>
        </div>

        <div class="flex items-center gap-4 self-stretch md:self-auto justify-between md:justify-end border-t md:border-t-0 border-slate-800 pt-2 md:pt-0">
          <div class="text-start md:text-end">
            <span class="text-[10px] text-slate-400 block font-bold">الرصيد الافتتاحي للدرج</span>
            <span class="text-sm font-black text-amber-400 font-mono">{{ formatMoney(activeShift.opening_cash_balance) }} ج.م</span>
          </div>

          <button
            type="button"
            @click="printActiveZReport"
            class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
          >
            <Printer class="w-3.5 h-3.5 text-amber-400" />
            <span>طباعة تقرير Z</span>
          </button>
        </div>
      </div>

      <div
        v-else
        class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 shadow-md flex items-center justify-between gap-3"
      >
        <div class="flex items-center gap-3">
          <AlertCircle class="w-5 h-5 text-amber-400 shrink-0" />
          <span class="text-xs font-bold text-amber-300">
            لا توجد وردية عمل مفتوحة حالياً لهذا الفرع. يرجى فتح وردية لبدء عمليات البيع والتحصيل.
          </span>
        </div>
        <button
          type="button"
          @click="showOpenShiftModal = true"
          class="px-3.5 py-1.5 bg-amber-500 text-slate-950 font-black text-xs rounded-xl shadow-md cursor-pointer"
        >
          فتح وردية الآن
        </button>
      </div>

      <!-- Financial Metrics Grid -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
        <!-- Total Inflow -->
        <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md space-y-1">
          <span class="text-[11px] font-bold text-slate-400 block">إجمالي المقبوضات (كاش وارد)</span>
          <div class="text-xl font-black text-emerald-400 font-mono">
            +{{ formatMoney(summary.total_cash_in || 0) }} <span class="text-xs text-slate-400 font-normal">ج.م</span>
          </div>
          <span class="text-[10px] text-slate-500 block">مبيعات كاش + تحصيل عملاء</span>
        </div>

        <!-- Total Outflow -->
        <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md space-y-1">
          <span class="text-[11px] font-bold text-slate-400 block">إجمالي المدفوعات (كاش منصرف)</span>
          <div class="text-xl font-black text-rose-400 font-mono">
            -{{ formatMoney(summary.total_cash_out || 0) }} <span class="text-xs text-slate-400 font-normal">ج.م</span>
          </div>
          <span class="text-[10px] text-slate-500 block">مصروفات + سداد موردين</span>
        </div>

        <!-- Net Cash Today -->
        <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md space-y-1">
          <span class="text-[11px] font-bold text-slate-400 block">صافي النقدية اليومية</span>
          <div
            class="text-xl font-black font-mono"
            :class="(summary.net_cash_today || 0) >= 0 ? 'text-cyan-400' : 'text-amber-400'"
          >
            {{ (summary.net_cash_today || 0) > 0 ? '+' : '' }}{{ formatMoney(summary.net_cash_today || 0) }} <span class="text-xs text-slate-400 font-normal">ج.م</span>
          </div>
          <span class="text-[10px] text-slate-500 block">الفارق بين الوارد والمنصرف</span>
        </div>

        <!-- Expected Cash In Drawer -->
        <div class="p-4 rounded-2xl bg-gradient-to-br from-amber-500/10 to-slate-950 border border-amber-500/30 shadow-md space-y-1">
          <span class="text-[11px] font-bold text-amber-300 block">الرصيد المتوقع بالدرج 💵</span>
          <div class="text-xl font-black text-amber-400 font-mono">
            {{ formatMoney(summary.expected_cash_in_drawer || 0) }} <span class="text-xs text-amber-300/80 font-normal">ج.م</span>
          </div>
          <span class="text-[10px] text-slate-400 block">افتتاحي + صافي النقدية</span>
        </div>
      </div>

      <!-- Journal Tabs (Invoices vs Expenses) -->
      <div class="bg-slate-950/80 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <!-- Tabs Header -->
        <div class="flex items-center border-b border-slate-800 bg-slate-900/60 p-2">
          <button
            type="button"
            @click="activeTab = 'invoices'"
            class="flex-1 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer"
            :class="activeTab === 'invoices' ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'text-slate-400 hover:text-slate-200'"
          >
            <ShoppingCart class="w-4 h-4" />
            <span>فواتير مبيعات اليومية ({{ invoices.length }})</span>
          </button>

          <button
            type="button"
            @click="activeTab = 'expenses'"
            class="flex-1 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer"
            :class="activeTab === 'expenses' ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'text-slate-400 hover:text-slate-200'"
          >
            <Receipt class="w-4 h-4" />
            <span>مصروفات ونثريات اليومية ({{ expenses.length }})</span>
          </button>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="p-12 text-center">
          <div class="w-8 h-8 border-4 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
          <p class="text-xs text-slate-400 font-bold">{{ $t('common.loading') }}</p>
        </div>

        <!-- Tab 1: Invoices Table -->
        <div v-else-if="activeTab === 'invoices'">
          <div v-if="invoices.length > 0" class="overflow-x-auto">
            <table class="w-full text-start text-xs border-collapse">
              <thead>
                <tr class="bg-slate-900/90 text-slate-400 border-b border-slate-800">
                  <th class="py-3 px-4 text-start font-bold">#</th>
                  <th class="py-3 px-4 text-start font-bold">{{ $t('invoices.invoice_number') }}</th>
                  <th class="py-3 px-4 text-start font-bold">{{ $t('invoices.customer') }}</th>
                  <th class="py-3 px-4 text-start font-bold">الوقت</th>
                  <th class="py-3 px-4 text-center font-bold">طريقة السداد</th>
                  <th class="py-3 px-4 text-end font-bold">{{ $t('common.total') }}</th>
                  <th class="py-3 px-4 text-end font-bold">{{ $t('invoices.paid') }}</th>
                  <th class="py-3 px-4 text-center font-bold">{{ $t('common.status') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/60 font-sans">
                <tr v-for="(inv, idx) in invoices" :key="inv.id" class="hover:bg-slate-900/50 transition-colors">
                  <td class="py-3.5 px-4 font-mono text-slate-500">{{ idx + 1 }}</td>
                  <td class="py-3.5 px-4 font-mono font-bold text-amber-400">{{ inv.invoice_number }}</td>
                  <td class="py-3.5 px-4 font-bold text-white font-tajawal">{{ inv.customer_name }}</td>
                  <td class="py-3.5 px-4 font-mono text-slate-400">{{ inv.time || '—' }}</td>
                  <td class="py-3.5 px-4 text-center">
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold font-tajawal bg-slate-800 border border-slate-700 text-slate-300">
                      {{ formatPaymentMethod(inv.payment_method) }}
                    </span>
                  </td>
                  <td class="py-3.5 px-4 text-end font-mono font-bold text-slate-200">
                    {{ formatMoney(inv.net_total) }} ج.م
                  </td>
                  <td class="py-3.5 px-4 text-end font-mono font-black text-emerald-400">
                    {{ formatMoney(inv.paid_amount) }} ج.م
                  </td>
                  <td class="py-3.5 px-4 text-center">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-tajawal border bg-emerald-500/10 border-emerald-500/30 text-emerald-400">
                      معتمدة
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <EmptyState
            v-else
            :title="'لا توجد فواتير مبيعات مسجلة في هذا التاريخ'"
            :description="'لم يتم إصدار أي فواتير مبيعات في اليوم المحدد.'"
            :icon="'🛒'"
          />
        </div>

        <!-- Tab 2: Expenses Table -->
        <div v-else-if="activeTab === 'expenses'">
          <div v-if="expenses.length > 0" class="overflow-x-auto">
            <table class="w-full text-start text-xs border-collapse">
              <thead>
                <tr class="bg-slate-900/90 text-slate-400 border-b border-slate-800">
                  <th class="py-3 px-4 text-start font-bold">#</th>
                  <th class="py-3 px-4 text-start font-bold">{{ $t('invoices.invoice_number') }}</th>
                  <th class="py-3 px-4 text-start font-bold">{{ $t('expenses.expense_item') }}</th>
                  <th class="py-3 px-4 text-start font-bold">مركز التكلفة</th>
                  <th class="py-3 px-4 text-center font-bold">طريقة الصرف</th>
                  <th class="py-3 px-4 text-end font-bold">{{ $t('common.amount') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/60 font-sans">
                <tr v-for="(e, idx) in expenses" :key="e.id" class="hover:bg-slate-900/50 transition-colors">
                  <td class="py-3.5 px-4 font-mono text-slate-500">{{ idx + 1 }}</td>
                  <td class="py-3.5 px-4 font-mono font-bold text-amber-400">{{ e.expense_number }}</td>
                  <td class="py-3.5 px-4 font-bold text-white font-tajawal">{{ e.title }}</td>
                  <td class="py-3.5 px-4 font-tajawal text-slate-300">{{ e.cost_center_label || e.cost_center }}</td>
                  <td class="py-3.5 px-4 text-center">
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold font-tajawal bg-slate-800 border border-slate-700 text-slate-300">
                      {{ formatPaymentMethod(e.payment_method) }}
                    </span>
                  </td>
                  <td class="py-3.5 px-4 text-end font-mono font-black text-rose-400">
                    {{ formatMoney(e.amount) }} ج.م
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <EmptyState
            v-else
            :title="'لا توجد مصروفات مسجلة في هذا التاريخ'"
            :description="'لم يتم تسجيل أي نثريات أو مصروفات في اليوم المحدد.'"
            :icon="'💸'"
          />
        </div>
      </div>

      <!-- Open Shift Modal -->
      <AppModal
        :show="showOpenShiftModal"
        :title="$t('treasury.open_shift')"
        @close="showOpenShiftModal = false"
      >
        <form @submit.prevent="submitOpenShift" class="space-y-4 font-tajawal">
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">
              الرصيد الافتتاحي للدرج (كاش بداية الوردية) <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="openShiftForm.opening_cash_balance"
              type="number"
              step="0.001"
              required
              autofocus
              class="w-full h-11 px-3 bg-slate-900 border border-slate-700 rounded-xl text-lg font-bold text-amber-400 font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
              placeholder="0.00"
            >
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">
              ملاحظات الفتح
            </label>
            <input
              v-model="openShiftForm.notes"
              type="text"
              class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
              placeholder="مثال: استلام الدرج من الكاشير الصباحي..."
            >
          </div>

          <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
            <button
              type="button"
              @click="showOpenShiftModal = false"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold cursor-pointer"
            >
              {{ $t('common.cancel') }}
            </button>

            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2 bg-emerald-500 hover:bg-emerald-400 text-slate-950 rounded-xl text-xs font-black shadow-lg shadow-emerald-500/20 disabled:opacity-50 cursor-pointer flex items-center gap-2"
            >
              <span v-if="isSubmitting" class="w-3.5 h-3.5 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
              <span>تأكيد فتح الوردية</span>
            </button>
          </div>
        </form>
      </AppModal>

      <!-- Close Shift Modal (Z-Report) -->
      <AppModal
        :show="showCloseShiftModal"
        :title="`${$t('treasury.close_shift')} - ${activeShift?.shift_number}`"
        @close="showCloseShiftModal = false"
      >
        <form @submit.prevent="submitCloseShift" class="space-y-4 font-tajawal">
          <div class="p-3.5 bg-slate-900/90 border border-slate-800 rounded-2xl space-y-2">
            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-400 font-bold">الرصيد الافتتاحي للوردية:</span>
              <span class="font-mono text-slate-200 font-bold">{{ formatMoney(activeShift?.opening_cash_balance || 0) }} ج.م</span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-400 font-bold">الرصيد الدفتري المتوقع بالدرج:</span>
              <span class="font-mono text-amber-400 font-black text-sm">{{ formatMoney(summary.expected_cash_in_drawer || 0) }} ج.م</span>
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">
              النقدية الفعلية بعد العدّ والجرد (الدرج الفعلي) <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="closeShiftForm.actual_cash_balance"
              type="number"
              step="0.001"
              required
              autofocus
              class="w-full h-11 px-3 bg-slate-900 border border-slate-700 rounded-xl text-lg font-bold text-emerald-400 font-mono focus:ring-2 focus:ring-emerald-500 focus:outline-none"
              placeholder="0.00"
            >
          </div>

          <!-- Live Discrepancy Preview -->
          <div v-if="closeShiftForm.actual_cash_balance !== ''" class="p-3 rounded-xl border text-xs font-bold flex items-center justify-between" :class="getDiffClass()">
            <span>فارق الدرج (العجز / الزيادة):</span>
            <span class="font-mono text-sm">{{ getDiffText() }}</span>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">
              ملاحظات التقفيل
            </label>
            <input
              v-model="closeShiftForm.notes"
              type="text"
              class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
              placeholder="أي ملاحظات حول الجرد أو التسليم..."
            >
          </div>

          <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
            <button
              type="button"
              @click="showCloseShiftModal = false"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold cursor-pointer"
            >
              {{ $t('common.cancel') }}
            </button>

            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2 bg-rose-500 hover:bg-rose-400 text-white rounded-xl text-xs font-black shadow-lg shadow-rose-500/20 disabled:opacity-50 cursor-pointer flex items-center gap-2"
            >
              <span v-if="isSubmitting" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <span>تأكيد الإغلاق وإصدار Z-Report</span>
            </button>
          </div>
        </form>
      </AppModal>

      <!-- Quick Journal Expense Modal -->
      <AppModal
        :show="showExpenseModal"
        :title="'تسجيل مصروف في اليومية'"
        @close="showExpenseModal = false"
      >
        <form @submit.prevent="submitExpense" class="space-y-4 font-tajawal">
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">
              {{ $t('expenses.expense_item') }} <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="expenseForm.title"
              type="text"
              required
              class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
              placeholder="مثال: بوفيه، إكراميات، شنط وتغليف..."
            >
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">
                {{ $t('common.amount') }} <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="expenseForm.amount"
                type="number"
                step="0.001"
                required
                class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-rose-400 font-mono focus:ring-2 focus:ring-rose-500 focus:outline-none"
                placeholder="0.00"
              >
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">
                مركز التكلفة <span class="text-rose-500">*</span>
              </label>
              <select
                v-model="expenseForm.cost_center"
                required
                class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
              >
                <option value="operational">مصاريف تشغيلية ونثريات</option>
                <option value="hospitality">ضيافة وبوفيه ونظافة</option>
                <option value="packaging">شنط وكراتين وتغليف</option>
                <option value="utilities">مرافق وكهرباء ومياه</option>
                <option value="salaries">رواتب وإكراميات</option>
                <option value="maintenance">صيانة دورية</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">
              طريقة الدفع <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="expenseForm.payment_method"
              required
              class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
            >
              <option value="cash">💵 نقداً من درج الكاشير</option>
              <option value="instapay">⚡ إنستاباي</option>
              <option value="e_wallet">📱 محفظة إلكترونية</option>
              <option value="visa">💳 فيزا وبطاقة بنكية</option>
            </select>
          </div>

          <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
            <button
              type="button"
              @click="showExpenseModal = false"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold cursor-pointer"
            >
              {{ $t('common.cancel') }}
            </button>

            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-xl text-xs font-black shadow-lg shadow-amber-500/20 disabled:opacity-50 cursor-pointer flex items-center gap-2"
            >
              <span v-if="isSubmitting" class="w-3.5 h-3.5 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
              <span>تسجيل المصروف</span>
            </button>
          </div>
        </form>
      </AppModal>
    </div>
  </SpaLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import SpaLayout from '../../Layouts/SpaLayout.vue';
import PageHeader from '../../Components/Common/PageHeader.vue';
import EmptyState from '../../Components/Common/EmptyState.vue';
import AppModal from '../../Components/Common/AppModal.vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import {
    Calendar,
    MinusCircle,
    Play,
    Lock,
    ShieldCheck,
    AlertCircle,
    Printer,
    ShoppingCart,
    Receipt
} from 'lucide-vue-next';

const selectedDate = ref(new Date().toISOString().split('T')[0]);
const activeTab = ref('invoices');
const isLoading = ref(false);
const isSubmitting = ref(false);

const activeShift = ref(null);
const summary = ref({
    total_sales: 0,
    cash_sales: 0,
    credit_sales: 0,
    customer_payments: 0,
    total_cash_in: 0,
    supplier_payments: 0,
    total_expenses: 0,
    cash_expenses: 0,
    total_cash_out: 0,
    net_cash_today: 0,
    opening_cash_balance: 0,
    expected_cash_in_drawer: 0,
});
const invoices = ref([]);
const expenses = ref([]);

// Modals State
const showOpenShiftModal = ref(false);
const openShiftForm = reactive({
    opening_cash_balance: '0.000',
    notes: '',
});

const showCloseShiftModal = ref(false);
const closeShiftForm = reactive({
    actual_cash_balance: '',
    notes: '',
});

const showExpenseModal = ref(false);
const expenseForm = reactive({
    title: '',
    amount: '',
    cost_center: 'operational',
    payment_method: 'cash',
    notes: '',
});

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatPaymentMethod = (method) => {
    const map = {
        cash: '💵 نقداً',
        instapay: '⚡ إنستاباي',
        e_wallet: '📱 محفظة',
        visa: '💳 فيزا',
        credit: '📝 آجل',
        partial: '⚖️ جزئي',
    };
    return map[method] || method;
};

const fetchDailyJournal = async () => {
    isLoading.value = true;
    try {
        const response = await api.get('/daily-journal', {
            params: {
                date: selectedDate.value,
            },
        });
        const data = response.data?.data;
        if (data) {
            activeShift.value = data.active_shift;
            summary.value = data.summary || {};
            invoices.value = data.invoices || [];
            expenses.value = data.expenses || [];
        }
    } catch (error) {
        console.error('Failed to load daily journal:', error);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchDailyJournal();
});

const submitOpenShift = async () => {
    isSubmitting.value = true;
    try {
        await api.post('/shifts/open', openShiftForm);
        Swal.fire({
            icon: 'success',
            title: 'تم فتح الوردية',
            text: 'تم فتح وردية العمل بنجاح',
            timer: 1500,
            showConfirmButton: false,
        });
        showOpenShiftModal.value = false;
        await fetchDailyJournal();
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: error.userMessage || 'تعذر فتح الوردية',
        });
    } finally {
        isSubmitting.value = false;
    }
};

const openCloseShiftModal = () => {
    closeShiftForm.actual_cash_balance = (summary.value?.expected_cash_in_drawer || 0).toString();
    closeShiftForm.notes = '';
    showCloseShiftModal.value = true;
};

const getDiffText = () => {
    const actual = parseFloat(closeShiftForm.actual_cash_balance) || 0;
    const expected = parseFloat(summary.value?.expected_cash_in_drawer) || 0;
    const diff = actual - expected;
    if (Math.abs(diff) < 0.001) return 'مطابقة تماماً بدون عجز أو زيادة ✓';
    if (diff > 0) return `زيادة بالدرج: +${diff.toFixed(2)} ج.م`;
    return `عجز بالدرج: -${Math.abs(diff).toFixed(2)} ج.م`;
};

const getDiffClass = () => {
    const actual = parseFloat(closeShiftForm.actual_cash_balance) || 0;
    const expected = parseFloat(summary.value?.expected_cash_in_drawer) || 0;
    const diff = actual - expected;
    if (Math.abs(diff) < 0.001) return 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400';
    if (diff > 0) return 'bg-amber-500/10 border-amber-500/30 text-amber-400';
    return 'bg-rose-500/10 border-rose-500/30 text-rose-400';
};

const submitCloseShift = async () => {
    if (!activeShift.value) return;
    isSubmitting.value = true;
    try {
        const response = await api.post('/shifts/close', {
            shift_id: activeShift.value.id,
            actual_cash_balance: closeShiftForm.actual_cash_balance,
            notes: closeShiftForm.notes,
        });
        Swal.fire({
            icon: 'success',
            title: 'تم إغلاق الوردية',
            text: response.data?.message || 'تم إغلاق وتقفيل الوردية وإصدار Z-Report بنجاح',
        });
        showCloseShiftModal.value = false;
        await fetchDailyJournal();
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: error.userMessage || 'تعذر إغلاق الوردية',
        });
    } finally {
        isSubmitting.value = false;
    }
};

const submitExpense = async () => {
    isSubmitting.value = true;
    try {
        await api.post('/expenses', {
            ...expenseForm,
            category: 'مصاريف يومية',
            expense_date: selectedDate.value,
        });
        Swal.fire({
            icon: 'success',
            title: 'تم التسجيل',
            text: 'تم تسجيل المصروف في اليومية بنجاح',
            timer: 1500,
            showConfirmButton: false,
        });
        showExpenseModal.value = false;
        expenseForm.title = '';
        expenseForm.amount = '';
        await fetchDailyJournal();
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: error.userMessage || 'تعذر تسجيل المصروف',
        });
    } finally {
        isSubmitting.value = false;
    }
};

const printActiveZReport = async () => {
    if (!activeShift.value) return;
    try {
        const response = await api.get(`/shifts/${activeShift.value.id}/z-report`);
        const report = response.data?.report;
        if (report) {
            window.print();
        }
    } catch (error) {
        console.error('Failed to get Z-report:', error);
    }
};
</script>
