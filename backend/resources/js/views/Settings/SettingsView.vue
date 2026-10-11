<template>
  <div class="space-y-6 max-w-6xl mx-auto font-tajawal pb-12">
    <!-- Shimmer Skeleton Loading State -->
    <SettingsSkeletonLoader v-if="isLoading" :is-mobile="isMobileView" :has-selected-tab="!!selectedTab" />

    <!-- Load Error State with Retry Button (Must Fix #6) -->
    <div
      v-else-if="loadError"
      class="p-8 rounded-3xl bg-white dark:bg-slate-900 border border-rose-200 dark:border-rose-900/50 shadow-sm text-center space-y-4"
    >
      <div
        class="w-12 h-12 rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-500 flex items-center justify-center mx-auto"
      >
        <AlertCircle class="w-6 h-6" />
      </div>
      <div>
        <h2 class="text-base font-black text-slate-900 dark:text-white">{{ $t('settings.load_error_title') }}</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-md mx-auto">
          {{ loadErrorMessage || $t('settings.load_error_desc') }}
        </p>
      </div>
      <BaseButton type="button" variant="primary" size="md" @click="fetchSettings" class="min-h-[44px]">
        {{ $t('settings.retry_loading') }}
      </BaseButton>
    </div>

    <!-- Main Settings Shell Content -->
    <div v-else class="space-y-6">
      <SettingsShellHeader
        :title="currentTitle"
        :subtitle="currentSubtitle"
        :is-mobile="isMobileView"
        :has-selected-tab="!!selectedTab"
        :show-advanced="showAdvanced"
        :has-unsaved-changes="hasUnsavedChanges"
        :is-saving="isSaving"
        :can-manage="canManage && !!initialForm"
        v-model:search-query="searchQuery"
        :search-results="searchResults"
        @back="backToHub"
        @toggle-advanced="toggleAdvanced"
        @save="saveSettings"
        @select-search-result="selectSetting"
      />

      <!-- Mobile Hub Cards Grid -->
      <SettingsTabsNav
        v-if="isMobileView && !selectedTab"
        :selected-tab="selectedTab"
        :is-mobile="isMobileView"
        @select-tab="selectTab"
      />

      <!-- Desktop Split Layout (Sidebar 4 cols + Main Content 8 cols) or Mobile Drill-Down -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <aside class="hidden lg:block lg:col-span-4">
          <SettingsTabsNav :selected-tab="selectedTab" :is-mobile="false" @select-tab="selectTab" />
        </aside>

        <main class="lg:col-span-8">
          <SettingsTabContent
            :selected-tab="selectedTab || 'general'"
            :form="form"
            :errors="errors"
            :can-manage="canManage"
            :is-saving="isSaving"
            :initial-form="initialForm"
            :color-palettes="colorPalettes"
            :logo-light-url="logo_light_url"
            :logo-dark-url="logo_dark_url"
            :logo-light-error="logoLightError"
            :logo-dark-error="logoDarkError"
            :is-uploading-light="isUploadingLogoLight"
            :is-uploading-dark="isUploadingLogoDark"
            :active-units-list="activeUnitsList"
            :default-presets="defaultPresets"
            :new-unit-input="newUnitInput"
            :is-testing-telegram="isTestingTelegram"
            @update:field="updateFormField"
            @update:custom-color="onCustomColorChange"
            @upload-logo="uploadLogo"
            @remove-logo="(variant) => removeLogo(variant)"
            @select-color="selectThemeColor"
            @update:new-unit="newUnitInput = $event"
            @add-unit="addCustomUnit"
            @add-preset="addPresetUnit"
            @remove-unit="removeUnit"
            @reorder-units="reorderUnits"
            @test-telegram="sendTestTelegram"
            @save="saveSettings"
            @reset-default="resetFieldToDefault"
            @set-theme="appConfigStore.setTheme"
          />
        </main>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { AlertCircle } from 'lucide-vue-next';
import BaseButton from '../../Components/Common/BaseButton.vue';
import { useSettings } from '../../Composables/useSettings';
import { useSettingsShell } from '../../Composables/useSettingsShell';
import { useSettingsSearch } from '../../Composables/useSettingsSearch';

import SettingsShellHeader from '../../Components/Settings/Shell/SettingsShellHeader.vue';
import SettingsTabsNav from '../../Components/Settings/Shell/SettingsTabsNav.vue';
import SettingsSkeletonLoader from '../../Components/Settings/Shell/SettingsSkeletonLoader.vue';
import SettingsTabContent from '../../Components/Settings/Shell/SettingsTabContent.vue';

const {
  form,
  initialForm,
  errors,
  isLoading,
  loadError,
  loadErrorMessage,
  fetchSettings,
  isSaving,
  isTestingTelegram,
  colorPalettes,
  newUnitInput,
  defaultPresets,
  activeUnitsList,
  addCustomUnit,
  addPresetUnit,
  removeUnit,
  reorderUnits,
  onCustomColorChange,
  selectThemeColor,
  updateFormField,
  resetFieldToDefault,
  sendTestTelegram,
  logo_light_url,
  logo_dark_url,
  logoLightError,
  logoDarkError,
  isUploadingLogoLight,
  isUploadingLogoDark,
  uploadLogo,
  removeLogo,
  saveSettings,
  appConfigStore,
} = useSettings();

const {
  selectedTab,
  selectTab,
  backToHub,
  isMobileView,
  showAdvanced,
  toggleAdvanced,
  canManage,
  hasUnsavedChanges,
  currentTitle,
  currentSubtitle,
} = useSettingsShell({ form, initialForm });

const { searchQuery, searchResults, selectSetting, handleDeepLink } = useSettingsSearch({
  onSelectTab: selectTab,
  isLoading,
});

onMounted(handleDeepLink);
</script>
