<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router';
import { ClipboardList } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import PageHeader from '@/Components/Common/PageHeader.vue';
import ErrorState from '@/Components/Common/ErrorState.vue';
import Skeleton from '@/Components/Common/Skeletons/Skeleton.vue';
import StockCountSheetHeader from '@/Components/StockCounts/StockCountSheetHeader.vue';
import StockCountScanBar from '@/Components/StockCounts/StockCountScanBar.vue';
import StockCountLinesTable from '@/Components/StockCounts/StockCountLinesTable.vue';
import StockCountPostModal from '@/Components/StockCounts/StockCountPostModal.vue';
import { useStockCountSheet } from '@/Composables/useStockCountSheet';
import { useStockCountActions } from '@/Composables/useStockCountActions';

const route = useRoute();
const router = useRouter();
const sheet = useStockCountSheet();
const { count, isLoading, error, search, filter, visibleLines, dirtyIds, countedTotal, isOpen, isBlind } = sheet;
const actions = useStockCountActions(sheet);
const showPost = ref(false);

const uncounted = computed(() => (count.value?.lines?.length || 0) - countedTotal.value);
const printRoute = (type) =>
  router.push({ name: 'stock_counts.print', params: { id: count.value.id }, query: { type } });

const confirmPost = async (zeroUncounted) => {
  if (await actions.post(zeroUncounted)) showPost.value = false;
};

onBeforeRouteLeave(async () => (dirtyIds.value.length ? (await Swal.fire(actions.leaveDialog())).isConfirmed : true));
onMounted(() => sheet.load(route.params.id));
</script>

<template>
  <div class="space-y-4 max-w-7xl mx-auto font-tajawal">
    <PageHeader :title="$t('stock_counts.title')" :icon="ClipboardList" back-href="/stock-counts" />

    <ErrorState v-if="error && !count" :message="error" @retry="sheet.load(route.params.id)" />
    <div v-else-if="isLoading && !count" class="space-y-3" aria-busy="true">
      <Skeleton height="h-36" rounded="rounded-3xl" />
      <Skeleton height="h-24" rounded="rounded-3xl" />
      <Skeleton height="h-80" rounded="rounded-3xl" />
    </div>
    <template v-else-if="count">
      <StockCountSheetHeader
        :count="count"
        :counted-total="countedTotal"
        :dirty-count="dirtyIds.length"
        :is-saving="sheet.isSaving.value"
        :is-posting="sheet.isPosting.value"
        @save="actions.save"
        @post="showPost = true"
        @cancel="actions.cancel"
        @print-sheet="printRoute('sheet')"
        @print-variance="printRoute('variance')"
      />
      <StockCountScanBar v-model:search="search" v-model:filter="filter" :can-scan="isOpen" @scan="actions.scan" />
      <StockCountLinesTable
        :lines="visibleLines"
        :is-loading="isLoading"
        :editable="isOpen"
        :hide-system="isBlind"
        :show-cost="count.can_view_cost"
        :highlight-id="sheet.lastScannedId.value"
        :counted-of="sheet.countedOf"
        :difference-of="sheet.differenceOf"
        @update="sheet.setCounted"
      />
      <StockCountPostModal
        :show="showPost"
        :number="count.count_number"
        :uncounted="uncounted"
        :loading="sheet.isPosting.value"
        @close="showPost = false"
        @confirm="confirmPost"
      />
    </template>
  </div>
</template>
