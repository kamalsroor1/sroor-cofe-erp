<template>
  <div class="p-5 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-lg grid grid-cols-1 sm:grid-cols-2 gap-6">
    <div class="space-y-3">
      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
          {{ $t('purchases.invoice_notes') }}
        </label>
        <textarea
          :value="notes"
          @input="$emit('update:notes', $event.target.value)"
          rows="3"
          class="w-full p-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
          :placeholder="$t('purchases.invoice_notes_placeholder')"
        ></textarea>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
            {{ $t('purchases.paid_to_supplier') }}
          </label>
          <input
            :value="paidAmount"
            @input="$emit('update:paidAmount', $event.target.value)"
            type="number"
            step="0.001"
            min="0"
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-emerald-500 dark:text-emerald-400 font-mono font-bold focus:ring-2 focus:ring-emerald-500 focus:outline-none"
            placeholder="0.00"
          >
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
            {{ $t('purchases.discount_earned') }}
          </label>
          <input
            :value="discountAmount"
            @input="$emit('update:discountAmount', $event.target.value)"
            type="number"
            step="0.001"
            min="0"
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-rose-500 dark:text-rose-400 font-mono font-bold focus:ring-2 focus:ring-rose-500 focus:outline-none"
            placeholder="0.00"
          >
        </div>
      </div>
    </div>

    <!-- Total Calculation Ledger -->
    <div class="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-2.5 font-mono text-xs self-center">
      <div class="flex justify-between text-slate-600 dark:text-slate-300 font-sans font-tajawal">
        <span>{{ $t('purchases.items_total_value') }}</span>
        <span class="font-mono font-bold">{{ formatMoney(subtotal) }} {{ $t('common.currency') }}</span>
      </div>
      <div v-if="discount > 0" class="flex justify-between text-rose-500 font-sans font-tajawal">
        <span>{{ $t('purchases.discount_earned') }}</span>
        <span class="font-mono font-bold">-{{ formatMoney(discount) }} {{ $t('common.currency') }}</span>
      </div>
      <div class="flex justify-between text-base font-black text-slate-900 dark:text-white pt-2 border-t border-slate-200 dark:border-slate-800 font-sans font-tajawal">
        <span>{{ $t('invoices.net_invoice') }}</span>
        <span class="font-mono text-emerald-500">{{ formatMoney(netTotal) }} {{ $t('common.currency') }}</span>
      </div>
      <div class="flex justify-between text-xs font-bold font-sans font-tajawal" :class="remaining > 0 ? 'text-rose-500' : 'text-slate-400'">
        <span>{{ $t('purchases.remaining_on_company') }}</span>
        <span class="font-mono font-bold">{{ formatMoney(remaining) }} {{ $t('common.currency') }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  notes: { type: String, default: '' },
  paidAmount: { type: [String, Number], default: '0.000' },
  discountAmount: { type: [String, Number], default: '0.000' },
  subtotal: { type: Number, default: 0 },
  discount: { type: Number, default: 0 },
  netTotal: { type: Number, default: 0 },
  remaining: { type: Number, default: 0 },
});

defineEmits(['update:notes', 'update:paidAmount', 'update:discountAmount']);
</script>
