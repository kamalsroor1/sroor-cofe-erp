<template>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 font-tajawal no-print">
    <!-- Payments Collections Log -->
    <div class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm space-y-4">
      <div class="flex items-center gap-2 border-b border-slate-100 dark:border-slate-800/80 pb-3">
        <span class="text-base">💳</span>
        <h3 class="text-xs font-black text-slate-900 dark:text-white">{{ $t('invoices.payments_history_section') }}</h3>
      </div>

      <div v-if="payments.length > 0" class="space-y-2">
        <div
          v-for="p in payments"
          :key="p.id"
          class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3 text-xs"
        >
          <div>
            <div class="font-bold text-slate-900 dark:text-white">{{ p.payment_method }}</div>
            <div class="text-[10px] text-slate-400 font-mono mt-0.5">📅 {{ p.payment_date }} • 👤 {{ p.user_name }}</div>
          </div>
          <div class="font-mono font-black text-emerald-500 text-sm">
            +{{ formatMoney(p.amount) }} {{ $t('common.currency') }}
          </div>
        </div>
      </div>
      <div v-else class="py-6 text-center text-xs text-slate-400 font-bold">
        {{ $t('invoices.no_payments_recorded') }}
      </div>
    </div>

    <!-- Notes & Remarks -->
    <div class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm space-y-4">
      <div class="flex items-center gap-2 border-b border-slate-100 dark:border-slate-800/80 pb-3">
        <span class="text-base">📝</span>
        <h3 class="text-xs font-black text-slate-900 dark:text-white">{{ $t('invoices.notes') }}</h3>
      </div>

      <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 text-xs text-slate-700 dark:text-slate-300 min-h-[90px] whitespace-pre-wrap">
        {{ invoice?.notes || $t('common.no_notes_available') }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { useFormatters } from '../../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  invoice: { type: Object, default: null },
  payments: { type: Array, default: () => [] },
});
</script>
