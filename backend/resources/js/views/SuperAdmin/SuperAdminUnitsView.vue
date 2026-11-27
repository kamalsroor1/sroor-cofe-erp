<template>
  <div class="space-y-6 max-w-5xl mx-auto font-tajawal">
    <!-- Page Header -->
    <PageHeader :title="$t('super.units_page_title')" :subtitle="$t('super.units_page_subtitle')" :icon="Scale">
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
            :disabled="isLoading || (error && !hasLoaded)"
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
    <div
      class="bg-white dark:bg-slate-900/90 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm dark:shadow-xl space-y-6"
    >
      <!-- Loading State Skeleton -->
      <UnitsSkeleton v-if="isLoading && !hasLoaded" />

      <!-- Error State -->
      <ErrorState
        v-else-if="error && !hasLoaded"
        :title="$t('super.units_load_failed')"
        :message="errorMessage"
        :status-code="statusCode"
        :can-retry="canRetry"
        :loading="isLoading"
        @retry="retry"
      />

      <div v-else class="space-y-6">
        <InlineErrorBar
          v-if="error"
          :message="errorMessage"
          :status-code="statusCode"
          :can-retry="canRetry"
          @retry="retry"
        />

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
import ErrorState from '../../Components/Common/ErrorState.vue';
import InlineErrorBar from '../../Components/Common/InlineErrorBar.vue';
import UnitsSkeleton from '../../Components/SuperAdmin/UnitsSkeleton.vue';
import ActiveUnitsGrid from '../../Components/SuperAdmin/ActiveUnitsGrid.vue';
import AddCustomUnitSection from '../../Components/SuperAdmin/AddCustomUnitSection.vue';
import UnitPresetSuggestions from '../../Components/SuperAdmin/UnitPresetSuggestions.vue';
import { useSuperAdminUnits } from '../../Composables/useSuperAdminUnits';

const {
  units,
  presets,
  newUnitInput,
  isLoading,
  hasLoaded,
  error,
  errorMessage,
  statusCode,
  canRetry,
  isSaving,
  retry,
  addCustomUnit,
  addPreset,
  removeUnit,
  saveUnits,
} = useSuperAdminUnits();
</script>
