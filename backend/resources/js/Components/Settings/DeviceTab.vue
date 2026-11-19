<template>
  <div class="space-y-6 font-tajawal">
    <!-- Appearance & Theme Controls -->
    <SettingsAppearanceSection
      :is-dark="appConfigStore.isDark"
      :can-manage="canManage"
      @set-theme="appConfigStore.setTheme"
    />

    <SettingsLegalLinksSection />
  </div>
</template>

<script setup>
import SettingsAppearanceSection from './SettingsAppearanceSection.vue';
import SettingsLegalLinksSection from './SettingsLegalLinksSection.vue';
import { useAppConfigStore } from '../../stores/appConfig';

defineProps({
  form: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
  canManage: { type: Boolean, default: true },
  isSaving: { type: Boolean, default: false },
  initialForm: { type: Object, default: null },
});

const appConfigStore = useAppConfigStore();
</script>
