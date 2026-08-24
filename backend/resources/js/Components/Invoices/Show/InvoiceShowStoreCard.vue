<template>
  <div class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm space-y-4 font-tajawal no-print">
    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800/80 pb-3">
      <div class="flex items-center gap-2">
        <span class="text-base">🏬</span>
        <h3 class="text-xs font-black text-slate-900 dark:text-white">{{ $t('invoices.store_cashier_section') }}</h3>
      </div>
    </div>

    <div class="space-y-3 text-xs">
      <div class="flex items-center justify-between">
        <span class="text-slate-500 dark:text-slate-400 font-bold">{{ $t('invoices.store') }}:</span>
        <span class="font-bold text-slate-900 dark:text-white">{{ invoice?.store_name || $t('inventory.main_store') }}</span>
      </div>

      <div class="flex items-center justify-between">
        <span class="text-slate-500 dark:text-slate-400 font-bold">{{ $t('invoices.cashier') }}:</span>
        <span class="font-bold text-slate-900 dark:text-white">{{ invoice?.cashier_name || 'الكاشير' }}</span>
      </div>

      <div class="flex items-center justify-between">
        <span class="text-slate-500 dark:text-slate-400 font-bold">{{ $t('invoices.payment_method') }}:</span>
        <span class="font-black text-theme-primary">{{ formatPaymentMethod(invoice?.payment_method) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useTrans } from '../../../Composables/useTrans';

const { t } = useTrans();

defineProps({
  invoice: { type: Object, default: null },
});

const formatPaymentMethod = (method) => {
  const map = {
    cash: '💵 ' + t('invoices.payment_cash'),
    instapay: '⚡ ' + t('invoices.payment_instapay'),
    wallet: '📱 ' + t('invoices.payment_wallet'),
    card: '💳 ' + t('invoices.payment_card'),
    bank: '🏦 ' + t('invoices.payment_bank'),
  };
  return map[method] || (method ? method : '💵 ' + t('invoices.payment_cash'));
};
</script>
