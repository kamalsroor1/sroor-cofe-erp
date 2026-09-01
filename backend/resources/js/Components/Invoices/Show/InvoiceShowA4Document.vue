<template>
  <div class="a4-document-wrapper w-full flex justify-center py-4 print:py-0 print:m-0">
    <div
      id="a4-print-area"
      class="a4-page bg-white text-slate-900 p-8 sm:p-12 shadow-2xl rounded-3xl border border-slate-200 w-full max-w-[210mm] font-tajawal select-none print:shadow-none print:border-none print:rounded-none print:p-0 print:m-0 print:w-full print:max-w-none"
      dir="rtl"
    >
      <!-- 1. Header: Enterprise Branding & Official Tax Invoice -->
      <div class="flex items-start justify-between border-b-2 border-slate-900 pb-5">
        <div class="space-y-1">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-slate-900 text-white flex items-center justify-center shadow-sm print:border print:border-slate-800">
              <Coffee class="w-6 h-6 stroke-[2]" />
            </div>
            <div>
              <h1 class="text-2xl font-black text-slate-950 tracking-tight leading-tight">{{ companyInfo?.name }}</h1>
              <p class="text-xs font-bold text-slate-600 mt-0.5">{{ companyInfo?.subtitle }}</p>
            </div>
          </div>

          <div class="mt-3 space-y-1 text-xs text-slate-600 font-bold">
            <div v-if="companyInfo?.address" class="flex items-center gap-1.5"><MapPin class="w-3.5 h-3.5 text-slate-700" /> <span>{{ companyInfo?.address }}</span></div>
            <div v-if="companyInfo?.phone" class="flex items-center gap-1.5"><Phone class="w-3.5 h-3.5 text-slate-700" /> <span dir="ltr">{{ companyInfo?.phone }}</span></div>
            <div class="font-mono text-slate-800 text-[11px] pt-1">
              <span>{{ $t('invoices.commercial_register') }} <strong class="text-slate-950">{{ companyInfo?.commercialRegister }}</strong></span>
              <span class="mx-2 text-slate-400">|</span>
              <span>{{ $t('invoices.tax_id_number') }} <strong class="text-slate-950">{{ companyInfo?.taxNumber }}</strong></span>
            </div>
          </div>
        </div>

        <!-- Official Tax Invoice Badge & Number -->
        <div class="text-end space-y-2 shrink-0">
          <div class="inline-block px-4 py-1.5 rounded-xl bg-slate-950 text-white font-black text-xs tracking-wider uppercase">
            {{ $t('invoices.official_tax_invoice') }}
          </div>
          <div class="text-xs font-mono font-bold text-slate-700 space-y-1">
            <div>{{ $t('invoices.invoice_number') }}: <strong class="text-slate-950 text-sm">#{{ invoice?.invoice_number }}</strong></div>
            <div>{{ $t('invoices.issued_date') }} <strong class="text-slate-950">{{ invoice?.invoice_date }}</strong></div>
          </div>
        </div>
      </div>

      <!-- 2. Customer & Store Grid -->
      <div class="grid grid-cols-2 gap-4 my-5 p-4 rounded-2xl bg-slate-50 border border-slate-300 text-xs print:bg-slate-50">
        <div>
          <div class="text-[10px] font-black text-slate-500 uppercase mb-1">{{ $t('invoices.customer_details') }}</div>
          <div class="font-black text-sm text-slate-950">{{ customerInfo?.name }}</div>
          <div v-if="customerInfo?.phone" class="text-slate-700 font-mono text-[11px] mt-0.5 flex items-center gap-1" dir="ltr"><Phone class="w-3 h-3 text-slate-700" /> <span>{{ customerInfo?.phone }}</span></div>
          <div v-if="customerInfo?.raw?.address" class="text-slate-600 mt-0.5 flex items-center gap-1"><MapPin class="w-3 h-3 text-slate-700" /> <span>{{ customerInfo.raw.address }}</span></div>
        </div>

        <div class="text-end">
          <div class="text-[10px] font-black text-slate-500 uppercase mb-1">{{ $t('invoices.store_details') }}</div>
          <div class="font-black text-sm text-slate-950">{{ invoice?.store_name }}</div>
          <div class="text-slate-700 mt-0.5">{{ $t('invoices.cashier') }}: <strong>{{ invoice?.cashier_name }}</strong></div>
          <div class="text-slate-700 mt-0.5">{{ $t('invoices.payment_method') }}: <strong>{{ invoice?.payment_method }}</strong></div>
        </div>
      </div>

      <!-- 3. Items Table -->
      <div class="my-5">
        <table class="w-full text-start text-xs border-collapse border border-slate-300">
          <thead>
            <tr class="bg-slate-100 text-slate-950 font-black border-b-2 border-slate-400 print:bg-slate-200">
              <th class="py-2 px-2.5 text-start w-8 border-l border-slate-300">#</th>
              <th class="py-2 px-2.5 text-start w-24 border-l border-slate-300">{{ $t('invoices.item_code_col') }}</th>
              <th class="py-2 px-2.5 text-start border-l border-slate-300">{{ $t('invoices.item_name_col') }}</th>
              <th class="py-2 px-2 text-center w-14 border-l border-slate-300">{{ $t('invoices.item_unit_col') }}</th>
              <th class="py-2 px-2 text-center w-14 border-l border-slate-300">{{ $t('invoices.item_qty_col') }}</th>
              <th class="py-2 px-2.5 text-end w-24 border-l border-slate-300">{{ $t('invoices.item_price_col') }}</th>
              <th class="py-2 px-2.5 text-end w-20 border-l border-slate-300">{{ $t('invoices.item_discount_col') }}</th>
              <th class="py-2 px-2.5 text-end w-28">{{ $t('invoices.item_total_col') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 font-mono">
            <tr v-for="(item, idx) in items" :key="item.id || idx" class="hover:bg-slate-50">
              <td class="py-2 px-2.5 text-slate-500 font-bold border-l border-slate-200">{{ idx + 1 }}</td>
              <td class="py-2 px-2.5 font-bold text-slate-700 border-l border-slate-200">{{ item.item_code || `ITM-${item.id}` }}</td>
              <td class="py-2 px-2.5 font-sans font-black text-slate-950 text-xs border-l border-slate-200">{{ item.name || item.item_name }}</td>
              <td class="py-2 px-2 text-center font-sans text-slate-600 border-l border-slate-200">{{ item.unit || 'قطعة' }}</td>
              <td class="py-2 px-2 text-center font-black text-slate-950 border-l border-slate-200">{{ formatMoney(item.quantity) }}</td>
              <td class="py-2 px-2.5 text-end font-bold text-slate-800 border-l border-slate-200">{{ formatMoney(item.unit_price) }}</td>
              <td class="py-2 px-2.5 text-end text-rose-600 font-bold border-l border-slate-200">
                {{ parseFloat(item.discount_amount || 0) > 0 ? '-' + formatMoney(item.discount_amount) : '—' }}
              </td>
              <td class="py-2 px-2.5 text-end font-black text-slate-950">{{ formatMoney(item.total_price) }} {{ $t('common.currency') }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 4. Financial Calculations & Summary Ledger -->
      <div class="grid grid-cols-12 gap-5 my-5">
        <!-- Terms and Notes -->
        <div class="col-span-7 space-y-2 text-xs">
          <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-300 space-y-1">
            <div class="font-black text-slate-950">{{ $t('invoices.terms_and_conditions') }}</div>
            <div class="text-[10px] text-slate-700 space-y-0.5 leading-relaxed font-bold">
              <p>{{ $t('invoices.terms_1') }}</p>
              <p>{{ $t('invoices.terms_2') }}</p>
              <p>{{ $t('invoices.terms_3') }}</p>
            </div>
          </div>
          <div v-if="invoice?.notes" class="p-3 rounded-2xl bg-slate-50 border border-slate-300 text-[11px] text-slate-800">
            <strong>{{ $t('invoices.notes') }}:</strong> {{ invoice.notes }}
          </div>
        </div>

        <!-- Totals Card -->
        <div class="col-span-5 p-4 rounded-2xl bg-slate-50 border border-slate-300 space-y-2 text-xs font-mono">
          <div class="flex justify-between text-slate-700 font-sans">
            <span>{{ $t('invoices.subtotal') }}:</span>
            <span class="font-mono font-bold">{{ formatMoney(invoice?.subtotal) }} {{ $t('common.currency') }}</span>
          </div>

          <div v-if="parseFloat(invoice?.discount_amount || 0) > 0" class="flex justify-between text-rose-600 font-sans">
            <span>{{ $t('invoices.discount') }}:</span>
            <span class="font-mono font-bold">- {{ formatMoney(invoice?.discount_amount) }} {{ $t('common.currency') }}</span>
          </div>

          <div v-if="parseFloat(invoice?.shipping_cost || 0) > 0" class="flex justify-between text-slate-700 font-sans">
            <span>{{ $t('invoices.shipping') }}:</span>
            <span class="font-mono font-bold">+ {{ formatMoney(invoice?.shipping_cost) }} {{ $t('common.currency') }}</span>
          </div>

          <div class="flex justify-between text-sm font-black text-slate-950 pt-2 border-t-2 border-slate-900 font-sans">
            <span>{{ $t('invoices.net_total') }}:</span>
            <span class="font-mono text-base font-black">{{ formatMoney(invoice?.net_total) }} {{ $t('common.currency') }}</span>
          </div>

          <div class="flex justify-between text-slate-800 font-sans pt-1 border-t border-dashed border-slate-300">
            <span>{{ $t('invoices.paid') }}:</span>
            <span class="font-mono font-black text-emerald-700">{{ formatMoney(invoice?.paid_amount) }} {{ $t('common.currency') }}</span>
          </div>

          <div v-if="parseFloat(invoice?.remaining_amount || 0) > 0" class="flex justify-between text-rose-600 font-sans font-bold">
            <span>{{ $t('invoices.remaining') }}:</span>
            <span class="font-mono font-black">{{ formatMoney(invoice?.remaining_amount) }} {{ $t('common.currency') }}</span>
          </div>
        </div>
      </div>

      <!-- 5. Signatures & Official Stamp (Avoid Page Break Inside) -->
      <div class="grid grid-cols-3 gap-6 pt-6 mt-8 border-t-2 border-slate-300 text-center text-xs break-inside-avoid">
        <div class="space-y-6">
          <div class="font-black text-slate-800">{{ $t('invoices.receiver_signature') }}</div>
          <div class="border-b border-dashed border-slate-400 w-3/4 mx-auto pt-4"></div>
        </div>

        <div class="space-y-6">
          <div class="font-black text-slate-800">{{ $t('invoices.authorized_signature') }}</div>
          <div class="border-b border-dashed border-slate-400 w-3/4 mx-auto pt-4"></div>
        </div>

        <div class="space-y-2">
          <div class="font-black text-slate-800">{{ $t('invoices.official_stamp') }}</div>
          <div class="w-14 h-14 rounded-full border-2 border-dashed border-slate-400 mx-auto flex items-center justify-center text-[10px] text-slate-400 font-bold">
            ختم
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Coffee, MapPin, Phone } from 'lucide-vue-next';
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
  @page {
    size: A4 portrait;
    margin: 8mm 10mm !important;
  }
  .a4-document-wrapper {
    padding: 0 !important;
    margin: 0 !important;
  }
  .a4-page {
    width: 100% !important;
    max-width: 100% !important;
    min-height: auto !important;
    padding: 0 !important;
    margin: 0 !important;
    box-shadow: none !important;
    border: none !important;
  }
  .break-inside-avoid {
    break-inside: avoid !important;
    page-break-inside: avoid !important;
  }
}
</style>
