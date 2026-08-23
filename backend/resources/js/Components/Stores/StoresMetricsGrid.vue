<template>
  <div class="font-tajawal">
    <!-- 🔄 Skeleton Loading State -->
    <div v-if="isLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <StatCardSkeleton v-for="n in 4" :key="n" />
    </div>

    <!-- 📊 Summary Metrics Grid -->
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total Stores Count -->
      <MetricCard
        :title="$t('inventory.total_stores_count')"
        :value="String(stores.length)"
        :currency="$t('inventory.store_unit')"
        variant="default"
        :icon="Store"
        icon-bg="bg-theme-primary/10"
        icon-color="text-theme-primary"
        :footer-left="$t('inventory.total_stores_sub')"
      />

      <!-- Active Branches -->
      <MetricCard
        :title="$t('inventory.active_stores_count')"
        :value="String(activeStoresCount)"
        :currency="$t('inventory.store_unit')"
        variant="success"
        :icon="CheckCircle2"
        icon-bg="bg-emerald-500/10"
        icon-color="text-emerald-500"
        :footer-left="$t('inventory.active_stores_sub')"
      />

      <!-- Main Store Name -->
      <MetricCard
        :title="$t('inventory.main_store_title')"
        :value="mainStoreName"
        currency=""
        variant="cyan"
        :icon="Building2"
        icon-bg="bg-cyan-500/10"
        icon-color="text-cyan-500 dark:text-cyan-400"
        :footer-left="$t('inventory.main_store_sub')"
      />

      <!-- Total SKUs -->
      <MetricCard
        :title="$t('inventory.total_sku_count')"
        :value="String(totalSkusCount)"
        :currency="$t('inventory.item_unit')"
        variant="primary"
        :icon="Package"
        icon-bg="bg-theme-light"
        icon-color="text-theme-primary"
        :footer-left="$t('inventory.total_sku_sub')"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Store, CheckCircle2, Building2, Package } from 'lucide-vue-next';
import MetricCard from '../Common/MetricCard.vue';
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';

const props = defineProps({
  stores: {
    type: Array,
    default: () => [],
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
});

const activeStoresCount = computed(() => props.stores.filter(s => s.is_active).length);

const mainStore = computed(() => props.stores.find(s => s.is_main));
const mainStoreName = computed(() => mainStore.value?.name || '-');

const totalSkusCount = computed(() => props.stores.reduce((acc, s) => acc + (s.stocks_count || 0), 0));
</script>
