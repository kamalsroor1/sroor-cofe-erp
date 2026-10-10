<template>
  <div
    class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 shadow-xs dark:shadow-xl space-y-6 font-tajawal transition-colors"
  >
    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
      <div class="flex items-center gap-3">
        <div
          class="w-10 h-10 rounded-2xl bg-theme-light border border-theme-border text-theme-primary flex items-center justify-center shrink-0"
        >
          <Scale class="w-5 h-5" />
        </div>
        <div>
          <h2 class="text-base font-black text-slate-900 dark:text-white">
            {{ $t('settings.legal_section_title') }}
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            {{ $t('settings.legal_section_sub') }}
          </p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <div class="w-full">
        <BaseInput
          id="setting-commercial_register"
          :model-value="form.commercial_register"
          @update:model-value="$emit('update:field', 'commercial_register', $event)"
          :label="$t('settings.commercial_register')"
          :placeholder="$t('settings.commercial_register_placeholder')"
          :disabled="!canManage"
          :error="fieldError('commercial_register')"
          maxlength="50"
        />
      </div>

      <div class="w-full">
        <BaseInput
          id="setting-tax_registration_no"
          :model-value="form.tax_registration_no"
          @update:model-value="$emit('update:field', 'tax_registration_no', $event)"
          :label="$t('settings.tax_registration_no')"
          :placeholder="$t('settings.tax_registration_no_placeholder')"
          :disabled="!canManage"
          :error="fieldError('tax_registration_no')"
          maxlength="50"
        />
      </div>
    </div>

    <div
      class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 flex items-start gap-2.5 text-xs text-slate-600 dark:text-slate-300 font-medium"
    >
      <FileText class="w-4 h-4 text-theme-primary shrink-0 mt-0.5" />
      <span>{{ $t('settings.legal_invoice_hint') }}</span>
    </div>
  </div>
</template>

<script setup>
import { Scale, FileText } from 'lucide-vue-next';
import BaseInput from '../../Form/BaseInput.vue';

const props = defineProps({
  form: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
  canManage: { type: Boolean, default: true },
});

defineEmits(['update:field']);

const fieldError = (key) => {
  const err = props.errors?.[key];
  if (!err) return null;
  return Array.isArray(err) ? err[0] : err;
};
</script>
