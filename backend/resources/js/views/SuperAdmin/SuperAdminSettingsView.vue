<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <PageHeader
      :title="$t('super.platform_settings.title')"
      :subtitle="$t('super.platform_settings.subtitle')"
      :icon="Settings"
    />

    <PlatformSettingsSkeleton v-if="isLoading" />

    <div
      v-else-if="loadError"
      class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-sm"
    >
      <ErrorState
        :title="$t('super.platform_settings.load_failed')"
        :message="loadError"
        :loading="isLoading"
        @retry="fetchSettings"
      />
    </div>

    <form v-else class="space-y-6" novalidate data-testid="platform-settings-form" @submit.prevent="save">
      <PlatformReadOnlyNotice v-if="!canManage" />

      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <PlatformIdentitySection :form="form" :errors="errors" :readonly="!canManage" @update:field="setField" />
        <PlatformSupportSection :form="form" :errors="errors" :readonly="!canManage" @update:field="setField" />
      </div>

      <PlatformAppearanceSection :form="form" :errors="errors" :readonly="!canManage" @update:field="setField" />

      <PlatformAssetsSection
        :assets="assets"
        :errors="assetErrors"
        :busy-slot="busySlot"
        :readonly="!canManage"
        @upload="uploadAsset"
        @remove="removeAsset"
      />

      <PlatformSettingsSaveBar v-if="canManage" :dirty="isDirty" :saving="isSaving" @discard="discard" />
    </form>
  </div>
</template>

<script setup>
import { Settings } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import ErrorState from '../../Components/Common/ErrorState.vue';
import PlatformSettingsSkeleton from '../../Components/SuperAdmin/PlatformSettings/PlatformSettingsSkeleton.vue';
import PlatformReadOnlyNotice from '../../Components/SuperAdmin/PlatformSettings/PlatformReadOnlyNotice.vue';
import PlatformIdentitySection from '../../Components/SuperAdmin/PlatformSettings/PlatformIdentitySection.vue';
import PlatformSupportSection from '../../Components/SuperAdmin/PlatformSettings/PlatformSupportSection.vue';
import PlatformAppearanceSection from '../../Components/SuperAdmin/PlatformSettings/PlatformAppearanceSection.vue';
import PlatformAssetsSection from '../../Components/SuperAdmin/PlatformSettings/PlatformAssetsSection.vue';
import PlatformSettingsSaveBar from '../../Components/SuperAdmin/PlatformSettings/PlatformSettingsSaveBar.vue';
import { usePlatformSettings } from '../../Composables/usePlatformSettings';

const {
  form,
  assets,
  errors,
  assetErrors,
  busySlot,
  isLoading,
  isSaving,
  loadError,
  canManage,
  isDirty,
  fetchSettings,
  setField,
  discard,
  save,
  uploadAsset,
  removeAsset,
} = usePlatformSettings();
</script>
