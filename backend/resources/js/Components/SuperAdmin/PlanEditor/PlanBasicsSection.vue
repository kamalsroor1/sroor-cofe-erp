<template>
  <div class="space-y-4">
    <BaseInput
      :model-value="form.name"
      :label="$t('super.plan_name_label')"
      :error="errors.name"
      required
      @update:model-value="$emit('update:field', 'name', $event)"
    />
    <BaseTextarea
      :model-value="form.description"
      :label="$t('super.plan_description_label')"
      :error="errors.description"
      :rows="3"
      @update:model-value="$emit('update:field', 'description', $event)"
    />
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <BaseInput
        :model-value="form.sort_order"
        :label="$t('super.plan_sort_order_label')"
        :error="errors.sort_order"
        type="number"
        inputmode="numeric"
        min="0"
        input-class="font-mono text-start"
        @update:model-value="$emit('update:field', 'sort_order', $event)"
      />
      <BaseInput
        :model-value="form.trial_days"
        :label="$t('super.plan_trial_days_label')"
        :error="errors.trial_days"
        type="number"
        inputmode="numeric"
        min="0"
        input-class="font-mono text-start"
        @update:model-value="$emit('update:field', 'trial_days', $event)"
      />
    </div>
    <BaseSwitch
      :model-value="form.is_free"
      :label="$t('plan_console.free_plan_label')"
      :description="$t('plan_console.free_plan_hint')"
      :error="errors.is_free"
      wrapper-class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800"
      data-testid="plan-is-free"
      @update:model-value="$emit('update:field', 'is_free', $event)"
    />
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <BaseSwitch
        v-for="flag in flags"
        :key="flag.field"
        :model-value="form[flag.field]"
        :label="$t(flag.label)"
        :description="$t(flag.hint)"
        :error="errors[flag.field]"
        wrapper-class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800"
        @update:model-value="$emit('update:field', flag.field, $event)"
      />
    </div>
  </div>
</template>

<script setup>
import BaseInput from '../../Form/BaseInput.vue';
import BaseTextarea from '../../Form/BaseTextarea.vue';
import BaseSwitch from '../../Form/BaseSwitch.vue';

defineProps({
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
});

defineEmits(['update:field']);

const flags = [
  { field: 'is_active', label: 'super.plan_active_checkbox', hint: 'super.plan_active_hint' },
  { field: 'is_public', label: 'super.plan_public_label', hint: 'super.plan_public_hint' },
  { field: 'is_popular', label: 'super.popular_plan_checkbox', hint: 'super.plan_popular_hint' },
];
</script>
