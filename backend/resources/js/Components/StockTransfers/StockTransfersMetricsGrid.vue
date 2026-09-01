<template>
  <div class="font-tajawal">
    <!-- 🔄 Skeleton Loading State -->
    <div v-if="isLoading" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <StatCardSkeleton v-for="n in 3" :key="n" />
    </div>

    <!-- 📊 Summary Metrics Grid -->
    <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <!-- Total Transfers Count -->
      <MetricCard
        :title="$t('inventory.total_transfers_count')"
        :value="summary.total_count || 0"
        :currency="$t('inventory.transfer_doc_unit')"
        variant="default"
        :icon="Truck"
        icon-bg="bg-theme-primary/10"
        icon-color="text-theme-primary"
        :footer-left="$t('inventory.total_transfers_count_sub')"
      />

      <!-- Confirmed Transfers -->
      <MetricCard
        :title="$t('inventory.confirmed_transfers_title')"
        :value="summary.confirmed_count || 0"
        :currency="$t('inventory.transfer_status_done')"
        variant="cyan"
        :icon="CheckCircle2"
        icon-bg="bg-emerald-500/10"
        icon-color="text-emerald-500"
        :footer-left="$t('inventory.confirmed_transfers_sub')"
      />

      <!-- Cancelled Transfers -->
      <MetricCard
        :title="$t('inventory.cancelled_transfers_title')"
        :value="summary.cancelled_count || 0"
        :currency="$t('inventory.transfer_status_cancelled')"
        variant="primary"
        :icon="Ban"
        icon-bg="bg-rose-500/10"
        icon-color="text-rose-500"
        :footer-left="$t('inventory.cancelled_transfers_sub')"
      />
    </div>
  </div>
</template>

<script setup>
import { Truck, CheckCircle2, Ban } from 'lucide-vue-next';
import MetricCard from '../Common/MetricCard.vue';
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';

defineProps({
  summary: {
    type: Object,
    default: () => ({ total_count: 0, confirmed_count: 0, cancelled_count: 0 }),
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
});
</script>
