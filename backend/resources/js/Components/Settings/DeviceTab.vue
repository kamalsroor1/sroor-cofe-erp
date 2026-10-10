<template>
  <div class="space-y-6 font-tajawal">
    <!-- Appearance & Theme Controls -->
    <SettingsAppearanceSection
      :theme-color="form.system_theme_color || 'amber'"
      :custom-color="customHexColor"
      :color-palettes="colorPalettes"
      :is-dark="appConfigStore.isDark"
      :can-manage="canManage"
      @select-color="selectThemeColor"
      @update:custom-color="onCustomColorChange"
      @pick-screen="pickFromScreen"
      @set-theme="appConfigStore.setTheme"
    />

    <!-- Database & Cloud Backup Controls -->
    <BackupTab @send-backup-telegram="$emit('send-backup-telegram')" />

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
          @click="$emit('save', 'device')"
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
import SettingsAppearanceSection from './SettingsAppearanceSection.vue';
import BackupTab from './BackupTab.vue';
import BaseButton from '../Common/BaseButton.vue';
import { useAppConfigStore } from '../../stores/appConfig';

const props = defineProps({
  form: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
  canManage: { type: Boolean, default: true },
  isSaving: { type: Boolean, default: false },
  customHexColor: { type: String, default: '#10b981' },
  colorPalettes: { type: Array, default: () => [] },
  initialForm: { type: Object, default: null },
});

const emit = defineEmits([
  'update:field',
  'save',
  'select-color',
  'update:custom-color',
  'pick-screen',
  'send-backup-telegram',
]);

const appConfigStore = useAppConfigStore();

const isSectionDirty = computed(() => {
  if (!props.initialForm) return false;
  return props.form.system_theme_color !== props.initialForm.system_theme_color;
});

const selectThemeColor = (colorId) => {
  emit('select-color', colorId);
  emit('update:field', 'system_theme_color', colorId);
};

const onCustomColorChange = (hex) => {
  emit('update:custom-color', hex);
  emit('update:field', 'system_theme_color', hex);
};

const pickFromScreen = () => {
  emit('pick-screen');
};
</script>
