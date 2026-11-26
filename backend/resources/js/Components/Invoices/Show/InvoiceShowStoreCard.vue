<template>
  <div
    class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm space-y-4 font-tajawal no-print"
  >
    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800/80 pb-3">
      <div class="flex items-center gap-2">
        <Store class="w-4 h-4 text-theme-primary" />
        <h3 class="text-xs font-black text-slate-900 dark:text-white">{{ $t('invoices.store_cashier_section') }}</h3>
      </div>
    </div>

    <div class="space-y-3 text-xs">
      <div class="flex items-center justify-between">
        <span class="text-slate-500 dark:text-slate-400 font-bold">{{ $t('invoices.store') }}:</span>
        <span class="font-bold text-slate-900 dark:text-white">{{
          invoice?.store_name || $t('inventory.main_store')
        }}</span>
      </div>

      <div class="flex items-center justify-between">
        <span class="text-slate-500 dark:text-slate-400 font-bold">{{ $t('invoices.cashier') }}:</span>
        <span class="font-bold text-slate-900 dark:text-white">{{
          invoice?.cashier_name || $t('invoices.cashier')
        }}</span>
      </div>

      <div class="flex items-center justify-between">
        <span class="text-slate-500 dark:text-slate-400 font-bold">{{ $t('invoices.payment_method') }}:</span>
        <span class="font-black text-theme-primary flex items-center gap-1">
          <component :is="paymentMethodIcon(invoice?.payment_method)" class="w-3.5 h-3.5" />
          <span data-testid="invoice-show-payment">{{ invoicePaymentLabels(invoice, t).join(' · ') }}</span>
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Store, Banknote, Zap, Smartphone, CreditCard, Building2 } from 'lucide-vue-next';
import { useTrans } from '../../../Composables/useTrans';
import { invoicePaymentLabels, normalizePaymentMethod } from '../../../helpers/paymentMethod.js';

const { t } = useTrans();

defineProps({
  invoice: { type: Object, default: null },
});

const ICONS = {
  cash: Banknote,
  instapay: Zap,
  e_wallet: Smartphone,
  visa: CreditCard,
  bank_transfer: Building2,
};

const paymentMethodIcon = (method) => ICONS[normalizePaymentMethod(method)] || Banknote;
</script>
