<template>
  <aside class="flex flex-col justify-between p-4 bg-white dark:bg-slate-900 border-s border-slate-200 dark:border-slate-800 shadow-lg overflow-y-auto custom-scrollbar space-y-4 font-tajawal select-none">
    
    <!-- 1. Financial Breakdown Card -->
    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-3">
      
      <div class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-400">
        <span>{{ $t('pos.subtotal_items', { count: cartCount }) }}</span>
        <span class="font-mono font-black text-sm text-slate-900 dark:text-white">{{ formatMoney(subtotal) }} {{ $t('common.currency') }}</span>
      </div>

      <!-- Quick Discount Row -->
      <div class="pt-2 border-t border-slate-200 dark:border-slate-800 space-y-1.5">
        <div class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-400">
          <span>{{ $t('pos.discount_label') }}</span>
          <span class="font-mono font-black text-sm text-rose-500">- {{ formatMoney(discountAmount) }} {{ $t('common.currency') }}</span>
        </div>
        
        <div class="flex flex-wrap gap-1">
          <button
            type="button"
            @click="$emit('apply-discount', { value: 0, type: 'percentage' })"
            class="min-h-[32px] px-2.5 py-1 rounded-lg text-[11px] font-bold border transition cursor-pointer"
            :class="parseFloat(discountValue) === 0 ? 'bg-theme-primary text-slate-950 font-black border-theme-primary' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-800'"
          >
            0%
          </button>
          <button
            v-for="r in [5, 10, 15, 20]"
            :key="r"
            type="button"
            @click="$emit('apply-discount', { value: r, type: 'percentage' })"
            class="min-h-[32px] px-2.5 py-1 rounded-lg text-[11px] font-mono font-bold border transition cursor-pointer"
            :class="(discountType === 'percentage' && parseFloat(discountValue) === r) ? 'bg-theme-primary text-slate-950 font-black border-theme-primary' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-800'"
          >
            {{ r }}%
          </button>
        </div>
      </div>

      <!-- Customer Extra Expenses (Shipping / Services) -->
      <div v-if="customerExpensesTotal > 0" class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-400">
        <span>{{ $t('pos.extra_expenses_shipping') }}</span>
        <span class="font-mono font-black text-sm text-emerald-500">+ {{ formatMoney(customerExpensesTotal) }} {{ $t('common.currency') }}</span>
      </div>

      <!-- GIANT NET TOTAL DUE -->
      <div class="pt-3 border-t-2 border-slate-200 dark:border-slate-800 flex items-center justify-between">
        <span class="text-sm font-black text-slate-900 dark:text-white">{{ $t('pos.giant_net_total') }}</span>
        <span class="text-3xl font-black font-mono text-emerald-600 dark:text-emerald-400 tracking-tight">
          {{ formatMoney(netTotal) }} <span class="text-sm font-bold text-slate-400 font-tajawal">{{ $t('common.currency') }}</span>
        </span>
      </div>

    </div>

    <!-- 2. Payment Type (كاش / آجل / جزئي) -->
    <div class="space-y-2">
      <label class="block text-xs font-black text-slate-700 dark:text-slate-300">{{ $t('pos.invoice_and_payment_type') }}</label>
      <div class="grid grid-cols-3 gap-2">
        <button
          type="button"
          @click="$emit('update:paymentType', 'cash')"
          class="min-h-[44px] p-2.5 rounded-xl border text-center transition active:scale-95 cursor-pointer"
          :class="paymentType === 'cash' ? 'bg-emerald-500/10 border-emerald-500 text-emerald-600 dark:text-emerald-400 font-black shadow-xs' : 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
        >
          <div class="text-xs">{{ $t('pos.instant_cash') }}</div>
        </button>
        <button
          type="button"
          @click="$emit('update:paymentType', 'credit')"
          class="min-h-[44px] p-2.5 rounded-xl border text-center transition active:scale-95 cursor-pointer"
          :class="paymentType === 'credit' ? 'bg-rose-500/10 border-rose-500 text-rose-600 dark:text-rose-400 font-black shadow-xs' : 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
        >
          <div class="text-xs">{{ $t('pos.credit_on_account') }}</div>
        </button>
        <button
          type="button"
          @click="$emit('update:paymentType', 'partial')"
          class="min-h-[44px] p-2.5 rounded-xl border text-center transition active:scale-95 cursor-pointer"
          :class="paymentType === 'partial' ? 'bg-amber-500/10 border-amber-500 text-amber-600 dark:text-amber-400 font-black shadow-xs' : 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
        >
          <div class="text-xs">{{ $t('pos.partial_pay') }}</div>
        </button>
      </div>
    </div>

    <!-- 3. Payment Method & Fast Cash Calculator -->
    <div v-if="paymentType !== 'credit'" class="space-y-3 p-3 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
      
      <!-- Payment Method Chips -->
      <div class="flex items-center gap-2">
        <button
          v-for="m in [
            { key: 'cash', label: '💵 كاش نقدي' },
            { key: 'instapay', label: '⚡ إنستاباي' },
            { key: 'smart_wallet', label: '📱 محفظة ذكية' }
          ]"
          :key="m.key"
          type="button"
          @click="$emit('update:paymentMethod', m.key)"
          class="min-h-[38px] flex-1 py-1.5 rounded-lg text-xs font-bold border transition text-center cursor-pointer"
          :class="paymentMethod === m.key ? 'bg-theme-primary text-slate-950 font-black border-theme-primary' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800'"
        >
          {{ m.label }}
        </button>
      </div>

      <!-- Quick Cash Amount Buttons -->
      <div class="flex flex-wrap gap-1">
        <button
          type="button"
          @click="$emit('update:cashReceived', netTotal)"
          class="min-h-[34px] px-2.5 py-1 rounded-lg bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-black border border-emerald-500/30 cursor-pointer"
        >
          🎯 {{ $t('pos.quick_cash_exact') }}
        </button>
        <button
          v-for="amt in [50, 100, 200, 500, 1000, 2000]"
          :key="amt"
          type="button"
          @click="$emit('update:cashReceived', amt)"
          class="min-h-[34px] px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 text-xs font-mono font-bold cursor-pointer"
        >
          {{ amt }}
        </button>
      </div>

      <!-- Cash Received & Change Due -->
      <div class="grid grid-cols-2 gap-2 pt-1">
        <div>
          <label class="block text-[11px] font-bold text-slate-500 mb-1">{{ $t('pos.received_from_cust') }}</label>
          <input
            type="number"
            :value="cashReceived"
            @input="$emit('update:cashReceived', $event.target.value)"
            step="0.001"
            min="0"
            class="w-full h-10 px-3 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-sm font-mono font-black text-slate-950 dark:text-white focus:outline-none focus:ring-2 focus:ring-theme-primary"
          />
        </div>
        <div class="p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex flex-col justify-center text-center">
          <span class="text-[10px] text-slate-400 font-bold">{{ $t('pos.change_to_cust') }}</span>
          <span
            class="text-base font-black font-mono"
            :class="changeDue > 0 ? 'text-emerald-500' : (changeDue < 0 ? 'text-rose-500' : 'text-slate-400')"
          >
            {{ formatMoney(Math.max(0, changeDue)) }} {{ $t('common.currency') }}
          </span>
        </div>
      </div>

    </div>

    <!-- 4. Execution Buttons -->
    <div class="space-y-2 pt-2">
      <button
        type="button"
        @click="$emit('submit', false)"
        :disabled="cartEmpty || isSubmitting"
        class="w-full h-14 bg-theme-primary hover:opacity-95 text-slate-950 rounded-2xl font-black text-base transition-all duration-200 active:scale-[0.98] shadow-lg flex items-center justify-center gap-3 cursor-pointer disabled:opacity-30"
      >
        <span v-if="isSubmitting" class="w-5 h-5 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
        <span v-else class="text-xl">✅</span>
        <span>{{ $t('pos.confirm_and_approve_f9') }}</span>
      </button>

      <button
        type="button"
        @click="$emit('submit', true)"
        :disabled="cartEmpty || isSubmitting"
        class="min-h-[44px] w-full h-11 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 rounded-xl font-bold text-xs transition border border-slate-200 dark:border-slate-700 flex items-center justify-center gap-2 cursor-pointer disabled:opacity-30"
      >
        <Printer class="w-4 h-4" />
        <span>{{ $t('pos.save_and_print_btn') }}</span>
      </button>
    </div>

  </aside>
</template>

<script setup>
import { Printer } from 'lucide-vue-next';
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
