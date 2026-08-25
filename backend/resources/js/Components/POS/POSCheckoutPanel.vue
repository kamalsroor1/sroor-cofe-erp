<template>
  <aside class="flex flex-col justify-between p-2.5 bg-white dark:bg-slate-900 border-s border-slate-200 dark:border-slate-800 shadow-md space-y-2 font-tajawal select-none">
    
    <!-- 1. Financial Breakdown Card -->
    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-1.5">
      
      <div class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-400">
        <span>{{ $t('pos.subtotal_items', { count: cartCount }) }}</span>
        <span class="font-mono font-black text-xs text-slate-900 dark:text-white">{{ formatMoney(subtotal) }} {{ $t('common.currency') }}</span>
      </div>

      <!-- Quick Discount Row -->
      <div class="pt-1 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2">
        <div class="flex items-center gap-1 text-[11px] font-bold text-slate-600 dark:text-slate-400">
          <span>{{ $t('pos.discount_label') }}</span>
          <span class="font-mono font-black text-rose-500">-{{ formatMoney(discountAmount) }}</span>
        </div>
        
        <div class="flex items-center gap-1">
          <button
            type="button"
            @click="$emit('apply-discount', { value: 0, type: 'percentage' })"
            class="px-1.5 py-0.5 rounded text-[10px] font-bold border transition cursor-pointer"
            :class="parseFloat(discountValue) === 0 ? 'bg-theme-primary text-slate-950 font-black border-theme-primary' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-800'"
          >
            0%
          </button>
          <button
            v-for="r in [5, 10, 15, 20]"
            :key="r"
            type="button"
            @click="$emit('apply-discount', { value: r, type: 'percentage' })"
            class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold border transition cursor-pointer"
            :class="(discountType === 'percentage' && parseFloat(discountValue) === r) ? 'bg-theme-primary text-slate-950 font-black border-theme-primary' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-800'"
          >
            {{ r }}%
          </button>
        </div>
      </div>

      <!-- Customer Extra Expenses (Shipping / Services) -->
      <div v-if="customerExpensesTotal > 0" class="flex items-center justify-between text-[11px] font-bold text-slate-600 dark:text-slate-400">
        <span>{{ $t('pos.extra_expenses_shipping') }}</span>
        <span class="font-mono font-black text-emerald-500">+ {{ formatMoney(customerExpensesTotal) }}</span>
      </div>

      <!-- GIANT NET TOTAL DUE -->
      <div class="pt-1.5 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
        <span class="text-xs font-black text-slate-900 dark:text-white">{{ $t('pos.giant_net_total') }}</span>
        <span class="text-xl font-black font-mono text-emerald-600 dark:text-emerald-400 tracking-tight">
          {{ formatMoney(netTotal) }} <span class="text-xs font-bold text-slate-400 font-tajawal">{{ $t('common.currency') }}</span>
        </span>
      </div>

    </div>

    <!-- 2. Payment Type (كاش / آجل / جزئي) -->
    <div class="grid grid-cols-3 gap-1.5">
      <button
        type="button"
        @click="$emit('update:paymentType', 'cash')"
        class="py-1.5 px-2 rounded-xl border text-center transition active:scale-95 cursor-pointer text-xs font-bold"
        :class="paymentType === 'cash' ? 'bg-emerald-500/10 border-emerald-500 text-emerald-600 dark:text-emerald-400 font-black shadow-2xs' : 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
      >
        {{ $t('pos.instant_cash') }}
      </button>
      <button
        type="button"
        @click="$emit('update:paymentType', 'credit')"
        class="py-1.5 px-2 rounded-xl border text-center transition active:scale-95 cursor-pointer text-xs font-bold"
        :class="paymentType === 'credit' ? 'bg-rose-500/10 border-rose-500 text-rose-600 dark:text-rose-400 font-black shadow-2xs' : 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
      >
        {{ $t('pos.credit_on_account') }}
      </button>
      <button
        type="button"
        @click="$emit('update:paymentType', 'partial')"
        class="py-1.5 px-2 rounded-xl border text-center transition active:scale-95 cursor-pointer text-xs font-bold"
        :class="paymentType === 'partial' ? 'bg-amber-500/10 border-amber-500 text-amber-600 dark:text-amber-400 font-black shadow-2xs' : 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
      >
        {{ $t('pos.partial_pay') }}
      </button>
    </div>

    <!-- 3. Payment Method & Fast Cash Calculator -->
    <div v-if="paymentType !== 'credit'" class="space-y-1.5 p-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
      
      <!-- Payment Method Chips -->
      <div class="flex items-center gap-1.5">
        <button
          v-for="m in [
            { key: 'cash', label: $t('pos.payment_cash') },
            { key: 'instapay', label: $t('pos.instapay') },
            { key: 'smart_wallet', label: $t('pos.smart_wallet') }
          ]"
          :key="m.key"
          type="button"
          @click="$emit('update:paymentMethod', m.key)"
          class="flex-1 py-1 rounded-lg text-[11px] font-bold border transition text-center cursor-pointer"
          :class="paymentMethod === m.key ? 'bg-theme-primary text-slate-950 font-black border-theme-primary' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800'"
        >
          {{ m.label }}
        </button>
      </div>

      <!-- Quick Cash Calculator Row -->
      <div class="flex items-center gap-2 pt-0.5">
        <div class="flex-1">
          <input
            type="number"
            :value="cashReceived"
            @input="$emit('update:cashReceived', $event.target.value)"
            step="1"
            min="0"
            placeholder="المدفوع نقداً..."
            class="w-full h-8 px-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-mono font-black text-slate-950 dark:text-white focus:outline-none focus:ring-1 focus:ring-theme-primary"
          />
        </div>
        <div class="px-2 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-center shrink-0">
          <span class="text-[10px] text-slate-400 font-bold me-1">{{ $t('pos.change_to_cust') }}</span>
          <span
            class="text-xs font-black font-mono"
            :class="changeDue > 0 ? 'text-emerald-500' : (changeDue < 0 ? 'text-rose-500' : 'text-slate-400')"
          >
            {{ formatMoney(Math.max(0, changeDue)) }}
          </span>
        </div>
      </div>

    </div>

    <!-- 4. Execution Buttons (Always Pinned & Accessible) -->
    <div class="space-y-1.5 pt-1">
      <button
        type="button"
        @click="$emit('submit', false)"
        :disabled="cartEmpty || isSubmitting"
        class="w-full h-11 bg-theme-primary hover:opacity-95 text-slate-950 rounded-xl font-black text-sm transition-all duration-150 active:scale-[0.98] shadow-md flex items-center justify-center gap-2 cursor-pointer disabled:opacity-30"
      >
        <span v-if="isSubmitting" class="w-4 h-4 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
        <CheckCircle2 v-else class="w-4 h-4 text-slate-950 stroke-[2.5]" />
        <span>{{ $t('pos.confirm_and_approve_f9') }}</span>
      </button>

      <button
        type="button"
        @click="$emit('submit', true)"
        :disabled="cartEmpty || isSubmitting"
        class="w-full h-8 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg font-bold text-[11px] transition border border-slate-200 dark:border-slate-700 flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-30"
      >
        <Printer class="w-3.5 h-3.5" />
        <span>{{ $t('pos.save_and_print_btn') }}</span>
      </button>
    </div>

  </aside>
</template>

<script setup>
import { Printer, CheckCircle2 } from 'lucide-vue-next';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  cartCount: { type: Number, default: 0 },
  subtotal: { type: Number, default: 0 },
  discountAmount: { type: Number, default: 0 },
  discountValue: { type: [Number, String], default: 0 },
  discountType: { type: String, default: 'percentage' },
  customerExpensesTotal: { type: Number, default: 0 },
  netTotal: { type: Number, default: 0 },
  paymentType: { type: String, default: 'cash' },
  paymentMethod: { type: String, default: 'cash' },
  cashReceived: { type: [Number, String], default: 0 },
  changeDue: { type: Number, default: 0 },
  cartEmpty: { type: Boolean, default: true },
  isSubmitting: { type: Boolean, default: false },
});

defineEmits([
  'apply-discount',
  'update:paymentType',
  'update:paymentMethod',
  'update:cashReceived',
  'submit',
]);
</script>
