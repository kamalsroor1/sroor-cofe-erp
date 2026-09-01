<template>
  <div class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 shadow-sm dark:shadow-xl space-y-6 font-tajawal">
    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-theme-light border border-theme-border text-theme-primary flex items-center justify-center">
          <Building2 class="w-5 h-5" />
        </div>
        <div>
          <h2 class="text-base font-black text-slate-900 dark:text-white">{{ $t('settings.branding_section_title') }}</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('settings.branding_section_sub') }}</p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <BaseInput
        :model-value="form.company_name"
        @update:model-value="$emit('update:field', 'company_name', $event)"
        :label="$t('settings.company_name')"
        :required="true"
        :placeholder="$t('settings.company_name_placeholder')"
      />

      <BaseInput
        :model-value="form.company_subtitle"
        @update:model-value="$emit('update:field', 'company_subtitle', $event)"
        :label="$t('settings.company_subtitle')"
        :placeholder="$t('settings.company_subtitle_placeholder')"
      />

      <BaseInput
        :model-value="form.company_phone"
        @update:model-value="$emit('update:field', 'company_phone', $event)"
        :label="$t('settings.company_phone')"
        :placeholder="$t('settings.company_phone_placeholder')"
        dir="ltr"
      />

      <BaseInput
        :model-value="form.company_address"
        @update:model-value="$emit('update:field', 'company_address', $event)"
        :label="$t('settings.company_address')"
        :placeholder="$t('settings.company_address_placeholder')"
      />
    </div>
  </div>
</template>

<script setup>
import { Building2 } from 'lucide-vue-next';
import BaseInput from '../Form/BaseInput.vue';

defineProps({
  form: { type: Object, default: () => ({}) },
});

defineEmits(['update:field']);
</script>
