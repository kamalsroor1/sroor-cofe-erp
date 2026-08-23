<template>
  <div class="space-y-6 max-w-6xl mx-auto font-tajawal pb-12">
    <!-- Page Header (Master Level) -->
    <PageHeader
      :title="currentSectionTitle"
      :subtitle="currentSectionSubtitle"
      icon="⚙️"
    >
      <template #actions>
        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
          <button
            v-if="selectedSection && isMobileView"
            type="button"
            @click="selectedSection = null"
            class="min-h-[38px] px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 flex items-center gap-1.5 text-xs font-bold active:scale-95"
          >
            <ArrowRight class="w-4 h-4" />
            <span>{{ $t('settings.back_to_hub') }}</span>
          </button>

          <BaseButton
            type="button"
            variant="primary"
            size="md"
            :loading="isSaving"
            @click="saveSettings"
            class="font-black shadow-theme-primary shadow-lg flex items-center gap-2"
          >
            <Save class="w-4 h-4" />
            <span>{{ $t('profile.save_changes') }}</span>
          </BaseButton>
        </div>
      </template>
    </PageHeader>

    <!-- Main Navigation & Sub-Pages Layout -->
    <div v-if="!isLoading">
      <!-- 📱 Mobile Hub View -->
      <SettingsMobileHub
        v-if="!selectedSection && isMobileView"
        :sections="sections"
        @select-section="selectedSection = $event"
      />

      <!-- 💻 Desktop Split View & Mobile Drill-Down Active Page -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Desktop Sidebar Menu -->
        <div class="hidden lg:block lg:col-span-4">
          <SettingsNavigationSidebar
            :sections="sections"
            :selected-section="selectedSection"
            @select-section="selectedSection = $event"
          />
        </div>

        <!-- Settings Sub-Page Content Area -->
        <div class="lg:col-span-8">
          <SettingsBrandingSection
            v-if="selectedSection === 'branding'"
            :form="form"
            @update:field="updateFormField"
          />

          <SettingsAppearanceSection
            v-else-if="selectedSection === 'appearance'"
            :theme-color="form.system_theme_color"
            :custom-color="customHexColor"
            :color-palettes="colorPalettes"
            :is-dark="appConfigStore.isDark"
            @select-color="selectThemeColor"
            @update:custom-color="onCustomColorChange"
            @pick-screen="pickFromScreen"
            @set-theme="appConfigStore.setTheme"
          />

          <SettingsPrintingSection
            v-else-if="selectedSection === 'printing'"
            :form="form"
            @update:field="updateFormField"
          />

          <SettingsTelegramSection
            v-else-if="selectedSection === 'telegram'"
            :form="form"
            :is-testing="isTestingTelegram"
            @update:field="updateFormField"
            @test-telegram="sendTestTelegram"
          />

          <SettingsUnitsSection
            v-else-if="selectedSection === 'units'"
            :active-units-list="activeUnitsList"
            :default-presets="defaultPresets"
            :new-unit-input="newUnitInput"
            @update:new-unit="newUnitInput = $event"
            @add-unit="addCustomUnit"
            @add-preset="addPresetUnit"
            @remove-unit="removeUnit"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Save, ArrowRight } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import SettingsNavigationSidebar from '../../Components/Settings/SettingsNavigationSidebar.vue';
import SettingsMobileHub from '../../Components/Settings/SettingsMobileHub.vue';
import SettingsBrandingSection from '../../Components/Settings/SettingsBrandingSection.vue';
import SettingsAppearanceSection from '../../Components/Settings/SettingsAppearanceSection.vue';
import SettingsPrintingSection from '../../Components/Settings/SettingsPrintingSection.vue';
import SettingsTelegramSection from '../../Components/Settings/SettingsTelegramSection.vue';
import SettingsUnitsSection from '../../Components/Settings/SettingsUnitsSection.vue';
import { useSettings } from '../../Composables/useSettings';

const {
  appConfigStore,
  isMobileView,
  selectedSection,
  isLoading,
  isSaving,
  isTestingTelegram,
  colorPalettes,
  sections,
  currentSectionTitle,
  currentSectionSubtitle,
  form,
  newUnitInput,
  defaultPresets,
  activeUnitsList,
  customHexColor,
  addCustomUnit,
  addPresetUnit,
  removeUnit,
  onCustomColorChange,
  pickFromScreen,
  selectThemeColor,
  updateFormField,
  saveSettings,
  sendTestTelegram,
} = useSettings();
</script>
