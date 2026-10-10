<template>
  <div class="space-y-6 font-tajawal">
    <!-- Read-Only Banner when user lacks settings.manage permission -->
    <div
      v-if="!resolvedCanManage"
      class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 flex items-center gap-3 text-xs font-bold"
    >
      <ShieldAlert class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0" />
      <span>{{ $t('settings.read_only_notice') }}</span>
    </div>

    <!-- 1. Identity Section -->
    <GeneralIdentitySection
      :form="resolvedForm"
      :errors="resolvedErrors"
      :can-manage="resolvedCanManage"
      @update:field="handleUpdateField"
    />

    <!-- 2. Localization Section -->
    <GeneralLocalizationSection
      :form="resolvedForm"
      :errors="resolvedErrors"
      :can-manage="resolvedCanManage"
      @update:field="handleUpdateField"
      @reset-field="handleResetField"
    />

    <!-- 3. Legal Information Section -->
    <GeneralLegalSection
      :form="resolvedForm"
      :errors="resolvedErrors"
      :can-manage="resolvedCanManage"
      @update:field="handleUpdateField"
    />

    <!-- Section Save Actions Footer -->
    <div
      v-if="resolvedCanManage"
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
          :loading="resolvedIsSaving"
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
import { Save, ShieldAlert } from 'lucide-vue-next';
import BaseButton from '../../Common/BaseButton.vue';
import GeneralIdentitySection from './GeneralIdentitySection.vue';
import GeneralLocalizationSection from './GeneralLocalizationSection.vue';
import GeneralLegalSection from './GeneralLegalSection.vue';
import { useSettings } from '../../../Composables/useSettings';

const props = defineProps({
  form: { type: Object, default: null },
  errors: { type: Object, default: null },
  isSaving: { type: Boolean, default: null },
  canManage: { type: Boolean, default: null },
  initialForm: { type: Object, default: null },
});

const emit = defineEmits(['save', 'update:field', 'reset-field', 'reset-default']);

// Fallback to composable if props not supplied
const settings = useSettings();

const resolvedForm = computed(() => props.form || settings.form.value);
const resolvedErrors = computed(() => props.errors || settings.errors.value);
const resolvedIsSaving = computed(() => (props.isSaving !== null ? props.isSaving : settings.isSaving.value));
const resolvedCanManage = computed(() => (props.canManage !== null ? props.canManage : settings.canManage.value));
const resolvedInitialForm = computed(() => props.initialForm || settings.initialForm.value);

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
  if (!resolvedInitialForm.value) return false;
  return generalFields.some((f) => resolvedForm.value[f] !== resolvedInitialForm.value[f]);
});

const handleUpdateField = (field, val) => {
  if (props.form) {
    emit('update:field', field, val);
  } else {
    settings.updateFormField(field, val);
  }
};

const handleResetField = (field) => {
  if (props.form) {
    emit('reset-default', field);
    emit('reset-field', field);
  } else {
    settings.resetFieldToDefault(field);
  }
};

const handleSave = () => {
  if (props.isSaving !== null) {
    emit('save', 'general');
  } else {
    settings.saveSettings('general');
  }
};
</script>
