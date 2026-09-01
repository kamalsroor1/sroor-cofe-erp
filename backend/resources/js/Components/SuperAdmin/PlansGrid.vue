<template>
  <div>
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <CardSkeleton v-for="i in 3" :key="i" :lines="6" />
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <PlanCard
        v-for="plan in plans"
        :key="plan.id"
        :plan="plan"
        @edit="$emit('edit', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import PlanCard from './PlanCard.vue';
import CardSkeleton from '../Common/Skeletons/CardSkeleton.vue';

defineProps({
  plans: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

defineEmits(['edit']);
</script>
