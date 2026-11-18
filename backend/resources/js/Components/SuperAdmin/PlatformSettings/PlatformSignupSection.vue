<script setup>
import { computed } from 'vue';
import { UserPlus } from 'lucide-vue-next';
import DashboardSectionCard from '../../Common/DashboardSectionCard.vue';
import BaseInput from '../../Form/BaseInput.vue';
import BaseSelect from '../../Form/BaseSelect.vue';
import BaseSwitch from '../../Form/BaseSwitch.vue';
import { useTrans } from '../../../Composables/useTrans';

const props = defineProps({
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  readonly: { type: Boolean, default: false },
  // [{ id, name, isActive, isFree }] from usePlatformPlanOptions().
  plans: { type: Array, default: () => [] },
  plansAvailable: { type: Boolean, default: true },
  effectiveFallbackPlanId: { type: Number, default: null },
});

defineEmits(['update:field']);

const { t } = useTrans();

const toOptions = (list, emptyLabel) => [
  { value: '', label: emptyLabel },
  ...list.map((plan) => ({ value: String(plan.id), label: plan.name })),
];

const defaultPlanOptions = computed(() =>
  toOptions(
    props.plans.filter((plan) => plan.isActive),
    t('plan_console.signup.default_plan_placeholder')
  )
);
const fallbackPlanOptions = computed(() =>
  toOptions(
    props.plans.filter((plan) => plan.isActive && plan.isFree),
    t('plan_console.signup.fallback_plan_placeholder')
  )
);

const selectsDisabled = computed(() => props.readonly || !props.plansAvailable);

const effectiveFallbackText = computed(() => {
  const plan = props.plans.find((item) => item.id === props.effectiveFallbackPlanId);
  return plan
    ? t('plan_console.signup.effective_fallback', { plan: plan.name })
    : t('plan_console.signup.effective_fallback_none');
});
</script>

<template>
  <DashboardSectionCard
    :title="$t('plan_console.signup.title')"
    :subtitle="$t('plan_console.signup.subtitle')"
    :icon="UserPlus"
    icon-bg="bg-sky-100 dark:bg-sky-500/15"
    icon-color="text-sky-600 dark:text-sky-400"
    wrapper-class="shadow-sm dark:shadow-xl"
  >
    <p
      v-if="!plansAvailable"
      class="p-3 rounded-xl text-xs font-bold bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-amber-700 dark:text-amber-300"
    >
      {{ $t('plan_console.signup.plans_unavailable') }}
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <BaseSelect
        :model-value="form.default_signup_plan_id"
        :options="defaultPlanOptions"
        :searchable="false"
        :label="$t('plan_console.signup.default_plan')"
        :hint="$t('plan_console.signup.default_plan_hint')"
        :error="errors.default_signup_plan_id"
        :disabled="selectsDisabled"
        data-testid="platform-default-signup-plan"
        @update:model-value="$emit('update:field', 'default_signup_plan_id', $event)"
      />
      <div class="space-y-1.5">
        <BaseSelect
          :model-value="form.trial_fallback_plan_id"
          :options="fallbackPlanOptions"
          :searchable="false"
          :label="$t('plan_console.signup.fallback_plan')"
          :hint="$t('plan_console.signup.fallback_plan_hint')"
          :error="errors.trial_fallback_plan_id"
          :disabled="selectsDisabled"
          data-testid="platform-trial-fallback-plan"
          @update:model-value="$emit('update:field', 'trial_fallback_plan_id', $event)"
        />
        <p class="text-[11px] font-bold text-slate-600 dark:text-slate-300">{{ effectiveFallbackText }}</p>
      </div>
      <BaseInput
        :model-value="form.signup_daily_cap"
        type="number"
        dir="ltr"
        inputmode="numeric"
        min="1"
        :label="$t('plan_console.signup.daily_cap')"
        :placeholder="$t('plan_console.signup.daily_cap_placeholder')"
        :hint="$t('plan_console.signup.daily_cap_hint')"
        :error="errors.signup_daily_cap"
        :readonly="readonly"
        data-testid="platform-signup-daily-cap"
        @update:model-value="$emit('update:field', 'signup_daily_cap', $event)"
      />
      <BaseSwitch
        :model-value="form.self_signup_enabled"
        :label="$t('plan_console.signup.self_signup_enabled')"
        :description="$t('plan_console.signup.self_signup_enabled_hint')"
        :error="errors.self_signup_enabled"
        :disabled="readonly"
        wrapper-class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800"
        data-testid="platform-self-signup-enabled"
        @update:model-value="$emit('update:field', 'self_signup_enabled', $event)"
      />
    </div>
  </DashboardSectionCard>
</template>
