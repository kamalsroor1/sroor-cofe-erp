<template>
  <div class="space-y-5">
    <p
      class="text-[11px] font-medium text-slate-600 dark:text-slate-300 p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 leading-relaxed"
    >
      {{ $t('super.plan_price_schedule_hint') }}
    </p>

    <fieldset v-for="group in groups" :key="group.title" class="space-y-2">
      <legend class="text-xs font-black text-slate-700 dark:text-slate-200">{{ $t(group.title) }}</legend>
      <p v-if="group.hint" class="text-[11px] font-medium text-slate-500 dark:text-slate-400">
        {{ $t(group.hint) }}
      </p>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <PlanPriceInput
          v-for="field in group.fields"
          :key="field.key"
          :model-value="form[field.key]"
          :label="$t(field.label)"
          :error="errors[field.key]"
          :required="field.required"
          :tax-rate="taxRate"
          @update:model-value="$emit('update:field', field.key, $event)"
        />
      </div>
    </fieldset>
  </div>
</template>

<script setup>
import PlanPriceInput from './PlanPriceInput.vue';

defineProps({
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  taxRate: { type: String, default: '0.000' },
});

defineEmits(['update:field']);

const groups = [
  {
    title: 'super.plan_list_prices_title',
    hint: null,
    fields: [
      { key: 'price_monthly', label: 'super.monthly_price_label', required: true },
      { key: 'price_yearly', label: 'super.yearly_price_label', required: true },
    ],
  },
  {
    title: 'super.plan_founder_prices_title',
    hint: 'super.plan_founder_prices_hint',
    fields: [
      { key: 'founder_price_monthly', label: 'super.plan_founder_monthly_label' },
      { key: 'founder_price_yearly', label: 'super.plan_founder_yearly_label' },
    ],
  },
  {
    title: 'super.plan_transition_prices_title',
    hint: 'super.plan_transition_prices_hint',
    fields: [
      { key: 'founder_transition_price_monthly', label: 'super.plan_transition_monthly_label' },
      { key: 'founder_transition_price_yearly', label: 'super.plan_transition_yearly_label' },
    ],
  },
];
</script>
