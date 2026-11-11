<script setup>
import { computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowRight, Printer } from 'lucide-vue-next';
import BaseButton from '@/Components/Common/BaseButton.vue';
import ErrorState from '@/Components/Common/ErrorState.vue';
import Skeleton from '@/Components/Common/Skeletons/Skeleton.vue';
import BillingInvoiceDocument from '@/Components/Billing/BillingInvoiceDocument.vue';
import { useBillingInvoice } from '@/Composables/useBillingInvoice';
import { useAppConfigStore } from '@/stores/appConfig';

const route = useRoute();
const router = useRouter();
const appConfig = useAppConfigStore();
const { invoice, isLoading, error, load } = useBillingInvoice();
const dir = computed(() => (appConfig.locale === 'en' ? 'ltr' : 'rtl'));

const printPage = () => window.print();

onMounted(() => load(route.params.id));
</script>

<template>
  <div class="min-h-screen bg-slate-200 dark:bg-slate-950 py-6 px-2 font-tajawal print:bg-white print:p-0" :dir="dir">
    <div class="no-print mx-auto mb-4 flex w-full max-w-[210mm] items-center justify-between gap-2">
      <BaseButton
        variant="secondary"
        :icon="ArrowRight"
        icon-class="rtl:rotate-0 ltr:rotate-180"
        @click="router.push({ name: 'billing.index' })"
      >
        {{ $t('common.back') }}
      </BaseButton>
      <BaseButton v-if="invoice" variant="primary" :icon="Printer" @click="printPage">
        {{ $t('common.print') }}
      </BaseButton>
    </div>

    <div v-if="isLoading" class="mx-auto max-w-[210mm] space-y-3 rounded-lg bg-white p-10" aria-busy="true">
      <Skeleton height="h-14" />
      <Skeleton height="h-24" />
      <Skeleton height="h-40" />
    </div>
    <ErrorState
      v-else-if="error"
      :message="error"
      :retry-label="$t('subscription.billing.page.retry')"
      @retry="load(route.params.id)"
    />
    <BillingInvoiceDocument v-else-if="invoice" :invoice="invoice" />
  </div>
</template>
