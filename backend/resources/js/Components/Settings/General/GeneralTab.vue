<template>
  <div class="space-y-6 font-tajawal">
    <!-- 1. Identity Section -->
    <GeneralIdentitySection :form="form" :errors="errors" :can-manage="canManage" @update:field="handleUpdateField" />

    <!-- 2. Localization Section -->
    <GeneralLocalizationSection
      :form="form"
      :errors="errors"
      :can-manage="canManage"
      @update:field="handleUpdateField"
      @reset-field="handleResetField"
    />

    <!-- 3. Legal Information Section -->
    <GeneralLegalSection :form="form" :errors="errors" :can-manage="canManage" @update:field="handleUpdateField" />

    <!-- Section Save Actions Footer -->
    <div
      v-if="canManage"
      class="flex flex-col sm:flex-row items-center justify-between gap-4 p-5 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs"
    >
      <div class="flex items-center gap-2">
        <span
          v-if="isSectionDirty"
          class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800 animate-pulse"
        >
          <span class="w-2 h-2 rounded-full bg-amber-500"></span>
          <span>{{ $t('settings.unsaved_changes') }}</span>
        </span>
      </div>

      <div class="w-full sm:w-auto flex items-center justify-end gap-3">
        <BaseButton
          type="button"
          variant="primary"
          size="md"
          :loading="isSaving"
          @click="handleSave"
          class="w-full sm:w-auto font-black shadow-theme-primary shadow-lg flex items-center justify-center gap-2 min-h-[44px]"
        >
          <Save class="w-4 h-4" />
          <span>{{ $t('settings.save_section') }}</span>
        </BaseButton>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Save } from 'lucide-vue-next';
import BaseButton from '../../Common/BaseButton.vue';
import GeneralIdentitySection from './GeneralIdentitySection.vue';
import GeneralLocalizationSection from './GeneralLocalizationSection.vue';
import GeneralLegalSection from './GeneralLegalSection.vue';

const props = defineProps({
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  isSaving: { type: Boolean, default: false },
  canManage: { type: Boolean, default: true },
  initialForm: { type: Object, default: null },
});

const emit = defineEmits(['save', 'update:field', 'reset-field', 'reset-default']);

const generalFields = [
  'company_name',
  'company_subtitle',
  'company_phone',
  'company_address',
  'currency',
  'timezone',
  'business_day_cutoff',
  'commercial_register',
  'tax_registration_no',
];

const isSectionDirty = computed(() => {
  if (!props.initialForm) return false;
  return generalFields.some((f) => props.form[f] !== props.initialForm[f]);
});

const handleUpdateField = (field, val) => {
  emit('update:field', field, val);
};

const handleResetField = (field) => {
  emit('reset-default', field);
  emit('reset-field', field);
};

const handleSave = () => {
  emit('save', 'general');
};
</script>
