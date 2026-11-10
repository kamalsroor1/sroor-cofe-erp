<template>
  <div class="min-h-screen bg-slate-100 dark:bg-slate-950 print:bg-white p-3 sm:p-6 print:p-0 font-tajawal">
    <div class="print:hidden mx-auto mb-4 flex max-w-[210mm] items-center justify-between gap-3">
      <button
        type="button"
        class="min-h-[44px] px-4 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 text-xs font-black flex items-center gap-2 cursor-pointer active:scale-[0.98]"
        @click="goBack"
      >
        <ArrowRight class="w-4 h-4 rtl:rotate-0 ltr:rotate-180" />
        <span>{{ $t('billing.console.print.back') }}</span>
      </button>
      <button
        v-if="invoice"
        type="button"
        class="min-h-[44px] px-5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-black flex items-center gap-2 cursor-pointer active:scale-[0.98]"
        @click="printPage"
      >
        <Printer class="w-4 h-4" />
        <span>{{ $t('billing.console.print.print') }}</span>
      </button>
    </div>

    <div
      v-if="isLoading"
      class="mx-auto max-w-[210mm] rounded-2xl bg-white dark:bg-slate-900 p-8 space-y-4"
      role="status"
      aria-busy="true"
      :aria-label="$t('common.loading')"
    >
      <Skeleton width="w-48" height="h-8" />
      <Skeleton height="h-24" />
      <Skeleton height="h-48" />
      <Skeleton width="w-64" height="h-24" />
    </div>

    <div v-else-if="loadError" class="mx-auto max-w-[210mm] rounded-2xl bg-white dark:bg-slate-900">
      <ErrorState :title="$t('billing.console.print.load_failed')" :message="loadError" @retry="fetchInvoice" />
    </div>

    <InvoicePrintSheet v-else-if="invoice" :invoice="invoice" :brand="brand" />
  </div>
</template>

<script setup>
import { ArrowRight, Printer } from 'lucide-vue-next';
import { useRouter } from 'vue-router';
import ErrorState from '../../Components/Common/ErrorState.vue';
import Skeleton from '../../Components/Common/Skeletons/Skeleton.vue';
import InvoicePrintSheet from '../../Components/SuperAdmin/Billing/InvoicePrintSheet.vue';
import { useBillingInvoicePrint } from '../../Composables/useBillingInvoicePrint';

const router = useRouter();
const { invoice, brand, isLoading, loadError, fetchInvoice, printPage } = useBillingInvoicePrint();

const goBack = () => (window.history.length > 1 ? router.back() : router.push('/super-admin/billing'));
</script>
