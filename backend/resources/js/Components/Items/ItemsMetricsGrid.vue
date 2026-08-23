<template>
  <div>
    <!-- 🔄 Skeleton Loading State -->
    <div v-if="isLoading" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <StatCardSkeleton v-for="n in 3" :key="n" />
    </div>

    <!-- 📊 Metrics Cards -->
    <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <!-- Total Stock Valuation -->
      <MetricCard
        :title="$t('inventory.total_stock_value')"
        :value="formatMoney(metrics.total_stock_value || 0)"
        :currency="$t('common.currency')"
        variant="cyan"
        :icon="TrendingUp"
        icon-bg="bg-cyan-500/10"
        icon-color="text-cyan-500 dark:text-cyan-400"
        :footer-left="$t('inventory.total_stock_value_sub')"
      />

      <!-- Low Stock Count -->
      <MetricCard
        :title="$t('inventory.low_stock_count')"
        :value="String(metrics.low_stock_count || 0)"
        :currency="$t('inventory.item_unit')"
        variant="danger"
        :icon="AlertTriangle"
        icon-bg="bg-rose-500/10"
        icon-color="text-rose-500 dark:text-rose-400"
        :footer-left="$t('inventory.low_stock_count_sub')"
        footer-right-class="text-rose-500 font-bold"
      />

      <!-- Total Items Count -->
      <MetricCard
        :title="$t('inventory.total_items_count')"
        :value="String(metrics.total_items || 0)"
        :currency="$t('inventory.item_unit')"
        variant="default"
        :icon="Package"
        icon-bg="bg-theme-primary/10"
        icon-color="text-theme-primary"
        :footer-left="$t('inventory.total_items_sub')"
      />
    </div>
  </div>
</template>

<script setup>
import { TrendingUp, AlertTriangle, Package } from 'lucide-vue-next';
import MetricCard from '../Common/MetricCard.vue';
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  metrics: {
    type: Object,
    default: () => ({
      total_items: 0,
      low_stock_count: 0,
      total_stock_value: 0,
    }),
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
});
</script>
