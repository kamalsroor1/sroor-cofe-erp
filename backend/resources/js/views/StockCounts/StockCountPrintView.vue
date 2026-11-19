<script setup>
import { computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowRight, Printer } from 'lucide-vue-next';
import BaseButton from '@/Components/Common/BaseButton.vue';
import ErrorState from '@/Components/Common/ErrorState.vue';
import Skeleton from '@/Components/Common/Skeletons/Skeleton.vue';
import StockCountPrintDocument from '@/Components/StockCounts/StockCountPrintDocument.vue';
import { useStockCountPrint } from '@/Composables/useStockCountPrint';
import { useAppConfigStore } from '@/stores/appConfig';

const route = useRoute();
const router = useRouter();
const appConfig = useAppConfigStore();
const { count, variance, isLoading, error, load } = useStockCountPrint();

const type = computed(() => (route.query.type === 'variance' ? 'variance' : 'sheet'));
const dir = computed(() => (appConfig.locale === 'en' ? 'ltr' : 'rtl'));
const reload = () => load(route.params.id, type.value);
const printPage = () => window.print();

onMounted(reload);
</script>

<template>
  <div class="min-h-screen bg-slate-200 dark:bg-slate-950 py-6 px-2 font-tajawal print:bg-white print:p-0" :dir="dir">
    <div class="no-print print:hidden mx-auto mb-4 flex w-full max-w-[210mm] items-center justify-between gap-2">
      <BaseButton
        variant="secondary"
        :icon="ArrowRight"
        icon-class="rtl:rotate-0 ltr:rotate-180"
        @click="router.push({ name: 'stock_counts.show', params: { id: route.params.id } })"
      >
        {{ $t('common.back') }}
      </BaseButton>
      <BaseButton v-if="count" variant="primary" :icon="Printer" data-testid="stock-count-print-btn" @click="printPage">
        {{ $t('common.print') }}
      </BaseButton>
    </div>

    <div
      v-if="isLoading"
      class="mx-auto max-w-[210mm] space-y-3 rounded-lg bg-white dark:bg-slate-900 p-10"
      aria-busy="true"
    >
      <Skeleton height="h-14" />
      <Skeleton height="h-24" />
      <Skeleton height="h-64" />
    </div>
    <ErrorState v-else-if="error" :message="error" @retry="reload" />
    <StockCountPrintDocument v-else-if="count" :type="type" :count="count" :variance="variance" />
  </div>
</template>
