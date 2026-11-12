<script setup>
import { RotateCcw } from 'lucide-vue-next';
import BaseInput from '../../Form/BaseInput.vue';

const props = defineProps({
  form: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
  canManage: { type: Boolean, default: true },
});

defineEmits(['update:field']);

const fieldError = () => {
  const err = props.errors?.returns_max_days;
  if (!err) return null;
  return Array.isArray(err) ? err[0] : err;
};
</script>

<template>
  <div
    class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 shadow-xs dark:shadow-xl space-y-5 font-tajawal transition-colors"
  >
    <div class="flex items-center gap-3 border-b border-slate-200 dark:border-slate-800 pb-4">
      <div
        class="w-10 h-10 rounded-2xl bg-theme-light border border-theme-border text-theme-primary flex items-center justify-center shrink-0"
      >
        <RotateCcw class="w-5 h-5" aria-hidden="true" />
      </div>
      <div class="min-w-0">
        <h2 class="text-base font-black text-slate-900 dark:text-white">{{ $t('settings.returns_policy_title') }}</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('settings.returns_policy_sub') }}</p>
      </div>
    </div>

    <div class="max-w-md">
      <BaseInput
        id="setting-returns_max_days"
        :model-value="form.returns_max_days"
        :label="$t('settings.returns_max_days')"
        :placeholder="$t('settings.returns_max_days_placeholder')"
        :hint="$t('settings.returns_max_days_desc')"
        :error="fieldError()"
        :disabled="!canManage"
        :maxlength="4"
        inputmode="numeric"
        autocomplete="off"
        dir="ltr"
        input-class="text-center font-mono"
        data-testid="setting-returns-max-days"
        @update:model-value="$emit('update:field', 'returns_max_days', $event)"
      />
    </div>
  </div>
</template>
