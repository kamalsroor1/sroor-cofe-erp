<template>
  <div class="thermal-receipt-wrapper w-full flex justify-center py-4">
    <div
      id="receipt-print-area"
      class="receipt-card bg-white text-black p-4 shadow-2xl rounded-2xl border border-slate-300 w-[80mm] max-w-[80mm] font-tajawal select-none print:shadow-none print:border-none print:rounded-none print:p-1 print:w-[78mm]"
      dir="rtl"
    >
      <div class="text-center space-y-1 pb-2 border-b-2 border-dashed border-black">
        <h1 class="text-lg font-black tracking-tight leading-tight text-black">{{ companyInfo?.name }}</h1>
        <p v-if="companyInfo?.subtitle" class="text-[11px] font-bold text-black">{{ companyInfo?.subtitle }}</p>
        <div class="text-[10px] font-bold text-black font-mono mt-0.5">{{ invoice?.store_name }}</div>
      </div>

      <div class="py-2 border-b-2 border-dashed border-black text-xs space-y-1 font-mono text-black">
        <div class="flex justify-between items-center">
          <span class="font-bold">{{ $t('invoices.invoice_number') }}:</span>
          <span class="font-black text-xs">#{{ invoice?.invoice_number }}</span>
        </div>
        <div class="flex justify-between items-center">
          <span><strong>{{ $t('common.date') }}:</strong> {{ invoice?.invoice_date }}</span>
          <span><strong>{{ $t('invoices.invoice_time') }}</strong> {{ invoiceTime }}</span>
        </div>
        <div class="flex justify-between items-center font-tajawal">
          <span class="font-bold">{{ $t('invoices.customer') }}:</span>
          <span class="font-black">{{ customerInfo?.name }}</span>
        </div>
        <div v-if="customerInfo?.phone" class="flex justify-between items-center">
          <span class="font-bold">{{ $t('common.phone') }}:</span>
          <span class="font-mono">{{ customerInfo?.phone }}</span>
        </div>
        <div v-if="invoice?.cashier_name" class="flex justify-between items-center font-tajawal">
          <span>{{ $t('invoices.cashier') }}:</span>
          <span>{{ invoice?.cashier_name }}</span>
        </div>
      </div>

      <div class="py-2 border-b-2 border-dashed border-black">
        <table class="w-full text-xs text-start border-collapse text-black">
          <thead>
            <tr class="border-b-2 border-black text-xs font-black">
              <th class="text-start py-1">{{ $t('invoices.item') }}</th>
              <th class="text-center py-1 w-9">{{ $t('invoices.quantity') }}</th>
              <th class="text-center py-1 w-14">{{ $t('invoices.unit_price') }}</th>
              <th class="text-end py-1 w-16">{{ $t('invoices.total') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-black/30">
            <tr v-for="item in items" :key="item.id" class="py-1">
              <td class="py-1.5 font-bold leading-tight text-[11px]">{{ item.name || item.item_name }}</td>
              <td class="text-center font-mono font-bold py-1.5">{{ formatMoney(item.quantity) }}</td>
              <td class="text-center font-mono py-1.5">{{ formatMoney(item.unit_price) }}</td>
              <td class="text-end font-mono font-black py-1.5">{{ formatMoney(item.total_price) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="py-2 border-b-2 border-dashed border-black text-xs space-y-1 text-black">
        <div class="flex justify-between items-center">
          <span class="font-bold">{{ $t('invoices.subtotal') }}:</span>
          <span class="font-mono font-bold">{{ formatMoney(invoice?.subtotal) }} {{ $t('common.currency') }}</span>
        </div>
        <div v-if="parseFloat(invoice?.discount_amount || 0) > 0" class="flex justify-between items-center">
          <span class="font-bold">{{ $t('invoices.discount') }}:</span>
          <span class="font-mono font-bold">- {{ formatMoney(invoice?.discount_amount) }} {{ $t('common.currency') }}</span>
        </div>
        <div v-if="parseFloat(invoice?.shipping_cost || 0) > 0" class="flex justify-between items-center">
          <span class="font-bold">{{ $t('invoices.extra_fees_shipping') }}</span>
          <span class="font-mono font-bold">+ {{ formatMoney(invoice?.shipping_cost) }} {{ $t('common.currency') }}</span>
        </div>
        <div class="flex justify-between items-center text-sm font-black pt-1.5 border-t-2 border-black">
          <span class="text-sm font-black">{{ $t('invoices.net_total') }}:</span>
          <span class="font-mono text-base font-black">{{ formatMoney(invoice?.net_total) }} {{ $t('common.currency') }}</span>
        </div>
        <div class="flex justify-between items-center text-xs pt-1 border-t border-dashed border-black/50">
          <span class="font-bold">{{ $t('invoices.payment_method') }}:</span>
          <span class="font-black">{{ invoice?.payment_method }}</span>
        </div>
        <div v-if="parseFloat(invoice?.paid_amount || 0) > 0" class="flex justify-between items-center text-xs">
          <span>{{ $t('invoices.amount_paid_label') }}</span>
          <span class="font-mono font-black">{{ formatMoney(invoice?.paid_amount) }} {{ $t('common.currency') }}</span>
        </div>
        <div v-if="parseFloat(invoice?.remaining_amount || 0) > 0" class="flex justify-between items-center text-xs font-bold">
          <span>{{ $t('invoices.amount_remaining_label') }}</span>
          <span class="font-mono font-black">{{ formatMoney(invoice?.remaining_amount) }} {{ $t('common.currency') }}</span>
        </div>
      </div>

      <div class="pt-2 text-center text-xs space-y-1 font-bold text-black">
        <p class="text-xs font-black">{{ $t('invoices.thank_you_note') }}</p>
        <p class="text-[9px]">{{ $t('invoices.return_policy_note') }}</p>
        <div class="pt-0.5 text-[8px] font-mono text-slate-800">{{ $t('invoices.printed_by_system', { system: companyInfo?.name }) }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useFormatters } from '../../../Composables/useFormatters';
const { formatMoney } = useFormatters();
defineProps({
  invoice: { type: Object, default: null },
  items: { type: Array, default: () => [] },
  companyInfo: { type: Object, default: () => ({}) },
  customerInfo: { type: Object, default: () => ({}) },
  invoiceTime: { type: String, default: '' },
});
</script>

<style scoped>
@media print {
  @page {
    size: 80mm auto;
    margin: 0mm !important;
  }
  .thermal-receipt-wrapper {
    padding: 0 !important;
    margin: 0 !important;
  }
  .receipt-card {
    width: 78mm !important;
    max-width: 78mm !important;
    padding: 2mm !important;
    margin: 0 auto !important;
    box-shadow: none !important;
    border: none !important;
  }
}
</style>
