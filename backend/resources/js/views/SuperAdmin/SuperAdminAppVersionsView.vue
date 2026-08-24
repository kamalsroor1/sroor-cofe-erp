<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal pb-12">
    <!-- Header -->
    <PageHeader
      :title="$t('super.app_versions_page_title')"
      :subtitle="$t('super.app_versions_page_subtitle')"
      badge="OTA Updater"
      icon="🚀"
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

          <button
            type="button"
            @click="openCreateModal"
            class="px-5 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-purple-500/25 flex items-center gap-2 transition active:scale-95 cursor-pointer"
          >
            <Plus class="w-4 h-4" />
            <span>{{ $t('super.publish_new_apk_btn') }}</span>
          </button>
        </div>
      </template>
    </PageHeader>

    <!-- KPI Summary Cards -->
    <AppVersionsSummaryGrid :summary="summary" />

    <!-- Releases List Table & Mobile Cards -->
    <AppVersionsTable
      :versions="versions"
      :loading="isLoading"
      @refresh="fetchVersions"
      @open-create="openCreateModal"
      @toggle-active="toggleActive"
      @delete-version="deleteVersion"
    />

    <!-- Upload New APK Release Modal -->
    <UploadApkModal
      :show="isCreateModalOpen"
      :form="form"
      :is-submitting="isSubmitting"
      @update:field="updateFormField"
      @file-change="handleFileUpload"
      @submit="submitCreateVersion"
      @close="isCreateModalOpen = false"
    />
  </div>
</template>

<script setup>
import { Crown, Plus } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import AppVersionsSummaryGrid from '../../Components/SuperAdmin/AppVersionsSummaryGrid.vue';
import AppVersionsTable from '../../Components/SuperAdmin/AppVersionsTable.vue';
import UploadApkModal from '../../Components/SuperAdmin/UploadApkModal.vue';
import { useSuperAdminAppVersions } from '../../Composables/useSuperAdminAppVersions';

const {
  versions,
  summary,
  isLoading,
  isCreateModalOpen,
  isSubmitting,
  form,
  fetchVersions,
  openCreateModal,
  updateFormField,
  handleFileUpload,
  submitCreateVersion,
  toggleActive,
  deleteVersion,
} = useSuperAdminAppVersions();
</script>
