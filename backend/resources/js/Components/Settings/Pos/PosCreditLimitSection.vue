<script setup>
import { HandCoins } from 'lucide-vue-next';
import BaseInput from '../../Form/BaseInput.vue';

// SHOP-03: the credit limit a new customer starts with (empty = no limit). Never applied to existing customers.
const props = defineProps({
  form: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
  canManage: { type: Boolean, default: true },
});

defineEmits(['update:field']);

const fieldError = () => {
  const err = props.errors?.customers_default_credit_limit;
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
        <HandCoins class="w-5 h-5" aria-hidden="true" />
      </div>
      <div class="min-w-0">
        <h2 class="text-base font-black text-slate-900 dark:text-white">{{ $t('settings.credit_policy_title') }}</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('settings.credit_policy_sub') }}</p>
      </div>
    </div>

    <div class="max-w-md">
      <BaseInput
        id="setting-customers_default_credit_limit"
        :model-value="form.customers_default_credit_limit"
        :label="$t('settings.customers_default_credit_limit')"
        :placeholder="$t('settings.customers_default_credit_limit_placeholder')"
        :hint="$t('settings.customers_default_credit_limit_desc')"
        :error="fieldError()"
        :disabled="!canManage"
        :maxlength="13"
        inputmode="decimal"
        autocomplete="off"
        dir="ltr"
        input-class="text-center font-mono"
        data-testid="setting-customers-default-credit-limit"
        @update:model-value="$emit('update:field', 'customers_default_credit_limit', $event)"
      />
    </div>
  </div>
</template>
