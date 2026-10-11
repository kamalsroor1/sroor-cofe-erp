<template>
  <div class="space-y-6 font-tajawal">
    <!-- 1. Units Section -->
    <InventoryUnitsSection :form="form" :errors="errors" :can-manage="canManage" @update:field="handleUpdateField" />

    <!-- 2. Threshold Section -->
    <InventoryThresholdSection
      :form="form"
      :errors="errors"
      :can-manage="canManage"
      @update:field="handleUpdateField"
      @reset-field="handleResetField"
    />

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
import InventoryUnitsSection from './InventoryUnitsSection.vue';
import InventoryThresholdSection from './InventoryThresholdSection.vue';

const props = defineProps({
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  isSaving: { type: Boolean, default: false },
  canManage: { type: Boolean, default: true },
  initialForm: { type: Object, default: null },
  activeUnitsList: { type: Array, default: () => [] },
  defaultPresets: { type: Array, default: () => [] },
  newUnitInput: { type: String, default: '' },
});

const emit = defineEmits([
  'save',
  'update:field',
  'reset-field',
  'reset-default',
  'update:new-unit',
  'add-unit',
  'add-preset',
  'remove-unit',
  'reorder-units',
]);

const inventoryFields = ['inventory_units', 'low_stock_default_threshold'];

const isSectionDirty = computed(() => {
  if (!props.initialForm) return false;
  return inventoryFields.some((f) => props.form[f] !== props.initialForm[f]);
});

const handleUpdateField = (field, val) => {
  if (field === 'inventory_units') {
    const list = val
      ? val
          .split(',')
          .map((u) => u.trim())
          .filter(Boolean)
      : [];
    emit('reorder-units', list);
  }
  emit('update:field', field, val);
};

const handleResetField = (field) => {
  emit('reset-default', field);
  emit('reset-field', field);
};

const handleSave = () => {
  emit('save', 'inventory');
};
</script>
