<template>
  <div class="print-wrapper min-h-screen bg-slate-200 dark:bg-slate-950 py-6 px-2 font-tajawal text-slate-900 select-none flex flex-col items-center justify-start print:bg-white print:p-0 print:m-0 print:min-h-0" dir="rtl">
    
    <!-- Action Bar (Hidden on Print) -->
    <div class="no-print w-full max-w-[80mm] mb-4 flex items-center justify-between gap-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2.5 rounded-2xl shadow-lg">
      <button
        type="button"
        @click="triggerPrint"
        class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-black transition flex items-center justify-center gap-1.5 cursor-pointer shadow-xs active:scale-95"
      >
        <Printer class="w-4 h-4" />
        <span>{{ $t('invoices.print_receipt_btn') }}</span>
      </button>

      <button
        type="button"
        @click="goBack"
        class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1 cursor-pointer active:scale-95"
      >
        <ArrowRight class="w-4 h-4" />
        <span>{{ $t('common.back') }}</span>
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="no-print p-8 text-center text-slate-600 dark:text-slate-300 font-bold">
      <div class="w-8 h-8 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
      <p class="text-xs font-bold">{{ $t('invoices.preparing_receipt') }}</p>
    </div>

    <!-- 80mm Thermal Receipt Container -->
    <div
      v-else-if="invoice"
      id="receipt-print-area"
      class="receipt-card bg-white text-black p-3.5 shadow-xl rounded-lg border border-slate-300 print:shadow-none print:border-none print:rounded-none print:p-1"
    >
      <!-- 1. Header / Company Info -->
      <div class="text-center space-y-1 pb-2 border-b-2 border-dashed border-black">
        <h1 class="text-lg font-black tracking-tight leading-tight text-black">{{ companyName }}</h1>
        <p v-if="companySubtitle" class="text-[11px] font-bold text-black">{{ companySubtitle }}</p>
        <div class="text-[10px] font-bold text-black font-mono mt-0.5">
          {{ invoice.store_name || activeStoreName }}
        </div>
      </div>

      <!-- 2. Invoice Meta Details -->
      <div class="py-2 border-b-2 border-dashed border-black text-xs space-y-1 font-mono text-black">
        <div class="flex justify-between items-center">
          <span class="font-bold">{{ $t('invoices.invoice_number') }}:</span>
          <span class="font-black text-xs">{{ invoice.invoice_number }}</span>
        </div>
        <div class="flex justify-between items-center">
          <span><strong>{{ $t('common.date') }}:</strong> {{ invoice.invoice_date }}</span>
          <span><strong>{{ $t('invoices.invoice_time') }}</strong> {{ invoiceTime }}</span>
        </div>
        <div class="flex justify-between items-center font-tajawal">
          <span class="font-bold">{{ $t('invoices.customer') }}:</span>
          <span class="font-black">{{ customerName }}</span>
        </div>
        <div v-if="customerPhone" class="flex justify-between items-center">
          <span class="font-bold">{{ $t('common.phone') }}:</span>
          <span class="font-mono">{{ customerPhone }}</span>
        </div>
        <div v-if="invoice.cashier_name" class="flex justify-between items-center font-tajawal">
          <span>{{ $t('invoices.cashier') }}:</span>
          <span>{{ invoice.cashier_name }}</span>
        </div>
      </div>

      <!-- 3. Items Table -->
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
            <tr v-for="item in invoiceItems" :key="item.id" class="py-1">
              <td class="py-1.5 font-bold leading-tight text-[11px]">
                {{ item.name }}
              </td>
              <td class="text-center font-mono font-bold py-1.5">{{ formatMoney(item.quantity) }}</td>
              <td class="text-center font-mono py-1.5">{{ formatMoney(item.unit_price) }}</td>
              <td class="text-end font-mono font-black py-1.5">{{ formatMoney(item.total_price) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 4. Financial Totals -->
      <div class="py-2 border-b-2 border-dashed border-black text-xs space-y-1 text-black">
        <div class="flex justify-between items-center">
          <span class="font-bold">{{ $t('invoices.subtotal') }}:</span>
          <span class="font-mono font-bold">{{ formatMoney(calculatedSubtotal) }} {{ $t('common.currency') }}</span>
        </div>

        <div v-if="calculatedDiscount > 0" class="flex justify-between items-center">
          <span class="font-bold">{{ $t('invoices.discount') }}:</span>
          <span class="font-mono font-bold">- {{ formatMoney(calculatedDiscount) }} {{ $t('common.currency') }}</span>
        </div>

        <div v-if="calculatedShipping > 0" class="flex justify-between items-center">
          <span class="font-bold">{{ $t('invoices.extra_fees_shipping') }}</span>
          <span class="font-mono font-bold">+ {{ formatMoney(calculatedShipping) }} {{ $t('common.currency') }}</span>
        </div>

        <!-- GIANT NET TOTAL -->
        <div class="flex justify-between items-center text-sm font-black pt-1.5 border-t-2 border-black">
          <span class="text-sm font-black">{{ $t('invoices.net_total') }}:</span>
          <span class="font-mono text-base font-black">{{ formatMoney(calculatedNetTotal) }} {{ $t('common.currency') }}</span>
        </div>

        <div class="flex justify-between items-center text-xs pt-1 border-t border-dashed border-black/50">
          <span class="font-bold">{{ $t('invoices.payment_method') }}:</span>
          <span class="font-black">{{ formatPaymentType(invoice.payment_type || invoice.invoice_type) }}</span>
        </div>

        <div v-if="parseFloat(invoice.paid_amount) > 0" class="flex justify-between items-center text-xs">
          <span>{{ $t('invoices.amount_paid_label') }}</span>
          <span class="font-mono font-black">{{ formatMoney(invoice.paid_amount) }} {{ $t('common.currency') }}</span>
        </div>

        <div v-if="parseFloat(invoice.remaining_amount) > 0" class="flex justify-between items-center text-xs font-bold">
          <span>{{ $t('invoices.amount_remaining_label') }}</span>
          <span class="font-mono font-black">{{ formatMoney(invoice.remaining_amount) }} {{ $t('common.currency') }}</span>
        </div>
      </div>

      <!-- 5. Footer -->
      <div class="pt-2 text-center text-xs space-y-1 font-bold text-black">
        <p class="text-xs font-black">{{ $t('invoices.thank_you_note') }}</p>
        <p class="text-[9px]">{{ $t('invoices.return_policy_note') }}</p>
        <div class="pt-0.5 text-[8px] font-mono text-slate-800">{{ $t('invoices.printed_by_system', { system: appConfigStore.platformName || 'ERP' }) }}</div>
      </div>

    </div>

    <!-- Error State -->
    <div v-else class="no-print p-8 text-center text-rose-500 font-bold">
      <p class="text-xs">{{ $t('invoices.no_invoices_found') }}</p>
      <button @click="goBack" class="mt-4 px-4 py-1.5 bg-slate-800 text-white text-xs rounded-xl cursor-pointer">
        {{ $t('common.back') }}
      </button>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Printer, ArrowRight } from 'lucide-vue-next';
import api from '../../services/api';
import { useAppConfigStore } from '../../stores/appConfig';
import { useFormatters } from '../../Composables/useFormatters';
import { useTrans } from '../../Composables/useTrans';

const route = useRoute();
const router = useRouter();
const appConfigStore = useAppConfigStore();
const { formatMoney } = useFormatters();
const { t } = useTrans();

const invoice = ref(null);
const isLoading = ref(true);

const companyName = computed(() => appConfigStore.companyName || appConfigStore.platformName || '');
const companySubtitle = computed(() => appConfigStore.companySubtitle || '');
const activeStoreName = computed(() => appConfigStore.currentStore?.name || '');

const invoiceItems = computed(() => {
  if (!invoice.value) return [];
  return invoice.value.items || invoice.value.invoice_items || [];
});

const customerName = computed(() => {
  return invoice.value?.customer?.name || invoice.value?.customer_name || t('pos.general_walkin_customer');
});

const customerPhone = computed(() => {
  return invoice.value?.customer?.phone || invoice.value?.customer_phone || '';
});

const invoiceTime = computed(() => {
  if (!invoice.value?.created_at) return '';
  try {
    const d = new Date(invoice.value.created_at);
    return d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
  } catch (e) {
    return '';
  }
});

const calculatedSubtotal = computed(() => {
  return parseFloat(invoice.value?.subtotal || invoice.value?.total_amount || 0);
});

const calculatedDiscount = computed(() => {
  return parseFloat(invoice.value?.discount_amount || invoice.value?.discount || 0);
});

const calculatedShipping = computed(() => {
  return parseFloat(invoice.value?.shipping_cost || invoice.value?.additional_cost || 0);
});

const calculatedNetTotal = computed(() => {
  return parseFloat(invoice.value?.net_total || invoice.value?.final_total || 0);
});

const formatPaymentType = (type) => {
  const map = {
    cash: t('invoices.payment_cash'),
    credit: t('invoices.payment_credit'),
    partial: t('invoices.payment_partial'),
    instapay: t('invoices.payment_instapay'),
    wallet: t('invoices.payment_wallet'),
    card: t('invoices.payment_card'),
    bank: t('invoices.payment_bank'),
  };
  return map[type] || t('invoices.payment_cash');
};

const triggerPrint = () => {
  window.print();
};

const goBack = () => {
  if (window.history.length > 1) {
    router.back();
  } else {
    router.push('/invoices');
  }
};

const fetchInvoice = async () => {
  const id = route.params.id;
  if (!id) return;
  isLoading.value = true;
  try {
    const res = await api.get(`/invoices/${id}`);
    invoice.value = res.data?.data || res.data;
  } catch (e) {
    console.error('Failed to load invoice for printing', e);
  } finally {
    isLoading.value = false;
  }
};

onMounted(async () => {
  await fetchInvoice();
  if (route.query.autoprint === 'true') {
    setTimeout(() => {
      window.print();
    }, 500);
  }
});
</script>

<style scoped>
@media print {
  body, html {
    background-color: white !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  .no-print {
    display: none !important;
  }
  .print-wrapper {
    background-color: white !important;
    padding: 0 !important;
    margin: 0 !important;
    min-height: auto !important;
  }
  .receipt-card {
    width: 78mm !important;
    max-width: 78mm !important;
    margin: 0 auto !important;
    padding: 2mm !important;
    box-shadow: none !important;
    border: none !important;
    color: black !important;
  }
}
</style>
