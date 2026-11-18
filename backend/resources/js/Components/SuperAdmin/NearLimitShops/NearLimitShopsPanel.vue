<script setup>
import { computed } from 'vue';
import { TrendingUp, RefreshCw, CircleCheck } from 'lucide-vue-next';
import DashboardSectionCard from '../../Common/DashboardSectionCard.vue';
import BaseButton from '../../Common/BaseButton.vue';
import EmptyState from '../../Common/EmptyState.vue';
import ErrorState from '../../Common/ErrorState.vue';
import Pagination from '../../Common/Pagination.vue';
import NearLimitShopRow from './NearLimitShopRow.vue';
import NearLimitShopsSkeleton from './NearLimitShopsSkeleton.vue';
import { useFormatters } from '../../../Composables/useFormatters';

const props = defineProps({
  rows: { type: Array, default: () => [] },
  meta: { type: Object, default: null },
  isLoading: { type: Boolean, default: false },
  hasError: { type: Boolean, default: false },
  threshold: { type: Number, default: 80 },
});

defineEmits(['refresh', 'retry', 'page-change']);

const { formatDateTime } = useFormatters();

const showRows = computed(() => !props.isLoading && !props.hasError && props.rows.length > 0);
const generatedAt = computed(() => (props.meta?.generated_at ? formatDateTime(props.meta.generated_at) : ''));
</script>

<template>
  <DashboardSectionCard
    :title="$t('plan_console.near_limit.title')"
    :subtitle="$t('plan_console.near_limit.subtitle', { percent: threshold })"
    :icon="TrendingUp"
    icon-bg="bg-amber-100 dark:bg-amber-500/15"
    icon-color="text-amber-600 dark:text-amber-400"
    wrapper-class="shadow-sm dark:shadow-xl"
  >
    <template #action>
      <BaseButton type="button" variant="secondary" size="md" :disabled="isLoading" @click="$emit('refresh')">
        <RefreshCw class="w-4 h-4 me-1.5" :class="{ 'animate-spin': isLoading }" aria-hidden="true" />
        {{ $t('plan_console.near_limit.refresh') }}
      </BaseButton>
    </template>

    <NearLimitShopsSkeleton v-if="isLoading" />

    <ErrorState v-else-if="hasError" :title="$t('plan_console.near_limit.load_failed')" @retry="$emit('retry')" />

    <EmptyState
      v-else-if="!rows.length"
      :icon="CircleCheck"
      :title="$t('plan_console.near_limit.empty_title')"
      :description="$t('plan_console.near_limit.empty_description', { percent: threshold })"
    />

    <div v-if="showRows" class="space-y-3" data-testid="near-limit-shops">
      <NearLimitShopRow v-for="row in rows" :key="`${row.tenant_id}-${row.resource}`" :row="row" />
      <Pagination v-if="meta?.last_page > 1" :pagination="meta" @page-change="$emit('page-change', $event)" />
    </div>

    <div
      v-if="meta && !isLoading && !hasError"
      class="flex flex-wrap gap-x-4 gap-y-1 text-[11px] font-medium text-slate-500 dark:text-slate-400"
    >
      <span>{{ $t('plan_console.near_limit.scanned', { count: meta.scanned }) }}</span>
      <span v-if="generatedAt">{{ $t('plan_console.near_limit.updated_at', { time: generatedAt }) }}</span>
      <span v-if="meta.capped" class="text-amber-600 dark:text-amber-400">
        {{ $t('plan_console.near_limit.capped', { count: meta.scanned }) }}
      </span>
      <span v-if="meta.failed > 0" class="text-rose-600 dark:text-rose-400">
        {{ $t('plan_console.near_limit.failed', { count: meta.failed }) }}
      </span>
    </div>
  </DashboardSectionCard>
</template>
