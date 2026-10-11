<template>
  <div>
    <GeneralTab
      v-if="selectedTab === 'general'"
      :form="form"
      :errors="errors"
      :is-saving="isSaving"
      :can-manage="canManage"
      :initial-form="initialForm"
      @update:field="(field, val) => $emit('update:field', field, val)"
      @save="$emit('save', 'general')"
      @reset-default="$emit('reset-default', $event)"
    />
    <BrandingTab
      v-else-if="selectedTab === 'branding'"
      :form="form"
      :errors="errors"
      :can-manage="canManage"
      :is-saving="isSaving"
      :initial-form="initialForm"
      :color-palettes="colorPalettes"
      :logo-light-url="logoLightUrl"
      :logo-dark-url="logoDarkUrl"
      :logo-light-error="logoLightError"
      :logo-dark-error="logoDarkError"
      :is-uploading-light="isUploadingLight"
      :is-uploading-dark="isUploadingDark"
      @update:field="(field, val) => $emit('update:field', field, val)"
      @update:custom-color="$emit('update:custom-color', $event)"
      @upload-logo="(variant, file) => $emit('upload-logo', variant, file)"
      @remove-logo="(variant) => $emit('remove-logo', variant)"
      @select-color="$emit('select-color', $event)"
      @save="$emit('save', 'branding')"
    />
    <SettingsPrintingSection
      v-else-if="selectedTab === 'printing'"
      :form="form"
      :errors="errors"
      :can-manage="canManage"
      :is-saving="isSaving"
      :initial-form="initialForm"
      @update:field="(field, val) => $emit('update:field', field, val)"
      @save="$emit('save', 'printing')"
    />
    <InventoryTab
      v-else-if="selectedTab === 'inventory'"
      :form="form"
      :errors="errors"
      :is-saving="isSaving"
      :can-manage="canManage"
      :initial-form="initialForm"
      :active-units-list="activeUnitsList"
      :default-presets="defaultPresets"
      :new-unit-input="newUnitInput"
      @update:new-unit="$emit('update:new-unit', $event)"
      @add-unit="$emit('add-unit')"
      @add-preset="$emit('add-preset', $event)"
      @remove-unit="$emit('remove-unit', $event)"
      @reorder-units="$emit('reorder-units', $event)"
      @update:field="(field, val) => $emit('update:field', field, val)"
      @save="$emit('save', 'inventory')"
      @reset-default="$emit('reset-default', $event)"
    />
    <SettingsTelegramSection
      v-else-if="selectedTab === 'notifications'"
      :form="form"
      :errors="errors"
      :can-manage="canManage"
      :is-saving="isSaving"
      :is-testing="isTestingTelegram"
      :initial-form="initialForm"
      @update:field="(field, val) => $emit('update:field', field, val)"
      @test-telegram="$emit('test-telegram')"
      @save="$emit('save', 'notifications')"
    />
    <DeviceTab
      v-else-if="selectedTab === 'device'"
      :form="form"
      :errors="errors"
      :can-manage="canManage"
      :is-saving="isSaving"
      :initial-form="initialForm"
      @set-theme="$emit('set-theme', $event)"
    />
  </div>
</template>

<script setup>
import GeneralTab from '../General/GeneralTab.vue';
import BrandingTab from '../BrandingTab.vue';
import SettingsPrintingSection from '../SettingsPrintingSection.vue';
import InventoryTab from '../Inventory/InventoryTab.vue';
import SettingsTelegramSection from '../SettingsTelegramSection.vue';
import DeviceTab from '../DeviceTab.vue';

defineProps({
  selectedTab: { type: String, default: 'general' },
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  canManage: { type: Boolean, default: true },
  isSaving: { type: Boolean, default: false },
  initialForm: { type: Object, default: null },
  colorPalettes: { type: Array, default: () => [] },
  logoLightUrl: { type: String, default: null },
  logoDarkUrl: { type: String, default: null },
  logoLightError: { type: String, default: null },
  logoDarkError: { type: String, default: null },
  isUploadingLight: { type: Boolean, default: false },
  isUploadingDark: { type: Boolean, default: false },
  activeUnitsList: { type: Array, default: () => [] },
  defaultPresets: { type: Array, default: () => [] },
  newUnitInput: { type: String, default: '' },
  isTestingTelegram: { type: Boolean, default: false },
});

defineEmits([
  'update:field',
  'update:custom-color',
  'upload-logo',
  'remove-logo',
  'select-color',
  'save',
  'reset-default',
  'update:new-unit',
  'add-unit',
  'add-preset',
  'remove-unit',
  'reorder-units',
  'test-telegram',
  'set-theme',
]);
</script>
