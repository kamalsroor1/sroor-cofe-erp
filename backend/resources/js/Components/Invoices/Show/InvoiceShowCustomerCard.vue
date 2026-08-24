<template>
  <div class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm space-y-4 font-tajawal no-print">
    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800/80 pb-3">
      <div class="flex items-center gap-2">
        <span class="text-base">👤</span>
        <h3 class="text-xs font-black text-slate-900 dark:text-white">{{ $t('invoices.customer_info_section') }}</h3>
      </div>
      <router-link
        v-if="customer?.id"
        :to="`/customers/${customer.id}/statement`"
        class="text-[11px] font-bold text-theme-primary hover:underline flex items-center gap-1"
      >
        <span>{{ $t('customers.view_statement') }}</span>
        <span>←</span>
      </router-link>
    </div>

    <div class="space-y-3 text-xs">
      <div class="flex items-center justify-between">
        <span class="text-slate-500 dark:text-slate-400 font-bold">{{ $t('invoices.customer') }}:</span>
        <span class="font-black text-slate-900 dark:text-white">{{ customerName }}</span>
      </div>

      <div v-if="customerPhone" class="flex items-center justify-between">
        <span class="text-slate-500 dark:text-slate-400 font-bold">{{ $t('common.phone') }}:</span>
        <span class="font-mono font-bold text-slate-800 dark:text-slate-200" dir="ltr">{{ customerPhone }}</span>
      </div>

      <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
        <span class="text-slate-500 dark:text-slate-400 font-bold">{{ $t('invoices.current_balance_after') }}</span>
        <span
          class="font-mono font-black text-sm"
          :class="customerBalance > 0 ? 'text-rose-500' : 'text-emerald-500'"
        >
          {{ formatMoney(customerBalance) }} {{ $t('common.currency') }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useFormatters } from '../../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  customer: { type: Object, default: () => ({}) },
  customerName: { type: String, default: '' },
  customerPhone: { type: String, default: '' },
  customerBalance: { type: Number, default: 0 },
});
</script>
