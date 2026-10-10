<template>
  <div class="space-y-6 max-w-6xl mx-auto font-tajawal pb-12">
    <!-- Shimmer Skeleton Loading State -->
    <SettingsSkeletonLoader v-if="isLoading" :is-mobile="isMobileView" :has-selected-tab="!!selectedTab" />

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
        :can-manage="canManage"
        v-model:search-query="searchQuery"
        :search-results="searchResults"
        @back="backToHub"
        @toggle-advanced="toggleAdvanced"
        @save="saveSection(selectedTab || 'general')"
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
          <GeneralTab
            v-if="selectedTab === 'general'"
            :form="form"
            :errors="errors"
            :is-saving="isSaving"
            :can-manage="canManage"
            :initial-form="initialForm"
            @update:field="updateFormField"
            @save="saveSection('general')"
            @reset-default="resetFieldToDefault"
          />
          <BrandingTab
            v-else-if="selectedTab === 'branding'"
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
            @update:field="updateFormField"
            @upload-logo="uploadLogo"
            @remove-logo="removeLogo"
            @select-color="selectThemeColor"
            @save="saveSection('branding')"
          />
          <SettingsPrintingSection
            v-else-if="selectedTab === 'printing'"
            :form="form"
            :errors="errors"
            :can-manage="canManage"
            :is-saving="isSaving"
            :initial-form="initialForm"
            @update:field="updateFormField"
            @save="saveSection('printing')"
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
            @update:new-unit="newUnitInput = $event"
            @add-unit="addCustomUnit"
            @add-preset="addPresetUnit"
            @remove-unit="removeUnit"
            @reorder-units="reorderUnits"
            @update:field="updateFormField"
            @save="saveSection('inventory')"
            @reset-default="resetFieldToDefault"
          />
          <SettingsTelegramSection
            v-else-if="selectedTab === 'notifications'"
            :form="form"
            :errors="errors"
            :can-manage="canManage"
            :is-saving="isSaving"
            :is-testing="isTestingTelegram"
            :initial-form="initialForm"
            @update:field="updateFormField"
            @test-telegram="sendTestTelegram"
            @save="saveSection('notifications')"
          />
          <DeviceTab
            v-else-if="selectedTab === 'device'"
            :form="form"
            :errors="errors"
            :can-manage="canManage"
            :is-saving="isSaving"
            :initial-form="initialForm"
            :custom-hex-color="customHexColor"
            :color-palettes="colorPalettes"
            @update:field="updateFormField"
            @select-color="selectThemeColor"
            @update:custom-color="onCustomColorChange"
            @pick-screen="pickFromScreen"
            @send-backup-telegram="sendTestTelegram"
            @save="saveSection('device')"
          />
        </main>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useSettings } from '../../Composables/useSettings';
import { useSettingsShell } from '../../Composables/useSettingsShell';
import { useSettingsSearch } from '../../Composables/useSettingsSearch';

import SettingsShellHeader from '../../Components/Settings/Shell/SettingsShellHeader.vue';
import SettingsTabsNav from '../../Components/Settings/Shell/SettingsTabsNav.vue';
import SettingsSkeletonLoader from '../../Components/Settings/Shell/SettingsSkeletonLoader.vue';
import GeneralTab from '../../Components/Settings/General/GeneralTab.vue';
import BrandingTab from '../../Components/Settings/BrandingTab.vue';
import SettingsPrintingSection from '../../Components/Settings/SettingsPrintingSection.vue';
import InventoryTab from '../../Components/Settings/Inventory/InventoryTab.vue';
import SettingsTelegramSection from '../../Components/Settings/SettingsTelegramSection.vue';
import DeviceTab from '../../Components/Settings/DeviceTab.vue';

const {
  form,
  initialForm,
  errors,
  isLoading,
  isSaving,
  isTestingTelegram,
  colorPalettes,
  newUnitInput,
  defaultPresets,
  activeUnitsList,
  customHexColor,
  addCustomUnit,
  addPresetUnit,
  removeUnit,
  reorderUnits,
  onCustomColorChange,
  pickFromScreen,
  selectThemeColor,
  updateFormField,
  saveSection,
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

const { searchQuery, searchResults, selectSetting, handleDeepLink } = useSettingsSearch({ onSelectTab: selectTab });

onMounted(handleDeepLink);
</script>
