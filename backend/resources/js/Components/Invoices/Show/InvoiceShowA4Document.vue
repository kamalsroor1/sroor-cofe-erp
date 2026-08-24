<template>
  <div class="a4-document-wrapper w-full flex justify-center py-4">
    <div
      id="a4-print-area"
      class="a4-page bg-white text-slate-900 p-8 sm:p-12 shadow-2xl rounded-2xl border border-slate-200 w-full max-w-[210mm] min-h-[297mm] font-tajawal select-none print:shadow-none print:border-none print:rounded-none print:p-6 print:m-0 print:w-full"
      dir="rtl"
    >
      <div class="flex items-start justify-between border-b-2 border-slate-800 pb-6">
        <div>
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-theme-primary text-white flex items-center justify-center text-2xl font-black shadow-md">
              ☕
            </div>
            <div>
              <h1 class="text-2xl font-black text-slate-950 tracking-tight">{{ companyInfo?.name }}</h1>
              <p class="text-xs font-bold text-slate-600 mt-0.5">{{ companyInfo?.subtitle }}</p>
            </div>
          </div>
          <div class="mt-4 space-y-1 text-xs text-slate-600 font-bold">
            <div v-if="companyInfo?.address">📍 {{ companyInfo?.address }}</div>
            <div v-if="companyInfo?.phone">📞 {{ companyInfo?.phone }}</div>
            <div class="font-mono text-slate-700">
              <span>{{ $t('invoices.commercial_register') }} <strong>{{ companyInfo?.commercialRegister }}</strong></span>
              <span class="mx-2">|</span>
              <span>{{ $t('invoices.tax_id_number') }} <strong>{{ companyInfo?.taxNumber }}</strong></span>
            </div>
          </div>
        </div>
        <div class="text-end space-y-2">
          <div class="inline-block px-4 py-1.5 rounded-xl bg-slate-950 text-white font-black text-sm tracking-wider uppercase">
            {{ $t('invoices.official_tax_invoice') }}
          </div>
          <div class="text-xs font-mono font-bold text-slate-600">
            <div>{{ $t('invoices.invoice_number') }}: <strong class="text-slate-950 text-sm">#{{ invoice?.invoice_number }}</strong></div>
            <div>{{ $t('invoices.issued_date') }} <strong>{{ invoice?.invoice_date }}</strong></div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-6 my-6 p-4 rounded-2xl bg-slate-50 border border-slate-200 text-xs">
        <div>
          <div class="text-[11px] font-black text-slate-400 uppercase mb-1">{{ $t('invoices.customer_details') }}</div>
          <div class="font-black text-sm text-slate-950">{{ customerInfo?.name }}</div>
          <div v-if="customerInfo?.phone" class="text-slate-600 font-mono mt-0.5">📞 {{ customerInfo?.phone }}</div>
          <div v-if="customerInfo?.raw?.address" class="text-slate-600 mt-0.5">📍 {{ customerInfo.raw.address }}</div>
        </div>
        <div class="text-end">
          <div class="text-[11px] font-black text-slate-400 uppercase mb-1">{{ $t('invoices.store_details') }}</div>
          <div class="font-black text-sm text-slate-950">{{ invoice?.store_name }}</div>
          <div class="text-slate-600 mt-0.5">{{ $t('invoices.cashier') }}: <strong>{{ invoice?.cashier_name }}</strong></div>
          <div class="text-slate-600 mt-0.5">{{ $t('invoices.payment_method') }}: <strong>{{ invoice?.payment_method }}</strong></div>
        </div>
      </div>

      <div class="my-6">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="bg-slate-950 text-white font-black">
              <th class="py-2.5 px-3 text-start w-10">#</th>
              <th class="py-2.5 px-3 text-start">{{ $t('invoices.item_code_col') }}</th>
              <th class="py-2.5 px-3 text-start">{{ $t('invoices.item_name_col') }}</th>
              <th class="py-2.5 px-3 text-center">{{ $t('invoices.item_unit_col') }}</th>
              <th class="py-2.5 px-3 text-center">{{ $t('invoices.item_qty_col') }}</th>
              <th class="py-2.5 px-3 text-end">{{ $t('invoices.item_price_col') }}</th>
              <th class="py-2.5 px-3 text-end">{{ $t('invoices.item_discount_col') }}</th>
              <th class="py-2.5 px-3 text-end">{{ $t('invoices.item_total_col') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 border-b-2 border-slate-950 font-mono">
            <tr v-for="(item, idx) in items" :key="item.id || idx">
              <td class="py-2.5 px-3 text-slate-400 font-bold">{{ idx + 1 }}</td>
              <td class="py-2.5 px-3 font-bold">{{ item.item_code || `ITM-${item.id}` }}</td>
              <td class="py-2.5 px-3 font-sans font-black text-slate-950 text-xs">{{ item.name || item.item_name }}</td>
              <td class="py-2.5 px-3 text-center font-sans text-slate-600">{{ item.unit || 'قطعة' }}</td>
              <td class="py-2.5 px-3 text-center font-black text-slate-950">{{ formatMoney(item.quantity) }}</td>
              <td class="py-2.5 px-3 text-end font-bold">{{ formatMoney(item.unit_price) }}</td>
              <td class="py-2.5 px-3 text-end text-rose-600 font-bold">
                {{ parseFloat(item.discount_amount || 0) > 0 ? '-' + formatMoney(item.discount_amount) : '—' }}
              </td>
              <td class="py-2.5 px-3 text-end font-black text-slate-950">{{ formatMoney(item.total_price) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="grid grid-cols-2 gap-8 my-6">
        <div class="space-y-3 text-xs">
          <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 space-y-1.5">
            <div class="font-bold text-slate-900">{{ $t('invoices.terms_and_conditions') }}</div>
            <div class="text-[10px] text-slate-600 space-y-1">
              <p>{{ $t('invoices.terms_1') }}</p>
              <p>{{ $t('invoices.terms_2') }}</p>
              <p>{{ $t('invoices.terms_3') }}</p>
            </div>
          </div>
        </div>
        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-2 text-xs font-mono">
          <div class="flex justify-between text-slate-600 font-sans">
            <span>{{ $t('invoices.subtotal') }}:</span>
            <span class="font-mono font-bold">{{ formatMoney(invoice?.subtotal) }} {{ $t('common.currency') }}</span>
          </div>
          <div v-if="parseFloat(invoice?.discount_amount || 0) > 0" class="flex justify-between text-rose-600 font-sans">
            <span>{{ $t('invoices.discount') }}:</span>
            <span class="font-mono font-bold">- {{ formatMoney(invoice?.discount_amount) }} {{ $t('common.currency') }}</span>
          </div>
          <div v-if="parseFloat(invoice?.shipping_cost || 0) > 0" class="flex justify-between text-slate-600 font-sans">
            <span>{{ $t('invoices.shipping') }}:</span>
            <span class="font-mono font-bold">+ {{ formatMoney(invoice?.shipping_cost) }} {{ $t('common.currency') }}</span>
          </div>
          <div class="flex justify-between text-base font-black text-slate-950 pt-2 border-t-2 border-slate-800 font-sans">
            <span>{{ $t('invoices.net_total') }}:</span>
            <span class="font-mono text-lg font-black">{{ formatMoney(invoice?.net_total) }} {{ $t('common.currency') }}</span>
          </div>
          <div class="flex justify-between text-slate-700 font-sans pt-1 border-t border-dashed border-slate-300">
            <span>{{ $t('invoices.paid') }}:</span>
            <span class="font-mono font-black text-emerald-600">{{ formatMoney(invoice?.paid_amount) }} {{ $t('common.currency') }}</span>
          </div>
          <div v-if="parseFloat(invoice?.remaining_amount || 0) > 0" class="flex justify-between text-rose-600 font-sans font-bold">
            <span>{{ $t('invoices.remaining') }}:</span>
            <span class="font-mono font-black">{{ formatMoney(invoice?.remaining_amount) }} {{ $t('common.currency') }}</span>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-3 gap-6 pt-8 mt-12 border-t border-slate-300 text-center text-xs">
        <div class="space-y-8">
          <div class="font-bold text-slate-700">{{ $t('invoices.receiver_signature') }}</div>
          <div class="border-b border-dashed border-slate-400 w-3/4 mx-auto"></div>
        </div>
        <div class="space-y-8">
          <div class="font-bold text-slate-700">{{ $t('invoices.authorized_signature') }}</div>
          <div class="border-b border-dashed border-slate-400 w-3/4 mx-auto"></div>
        </div>
        <div class="space-y-8">
          <div class="font-bold text-slate-700">{{ $t('invoices.official_stamp') }}</div>
          <div class="w-16 h-16 rounded-full border-2 border-dashed border-slate-400 mx-auto flex items-center justify-center text-[10px] text-slate-400 font-bold">
            ختم
          </div>
        </div>
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
});
</script>

<style scoped>
@media print {
  .a4-page {
    width: 210mm !important;
    max-width: 210mm !important;
    min-height: 297mm !important;
    padding: 10mm !important;
    margin: 0 !important;
    box-shadow: none !important;
    border: none !important;
  }
}
</style>
