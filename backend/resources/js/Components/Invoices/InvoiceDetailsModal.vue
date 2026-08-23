<template>
  <AppModal
    :show="show"
    :title="$t('invoices.sales_invoice_title', { number: invoice?.invoice_number || '' })"
    @close="$emit('close')"
  >
    <div v-if="invoice" class="space-y-4 font-tajawal text-xs">
      <!-- Top Info Cards -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-3.5 bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 rounded-2xl">
        <div>
          <span class="text-slate-400 block font-bold">{{ $t('invoices.customer') }}:</span>
          <span class="text-slate-900 dark:text-white font-bold">{{ invoice.customer_name }}</span>
        </div>
        <div>
          <span class="text-slate-400 block font-bold">{{ $t('common.date') }}:</span>
          <span class="text-slate-900 dark:text-slate-200 font-mono">{{ invoice.invoice_date }}</span>
        </div>
        <div>
          <span class="text-slate-400 block font-bold">{{ $t('invoices.branch_cashier') }}</span>
          <span class="text-slate-900 dark:text-slate-200">{{ invoice.store_name }} ({{ invoice.cashier_name }})</span>
        </div>
        <div>
          <span class="text-slate-400 block font-bold">{{ $t('invoices.payment_method') }}:</span>
          <span class="font-bold text-theme-primary">{{ formatPaymentType(invoice.payment_type) }}</span>
        </div>
      </div>

      <!-- Items Table -->
      <div class="border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100 dark:bg-slate-950 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
              <th class="p-2.5 text-start font-bold">{{ $t('invoices.item') }}</th>
              <th class="p-2.5 text-end font-bold">{{ $t('invoices.quantity') }}</th>
              <th class="p-2.5 text-end font-bold">{{ $t('invoices.sale_price') }}</th>
              <th class="p-2.5 text-end font-bold">{{ $t('common.total') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800 font-sans">
            <tr v-for="item in invoice.items" :key="item.id">
              <td class="p-2.5 font-bold font-tajawal text-slate-900 dark:text-white">{{ item.item_name || item.name }}</td>
              <td class="p-2.5 text-end font-mono text-slate-700 dark:text-slate-300">{{ formatMoney(item.quantity) }} {{ item.unit }}</td>
              <td class="p-2.5 text-end font-mono text-slate-700 dark:text-slate-300">{{ formatMoney(item.unit_price) }}</td>
              <td class="p-2.5 text-end font-mono font-bold text-slate-900 dark:text-white">{{ formatMoney(item.total_price) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Financial Breakdown -->
      <div class="p-3.5 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1.5 font-mono">
        <div class="flex justify-between text-slate-600 dark:text-slate-400 font-tajawal">
          <span>{{ $t('invoices.subtotal') }}:</span>
          <span class="font-bold font-mono">{{ formatMoney(invoice.subtotal) }} {{ $t('common.currency') }}</span>
        </div>
        <div v-if="invoice.discount_amount > 0" class="flex justify-between text-rose-500 font-tajawal">
          <span>{{ $t('invoices.discount') }}:</span>
          <span class="font-bold font-mono">- {{ formatMoney(invoice.discount_amount) }} {{ $t('common.currency') }}</span>
        </div>
        <div class="flex justify-between text-sm font-black text-slate-900 dark:text-white pt-2 border-t border-slate-200 dark:border-slate-800 font-tajawal">
          <span>{{ $t('invoices.net_total') }}:</span>
          <span class="text-emerald-500 text-base font-mono">{{ formatMoney(invoice.net_total) }} {{ $t('common.currency') }}</span>
        </div>
      </div>

      <!-- Modal Action Footer Buttons -->
      <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-800 flex-wrap">
        <!-- WhatsApp Share Button -->
        <a
          v-if="whatsApp?.url"
          :href="whatsApp.url"
          target="_blank"
          class="min-h-[44px] px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold text-xs flex items-center gap-1.5 transition active:scale-95 select-none"
        >
          <Share2 class="w-4 h-4" />
          <span>{{ $t('invoices.share_whatsapp_btn') }}</span>
        </a>

        <!-- Print Thermal Receipt Button -->
        <button
          type="button"
          @click="$emit('print', invoice.id)"
          class="min-h-[44px] px-4 py-2.5 bg-slate-900 hover:bg-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700 text-white rounded-xl font-bold text-xs flex items-center gap-1.5 transition cursor-pointer active:scale-95 select-none"
        >
          <Printer class="w-4 h-4 text-theme-primary" />
          <span>{{ $t('invoices.print_receipt_btn') }}</span>
        </button>
      </div>
    </div>
  </AppModal>
</template>

<script setup>
import { Share2, Printer } from 'lucide-vue-next';
import AppModal from '../Common/AppModal.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { trans } from '../../helpers/trans';

const { formatMoney } = useFormatters();

defineProps({
  show: { type: Boolean, default: false },
  invoice: { type: Object, default: null },
  whatsApp: { type: Object, default: null },
});

defineEmits(['close', 'print']);

const formatPaymentType = (type) => {
  const map = {
    cash: `💵 ${trans('invoices.cash') || 'نقدي'}`,
    credit: `📝 ${trans('invoices.credit') || 'آجل'}`,
    partial: `⚖️ ${trans('invoices.partial') || 'جزئي'}`,
    bank_transfer: `⚡ ${trans('invoices.electronic_transfer') || 'تحويل إلكتروني'}`,
  };
  return map[type] || type;
};
</script>
