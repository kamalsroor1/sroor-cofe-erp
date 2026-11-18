<script setup>
import { toRef } from 'vue';
import { ArrowDownCircle } from 'lucide-vue-next';
import BaseButton from '@/Components/Common/BaseButton.vue';
import { useFreePlanDowngrade } from '@/Composables/useFreePlanDowngrade';

const props = defineProps({
  subscription: { type: Object, required: true },
});

const emit = defineEmits(['changed']);

const { freePlan, isOffered, appliesNow, isSubmitting, request } = useFreePlanDowngrade(
  toRef(props, 'subscription'),
  async () => emit('changed')
);
</script>

<template>
  <section
    v-if="isOffered"
    class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 sm:p-5 shadow-xs font-tajawal"
    data-testid="downgrade-card"
  >
    <div class="flex items-start gap-2.5 min-w-0">
      <ArrowDownCircle class="w-5 h-5 shrink-0 mt-0.5 text-slate-500 dark:text-slate-400" />
      <div class="min-w-0 space-y-0.5">
        <h2 class="text-sm font-black text-slate-900 dark:text-white">
          {{ $t('subscription.downgrade.title', { plan: freePlan.name }) }}
        </h2>
        <p class="text-xs font-bold leading-relaxed text-slate-600 dark:text-slate-400">
          {{ $t('subscription.downgrade.description') }}
        </p>
      </div>
    </div>
    <BaseButton variant="outline" :loading="isSubmitting" data-testid="downgrade-button" @click="request">
      {{ appliesNow ? $t('subscription.downgrade.action_now') : $t('subscription.downgrade.action') }}
    </BaseButton>
  </section>
</template>
