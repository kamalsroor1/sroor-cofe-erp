<script setup>
import { computed } from 'vue';
import { Save } from 'lucide-vue-next';
import BaseButton from '../../Common/BaseButton.vue';
import PosReturnWindowSection from './PosReturnWindowSection.vue';
import PosIdleLockSection from './PosIdleLockSection.vue';
import PosShiftRuleSection from './PosShiftRuleSection.vue';

const props = defineProps({
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  isSaving: { type: Boolean, default: false },
  canManage: { type: Boolean, default: true },
  initialForm: { type: Object, default: null },
});

const emit = defineEmits(['save', 'update:field']);

const policyFields = ['returns_max_days', 'pos_idle_lock_minutes'];

const isSectionDirty = computed(() => {
  if (!props.initialForm) return false;
  return policyFields.some((field) => props.form[field] !== props.initialForm[field]);
});
</script>

<template>
  <div class="space-y-6 font-tajawal">
    <PosReturnWindowSection
      :form="form"
      :errors="errors"
      :can-manage="canManage"
      @update:field="(field, val) => emit('update:field', field, val)"
    />
    <PosIdleLockSection
      :form="form"
      :errors="errors"
      :can-manage="canManage"
      @update:field="(field, val) => emit('update:field', field, val)"
    />
    <PosShiftRuleSection />

    <div
      v-if="canManage"
      class="flex flex-col sm:flex-row items-center justify-between gap-4 p-5 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs"
    >
      <span
        v-if="isSectionDirty"
        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800"
      >
        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
        <span>{{ $t('settings.unsaved_changes') }}</span>
      </span>
      <span v-else></span>

      <BaseButton
        type="button"
        variant="primary"
        size="md"
        :loading="isSaving"
        data-testid="settings-pos-save"
        class="w-full sm:w-auto font-black shadow-theme-primary shadow-lg flex items-center justify-center gap-2 min-h-[44px]"
        @click="emit('save', 'pos')"
      >
        <Save class="w-4 h-4" />
        <span>{{ $t('settings.save_section') }}</span>
      </BaseButton>
    </div>
  </div>
</template>
