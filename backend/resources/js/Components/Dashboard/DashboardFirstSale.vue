<template>
  <section
    class="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs"
    data-testid="dashboard-first-sale"
  >
    <EmptyState
      :icon="ShoppingBag"
      :title="$t('dashboard.first_sale.title')"
      :description="$t('dashboard.first_sale.description')"
    >
      <router-link
        v-if="canSell"
        to="/pos"
        data-testid="first-sale-cta"
        class="min-h-[44px] px-5 rounded-2xl btn-primary-theme text-sm font-black flex items-center gap-2 transition active:scale-95 shadow-theme-sm cursor-pointer"
      >
        <Zap class="w-4 h-4" />
        <span>{{ $t('dashboard.first_sale.action') }}</span>
      </router-link>
    </EmptyState>
  </section>
</template>
<script setup>
import { ShoppingBag, Zap } from 'lucide-vue-next';
import EmptyState from '../Common/EmptyState.vue';

defineProps({
  canSell: { type: Boolean, default: false },
});
</script>
