<script setup>
import { Inbox } from 'lucide-vue-next';
import ErrorState from '../../Common/ErrorState.vue';
import EmptyState from '../../Common/EmptyState.vue';
import BillingPaymentsSkeleton from './BillingPaymentsSkeleton.vue';
import BillingPaymentCard from './BillingPaymentCard.vue';

defineProps({
  payments: { type: Array, default: () => [] },
  status: { type: String, default: 'pending' },
  isLoading: { type: Boolean, default: false },
  loadError: { type: String, default: '' },
  canManage: { type: Boolean, default: false },
  busyId: { type: [Number, String], default: null },
});

defineEmits(['retry', 'receipt', 'verify', 'reject']);
</script>

<template>
  <BillingPaymentsSkeleton v-if="isLoading" />

  <div
    v-else-if="loadError"
    class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-sm"
  >
    <ErrorState :title="$t('billing.console.queue.load_failed')" :message="loadError" @retry="$emit('retry')" />
  </div>

  <div
    v-else-if="!payments.length"
    class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-sm"
  >
    <EmptyState
      :icon="Inbox"
      :title="$t(`billing.console.queue.empty.${status}`)"
      :description="$t('billing.console.queue.empty_hint')"
    />
  </div>

  <div v-else class="grid grid-cols-1 xl:grid-cols-2 gap-4">
    <BillingPaymentCard
      v-for="payment in payments"
      :key="payment.id"
      :payment="payment"
      :can-manage="canManage"
      :busy="busyId === payment.id"
      @receipt="$emit('receipt', $event)"
      @verify="$emit('verify', $event)"
      @reject="$emit('reject', $event)"
    />
  </div>
</template>
