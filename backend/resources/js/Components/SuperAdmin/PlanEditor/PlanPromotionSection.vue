<template>
  <div class="space-y-4">
    <div
      class="flex flex-wrap items-center justify-between gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800"
    >
      <div class="flex items-center gap-2">
        <span class="text-xs font-black text-slate-700 dark:text-slate-200">{{
          $t('super.plan_promo_status_label')
        }}</span>
        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold border" :class="badgeClass">
          {{ $t(`super.plan_promo_status_${status}`) }}
        </span>
      </div>
      <BaseButton v-if="status !== 'off'" type="button" variant="secondary" size="md" @click="$emit('clear')">
        <Trash2 class="w-4 h-4 me-1.5" aria-hidden="true" />
        {{ $t('super.plan_promo_remove_btn') }}
      </BaseButton>
    </div>

    <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ $t('super.plan_promo_hint') }}</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <PlanPriceInput
        :model-value="form.compare_at_price_monthly"
        :label="$t('super.plan_compare_at_monthly_label')"
        :error="errors.compare_at_price_monthly"
        @update:model-value="$emit('update:field', 'compare_at_price_monthly', $event)"
      />
      <PlanPriceInput
        :model-value="form.compare_at_price_yearly"
        :label="$t('super.plan_compare_at_yearly_label')"
        :error="errors.compare_at_price_yearly"
        @update:model-value="$emit('update:field', 'compare_at_price_yearly', $event)"
      />
      <BaseInput
        :model-value="form.promo_starts_at"
        :label="$t('super.plan_promo_starts_label')"
        :error="errors.promo_starts_at"
        type="datetime-local"
        input-class="font-mono text-start"
        @update:model-value="$emit('update:field', 'promo_starts_at', $event)"
      />
      <BaseInput
        :model-value="form.promo_ends_at"
        :label="$t('super.plan_promo_ends_label')"
        :error="errors.promo_ends_at"
        type="datetime-local"
        input-class="font-mono text-start"
        @update:model-value="$emit('update:field', 'promo_ends_at', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Trash2 } from 'lucide-vue-next';
import BaseInput from '../../Form/BaseInput.vue';
import BaseButton from '../../Common/BaseButton.vue';
import PlanPriceInput from './PlanPriceInput.vue';
import { promotionStatus } from '../../../helpers/planEditor';

const props = defineProps({
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
});

defineEmits(['update:field', 'clear']);

const status = computed(() => promotionStatus(props.form));

const BADGES = {
  active: 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400',
  scheduled: 'bg-sky-500/10 border-sky-500/30 text-sky-600 dark:text-sky-400',
  ended: 'bg-amber-500/10 border-amber-500/30 text-amber-600 dark:text-amber-400',
  off: 'bg-slate-500/10 border-slate-500/30 text-slate-500 dark:text-slate-400',
};
const badgeClass = computed(() => BADGES[status.value]);
</script>
