<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { MousePointerClick } from 'lucide-vue-next';
import Skeleton from '../../Common/Skeletons/Skeleton.vue';
import BillingQueueList from './BillingQueueList.vue';
import BillingPaymentDetail from './BillingPaymentDetail.vue';
import { useCompactViewport } from '../../../Composables/useMediaQuery';

const props = defineProps({
  payments: { type: Array, default: () => [] },
  selected: { type: Object, default: null },
  selectedId: { type: [Number, String], default: null },
  total: { type: Number, default: 0 },
  isLoading: { type: Boolean, default: false },
  loadError: { type: String, default: '' },
  canManage: { type: Boolean, default: false },
});

defineEmits(['select', 'back', 'retry', 'decided']);

const compact = useCompactViewport();
// Chips count down from the server's window end: one shared minute tick for every row.
const now = ref(Date.now());
const timer = window.setInterval(() => {
  now.value = Date.now();
}, 60_000);
onBeforeUnmount(() => window.clearInterval(timer));

// Phone/tablet: queue list -> detail page. Desktop: both panes.
const showQueue = computed(() => !compact.value || !props.selected);
const showDetail = computed(() => !compact.value || !!props.selected);
</script>

<template>
  <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-[320px_minmax(0,1fr)]">
    <BillingQueueList
      v-if="showQueue"
      :payments="payments"
      :selected-id="selectedId"
      :total="total"
      :is-loading="isLoading"
      :load-error="loadError"
      :now="now"
      @select="$emit('select', $event)"
      @retry="$emit('retry')"
    />

    <template v-if="showDetail">
      <div
        v-if="isLoading"
        class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
        role="status"
        aria-busy="true"
        :aria-label="$t('common.loading')"
      >
        <Skeleton height="h-48" rounded="rounded-2xl" />
        <Skeleton v-for="line in 6" :key="line" height="h-4" :width="line % 2 ? 'w-full' : 'w-2/3'" />
      </div>
      <BillingPaymentDetail
        v-else-if="selected"
        :payment="selected"
        :can-manage="canManage"
        :compact="compact"
        :now="now"
        @back="$emit('back')"
        @decided="$emit('decided', $event)"
      />
      <p
        v-else-if="!loadError && payments.length"
        class="hidden items-center justify-center gap-2 rounded-2xl border border-dashed border-slate-300 p-10 text-sm font-bold text-slate-500 dark:border-slate-700 dark:text-slate-400 lg:flex"
      >
        <MousePointerClick class="h-5 w-5" aria-hidden="true" />
        <span>{{ $t('billing.console.verify.select_hint') }}</span>
      </p>
    </template>
  </div>
</template>
