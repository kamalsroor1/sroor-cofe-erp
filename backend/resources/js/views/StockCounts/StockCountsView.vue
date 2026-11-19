<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ClipboardList, Plus } from 'lucide-vue-next';
import PageHeader from '@/Components/Common/PageHeader.vue';
import BaseButton from '@/Components/Common/BaseButton.vue';
import ErrorState from '@/Components/Common/ErrorState.vue';
import StockCountsTable from '@/Components/StockCounts/StockCountsTable.vue';
import StockCountsFilterBar from '@/Components/StockCounts/StockCountsFilterBar.vue';
import StartStockCountModal from '@/Components/StockCounts/StartStockCountModal.vue';
import { useStockCounts } from '@/Composables/useStockCounts';

const router = useRouter();
const {
  counts,
  categories,
  status,
  search,
  isLoading,
  isStarting,
  error,
  pagination,
  fetchCounts,
  fetchCategories,
  startCount,
} = useStockCounts();

const showStart = ref(false);
const startErrors = ref({});

const openStart = () => {
  startErrors.value = {};
  showStart.value = true;
  if (!categories.value.length) fetchCategories();
};

const submitStart = async (payload) => {
  try {
    const created = await startCount(payload);
    showStart.value = false;
    if (created) router.push({ name: 'stock_counts.show', params: { id: created.id } });
  } catch (err) {
    startErrors.value = err.response?.data?.errors || {};
  }
};

const openCount = (row) => router.push({ name: 'stock_counts.show', params: { id: row.id } });

onMounted(() => fetchCounts(1));
</script>

<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <PageHeader :title="$t('stock_counts.title')" :subtitle="$t('stock_counts.subtitle')" :icon="ClipboardList">
      <template #actions>
        <BaseButton
          variant="gradient"
          size="md"
          :icon="Plus"
          :label="$t('stock_counts.new_count')"
          data-testid="stock-count-new"
          @click="openStart"
        />
      </template>
    </PageHeader>

    <StockCountsFilterBar v-model:status="status" v-model:search="search" />

    <ErrorState v-if="error && !counts.length" :message="error" @retry="fetchCounts(pagination.current_page || 1)" />
    <StockCountsTable
      v-else
      :counts="counts"
      :pagination="pagination"
      :is-loading="isLoading"
      @open="openCount"
      @start="openStart"
      @page-change="fetchCounts"
    />

    <StartStockCountModal
      :show="showStart"
      :categories="categories"
      :loading="isStarting"
      :errors="startErrors"
      @close="showStart = false"
      @submit="submitStart"
    />
  </div>
</template>
