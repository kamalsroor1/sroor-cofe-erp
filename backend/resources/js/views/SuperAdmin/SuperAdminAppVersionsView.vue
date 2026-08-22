<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal pb-12">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-950/80 p-5 rounded-3xl border border-slate-800 shadow-xl backdrop-blur-md">
      <div class="flex items-center gap-3.5">
        <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 p-0.5 shadow-lg shadow-purple-500/20 text-white flex items-center justify-center shrink-0">
          <Rocket class="w-6 h-6" />
        </div>
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-lg sm:text-xl font-black text-white">{{ $t('super.app_versions_page_title') }}</h1>
            <span class="px-2.5 py-0.5 rounded-full bg-purple-500/15 border border-purple-500/30 text-purple-300 text-[10px] font-bold">
              OTA Updater
            </span>
          </div>
          <p class="text-xs text-slate-400 mt-0.5">
            {{ $t('super.app_versions_page_subtitle') }}
          </p>
        </div>
      </div>

      <button
        type="button"
        @click="openCreateModal"
        class="w-full sm:w-auto px-5 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold text-xs sm:text-sm rounded-2xl shadow-lg shadow-purple-500/25 flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer"
      >
        <Plus class="w-4.5 h-4.5" />
        <span>{{ $t('super.publish_new_apk_btn') }}</span>
      </button>
    </div>

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="p-5 rounded-3xl bg-slate-950/80 border border-slate-800 shadow-lg flex items-center justify-between">
        <div>
          <span class="text-xs text-slate-400 block font-bold">{{ $t('super.current_active_version') }}</span>
          <span class="text-xl font-black text-emerald-400 mt-1 block font-mono">
            v{{ summary.active_version || '1.0.0' }}
          </span>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center">
          <CheckCircle class="w-5 h-5" />
        </div>
      </div>

      <div class="p-5 rounded-3xl bg-slate-950/80 border border-slate-800 shadow-lg flex items-center justify-between">
        <div>
          <span class="text-xs text-slate-400 block font-bold">{{ $t('super.total_inapp_downloads') }}</span>
          <span class="text-xl font-black text-amber-400 mt-1 block font-mono">
            {{ summary.total_downloads || 0 }} {{ $t('super.download_unit') }}
          </span>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center">
          <Download class="w-5 h-5" />
        </div>
      </div>

      <div class="p-5 rounded-3xl bg-slate-950/80 border border-slate-800 shadow-lg flex items-center justify-between">
        <div>
          <span class="text-xs text-slate-400 block font-bold">{{ $t('super.published_releases_count') }}</span>
          <span class="text-xl font-black text-purple-400 mt-1 block font-mono">
            {{ summary.total_releases || 0 }}
          </span>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center">
          <Layers class="w-5 h-5" />
        </div>
      </div>
    </div>

    <!-- Releases List -->
    <div class="bg-slate-950/80 rounded-3xl border border-slate-800 p-6 shadow-xl space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="text-sm font-black text-white flex items-center gap-2">
          <HardDrive class="w-4.5 h-4.5 text-purple-400" />
          <span>{{ $t('super.releases_history_title') }}</span>
        </h2>
        <button
          type="button"
          @click="fetchVersions"
          class="p-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-xl transition cursor-pointer"
          :title="$t('common.refresh')"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="p-12 text-center">
        <div class="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-xs text-slate-400">{{ $t('super.loading_versions') }}</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="!versions.length" class="p-12 text-center text-slate-400 space-y-2">
        <Rocket class="w-10 h-10 mx-auto text-slate-600" />
        <p class="text-xs">{{ $t('super.no_versions_published') }}</p>
      </div>

      <!-- Table -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-xs text-start">
          <thead>
            <tr class="border-b border-slate-800 text-slate-400 font-bold text-[11px]">
              <th class="pb-3 text-start">{{ $t('super.version_col') }}</th>
              <th class="pb-3 text-start">{{ $t('super.platform_col') }}</th>
              <th class="pb-3 text-start">{{ $t('super.type_col') }}</th>
              <th class="pb-3 text-start">{{ $t('super.size_col') }}</th>
              <th class="pb-3 text-start">{{ $t('super.downloads_col') }}</th>
              <th class="pb-3 text-start">{{ $t('super.published_at_col') }}</th>
              <th class="pb-3 text-start">{{ $t('common.status') }}</th>
              <th class="pb-3 text-end">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/60 font-mono">
            <tr v-for="v in versions" :key="v.id" class="hover:bg-slate-900/50 transition">
              <td class="py-3.5">
                <div class="flex items-center gap-2">
                  <span class="font-black text-white font-mono text-sm">v{{ v.version_name }}</span>
                  <span class="px-2 py-0.5 rounded-md bg-slate-800 text-slate-400 text-[10px] font-mono">
                    Code: {{ v.version_code }}
                  </span>
                </div>
              </td>
              <td class="py-3.5 font-sans">
                <span class="capitalize font-bold text-slate-300">{{ v.platform }}</span>
              </td>
              <td class="py-3.5 font-sans">
                <span
                  v-if="v.is_force_update"
                  class="px-2 py-0.5 rounded-md bg-rose-500/10 border border-rose-500/30 text-rose-400 text-[10px] font-bold"
                >
                  {{ $t('super.mandatory_update_badge') }}
                </span>
                <span
                  v-else
                  class="px-2 py-0.5 rounded-md bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-[10px] font-bold"
                >
                  {{ $t('super.optional_update_badge') }}
                </span>
              </td>
              <td class="py-3.5 font-mono text-slate-300">
                {{ formatBytes(v.apk_size_bytes) }}
              </td>
              <td class="py-3.5 font-mono font-bold text-amber-400">
                {{ v.download_count }}
              </td>
              <td class="py-3.5 text-slate-400 text-[11px] font-sans">
                {{ v.published_at ? v.published_at.split('T')[0] : '—' }}
              </td>
              <td class="py-3.5 font-sans">
                <button
                  type="button"
                  @click="toggleActive(v)"
                  class="px-2.5 py-1 rounded-full text-[10px] font-bold transition cursor-pointer"
                  :class="v.is_active ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' : 'bg-slate-800 text-slate-500'"
                >
                  {{ v.is_active ? $t('super.active_available_badge') : $t('super.disabled_badge') }}
                </button>
              </td>
              <td class="py-3.5 text-end font-sans">
                <div class="flex items-center justify-end gap-2">
                  <a
                    :href="`/api/v1/app/download-apk?platform=${v.platform}`"
                    class="p-2 bg-slate-800 hover:bg-slate-700 text-amber-400 rounded-xl transition cursor-pointer"
                    :title="$t('super.download_package_hint')"
                    download
                  >
                    <Download class="w-4 h-4" />
                  </a>

                  <button
                    type="button"
                    @click="deleteVersion(v)"
                    class="p-2 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 rounded-xl transition cursor-pointer"
                    :title="$t('super.delete_release_hint')"
                  >
                    <Trash2 class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Upload New APK Release Modal -->
    <AppModal
      :show="isCreateModalOpen"
      :title="$t('super.upload_apk_modal_title')"
      @close="isCreateModalOpen = false"
    >
      <form @submit.prevent="submitCreateVersion" class="space-y-4 text-xs font-tajawal">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="block font-bold text-slate-300">{{ $t('super.readable_version_name') }}</label>
            <input
              v-model="form.version_name"
              type="text"
              placeholder="1.1.0"
              required
              class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-purple-500"
            />
          </div>

          <div class="space-y-1.5">
            <label class="block font-bold text-slate-300">{{ $t('super.version_code_label') }}</label>
            <input
              v-model.number="form.version_code"
              type="number"
              min="1"
              placeholder="2"
              required
              class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-purple-500"
            />
          </div>

          <div class="space-y-1.5">
            <label class="block font-bold text-slate-300">{{ $t('super.target_platform_label') }}</label>
            <select
              v-model="form.platform"
              class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-purple-500 font-tajawal"
            >
              <option value="android">📱 Android (APK)</option>
              <option value="windows">💻 Windows</option>
              <option value="ios">🍏 iOS</option>
            </select>
          </div>

          <div class="space-y-1.5">
            <label class="block font-bold text-slate-300">{{ $t('super.min_supported_version') }}</label>
            <input
              v-model.number="form.min_version_code"
              type="number"
              min="1"
              placeholder="1"
              class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-purple-500"
            />
          </div>
        </div>

        <!-- Force update toggle -->
        <label class="flex items-center justify-between p-3.5 rounded-2xl bg-slate-900/80 border border-slate-800 cursor-pointer">
          <div>
            <div class="font-bold text-white">{{ $t('super.force_update_label') }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">{{ $t('super.force_update_desc') }}</div>
          </div>
          <input
            type="checkbox"
            v-model="form.is_force_update"
            class="w-4.5 h-4.5 rounded text-purple-600 focus:ring-purple-500"
          />
        </label>

        <!-- Release notes textarea -->
        <div class="space-y-1.5">
          <label class="block font-bold text-slate-300">{{ $t('super.release_notes_ar_label') }}</label>
          <textarea
            v-model="form.release_notes_ar"
            rows="3"
            required
            placeholder="• ميزة 1...&#10;• ميزة 2..."
            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-purple-500 leading-relaxed font-tajawal"
          ></textarea>
        </div>

        <!-- File Upload -->
        <div class="space-y-1.5">
          <label class="block font-bold text-slate-300">{{ $t('super.apk_file_label') }}</label>
          <input
            type="file"
            accept=".apk"
            @change="handleFileUpload"
            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white text-xs file:me-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-purple-600 file:text-white"
          />
          <span class="text-[10px] text-slate-500 block">{{ $t('super.apk_max_size_hint') }}</span>
        </div>

        <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-800">
          <button
            type="button"
            @click="isCreateModalOpen = false"
            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-bold transition cursor-pointer"
          >
            {{ $t('common.cancel') }}
          </button>

          <button
            type="submit"
            :disabled="isSubmitting"
            class="px-6 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold rounded-xl shadow-lg shadow-purple-500/25 flex items-center gap-2 transition disabled:opacity-50 cursor-pointer"
          >
            <Rocket class="w-4 h-4" />
            <span>{{ isSubmitting ? $t('super.publishing_status') : $t('super.publish_now_btn') }}</span>
          </button>
        </div>
      </form>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import AppModal from '../../Components/Common/AppModal.vue';
import { trans } from '../../helpers/trans';
import {
    Rocket,
    Plus,
    CheckCircle,
    Download,
    Layers,
    HardDrive,
    RefreshCw,
    Trash2
} from 'lucide-vue-next';

const versions = ref([]);
const summary = ref({});
const isLoading = ref(false);
const isCreateModalOpen = ref(false);
const isSubmitting = ref(false);
const selectedApkFile = ref(null);

const form = ref({
    platform: 'android',
    version_name: '',
    version_code: 2,
    min_version_code: 1,
    is_force_update: false,
    release_notes_ar: '',
    is_active: true,
});

const formatBytes = (bytes) => {
    if (!bytes) return '0 B';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return bytes + ' B';
};

const handleFileUpload = (e) => {
    selectedApkFile.value = e.target.files[0] || null;
};

const fetchVersions = async () => {
    isLoading.value = true;
    try {
        const res = await api.get('/super-admin/app-versions');
        versions.value = res.data?.versions?.data || [];
        summary.value = res.data?.summary || {};
    } catch (e) {
        console.error('Failed to load app versions:', e);
    } finally {
        isLoading.value = false;
    }
};

const openCreateModal = () => {
    const nextCode = versions.value.length ? Math.max(...versions.value.map(v => v.version_code)) + 1 : 2;
    form.value = {
        platform: 'android',
        version_name: `1.${nextCode - 1}.0`,
        version_code: nextCode,
        min_version_code: 1,
        is_force_update: false,
        release_notes_ar: '• تحسينات عامة في الأداء والسرعة واستقرار النظام.',
        is_active: true,
    };
    selectedApkFile.value = null;
    isCreateModalOpen.value = true;
};

const submitCreateVersion = async () => {
    isSubmitting.value = true;
    try {
        const formData = new FormData();
        formData.append('platform', form.value.platform);
        formData.append('version_name', form.value.version_name);
        formData.append('version_code', form.value.version_code);
        formData.append('min_version_code', form.value.min_version_code);
        formData.append('is_force_update', form.value.is_force_update ? '1' : '0');
        formData.append('release_notes_ar', form.value.release_notes_ar);
        formData.append('is_active', form.value.is_active ? '1' : '0');

        if (selectedApkFile.value) {
            formData.append('apk_file', selectedApkFile.value);
        }

        await api.post('/super-admin/app-versions', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        Swal.fire({
            icon: 'success',
            title: trans('common.success'),
            text: trans('super.release_published_success'),
            timer: 1500,
            showConfirmButton: false,
        });

        isCreateModalOpen.value = false;
        fetchVersions();
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: trans('common.error'),
            text: e.response?.data?.message || trans('super.release_publish_failed'),
        });
    } finally {
        isSubmitting.value = false;
    }
};

const toggleActive = async (v) => {
    try {
        await api.patch(`/super-admin/app-versions/${v.id}/toggle-active`);
        v.is_active = !v.is_active;
    } catch (e) {
        Swal.fire({ icon: 'error', title: trans('common.error'), text: trans('super.status_update_failed') });
    }
};

const deleteVersion = async (v) => {
    const result = await Swal.fire({
        title: trans('super.delete_version_confirm_title'),
        text: trans('super.delete_version_confirm_text', { version: v.version_name }),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: trans('common.yes'),
        cancelButtonText: trans('common.cancel'),
        confirmButtonColor: '#ef4444',
    });

    if (result.isConfirmed) {
        try {
            await api.delete(`/super-admin/app-versions/${v.id}`);
            Swal.fire({ icon: 'success', title: trans('common.success'), text: trans('super.version_deleted_success') });
            fetchVersions();
        } catch (e) {
            Swal.fire({ icon: 'error', title: trans('common.error'), text: trans('super.version_delete_failed') });
        }
    }
};

onMounted(() => {
    fetchVersions();
});
</script>
