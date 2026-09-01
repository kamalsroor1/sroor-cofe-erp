<template>
  <div class="space-y-6 max-w-5xl mx-auto font-tajawal">
    <!-- Page Header -->
    <PageHeader
      :title="$t('super.units_page_title')"
      :subtitle="$t('super.units_page_subtitle')"
      :icon="Scale"
    >
      <template #actions>
        <div class="flex items-center gap-3">
          <router-link
            to="/super-admin"
            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 font-bold text-xs rounded-xl shadow-xs flex items-center gap-2 transition active:scale-95"
          >
            <Crown class="w-4 h-4 text-purple-400" />
            <span>{{ $t('super.dashboard') }}</span>
          </router-link>

          <BaseButton
            type="button"
            variant="primary"
            size="md"
            :loading="isSaving"
            @click="saveUnits"
            class="bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-400 hover:to-indigo-500 text-white font-black shadow-lg shadow-purple-500/20 flex items-center gap-1.5"
          >
            <Save class="w-4 h-4" />
            <span>{{ isSaving ? $t('common.saving') : $t('common.save') }}</span>
          </BaseButton>
        </div>
      </template>
    </PageHeader>

    <!-- Active Units Container Card -->
    <div class="bg-white dark:bg-slate-900/90 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm dark:shadow-xl space-y-6">
      <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
        <div>
          <h2 class="text-base font-black text-slate-900 dark:text-white">
            {{ $t('super.active_units_count', { count: units.length }) }}
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            {{ $t('super.active_units_desc') }}
          </p>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="p-12 text-center">
        <div class="w-8 h-8 border-3 border-purple-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
        <p class="text-xs text-slate-400">{{ $t('super.loading_units') }}</p>
      </div>

      <div v-else class="space-y-6">
        <!-- Units Badges Grid -->
        <ActiveUnitsGrid :units="units" @remove="removeUnit" />

        <!-- Add Custom Unit Input -->
        <AddCustomUnitSection v-model="newUnitInput" @add="addCustomUnit" />

        <!-- Preset Suggestions -->
        <UnitPresetSuggestions :presets="presets" :units="units" @add-preset="addPreset" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { Crown, Scale, Save } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import ActiveUnitsGrid from '../../Components/SuperAdmin/ActiveUnitsGrid.vue';
import AddCustomUnitSection from '../../Components/SuperAdmin/AddCustomUnitSection.vue';
import UnitPresetSuggestions from '../../Components/SuperAdmin/UnitPresetSuggestions.vue';
import { useSuperAdminUnits } from '../../Composables/useSuperAdminUnits';

const {
  units,
  presets,
  newUnitInput,
  isLoading,
  isSaving,
  addCustomUnit,
  addPreset,
  removeUnit,
  saveUnits,
} = useSuperAdminUnits();
</script>
