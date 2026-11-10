<script setup>
import { AlertTriangle, PackageOpen } from 'lucide-vue-next';
import EmptyState from '../Common/EmptyState.vue';
import ErrorState from '../Common/ErrorState.vue';
import Skeleton from '../Common/Skeletons/Skeleton.vue';
import PlanCard from './PlanCard.vue';

defineProps({
  plans: { type: Array, default: () => [] },
  isLoading: { type: Boolean, default: false },
  hasError: { type: Boolean, default: false },
  billing: { type: String, default: 'monthly' },
  currency: { type: String, default: '' },
  founder: { type: Object, default: () => ({ available: false }) },
  pricesExcludeVat: { type: Boolean, default: false },
  showDetails: { type: Boolean, default: true },
});

defineEmits(['retry']);
</script>

<template>
  <div v-if="isLoading" class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3" aria-busy="true">
    <div
      v-for="n in 3"
      :key="n"
      class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
    >
      <Skeleton width="w-1/2" height="h-6" />
      <Skeleton height="h-3" />
      <Skeleton width="w-2/3" height="h-10" />
      <Skeleton height="h-11" rounded="rounded-xl" />
      <Skeleton v-for="m in showDetails ? 6 : 2" :key="m" height="h-3" />
    </div>
  </div>

  <ErrorState
    v-else-if="hasError"
    :icon="AlertTriangle"
    :title="$t('landing.errors.plans_unavailable')"
    @retry="$emit('retry')"
  />

  <EmptyState v-else-if="!plans.length" :icon="PackageOpen" :title="$t('landing.pricing.plan_unavailable')" />

  <div v-else class="grid grid-cols-1 gap-5 pt-3 md:grid-cols-2 lg:grid-cols-3">
    <PlanCard
      v-for="plan in plans"
      :key="plan.code"
      :plan="plan"
      :billing="billing"
      :currency="currency"
      :founder="founder"
      :prices-exclude-vat="pricesExcludeVat"
      :show-details="showDetails"
    />
  </div>
</template>
