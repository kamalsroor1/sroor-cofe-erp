<template>
  <div>
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
      <CardSkeleton v-for="i in 3" :key="i" :lines="6" />
    </div>

    <ErrorState v-else-if="hasError" @retry="$emit('retry')" />

    <EmptyState v-else-if="!plans.length" :title="$t('super.plans_empty')" />

    <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
      <PlanCard
        v-for="plan in plans"
        :key="plan.id"
        :plan="plan"
        :can-manage="canManage"
        @edit="$emit('edit', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import PlanCard from './PlanCard.vue';
import CardSkeleton from '../Common/Skeletons/CardSkeleton.vue';
import ErrorState from '../Common/ErrorState.vue';
import EmptyState from '../Common/EmptyState.vue';

defineProps({
  plans: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  hasError: { type: Boolean, default: false },
  canManage: { type: Boolean, default: false },
});

defineEmits(['edit', 'retry']);
</script>
