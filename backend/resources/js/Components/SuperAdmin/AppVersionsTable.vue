<template>
  <div class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm dark:shadow-xl space-y-4 font-tajawal">
    <div class="flex items-center justify-between">
      <h2 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
        <HardDrive class="w-4.5 h-4.5 text-purple-500 dark:text-purple-400" />
        <span>{{ $t('super.releases_history_title') }}</span>
      </h2>
      <button
        type="button"
        @click="$emit('refresh')"
        class="p-2 text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition cursor-pointer active:scale-95"
        :title="$t('common.refresh')"
      >
        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
      </button>
    </div>

    <!-- Loading State Skeleton -->
    <div v-if="loading" class="p-4">
      <TableSkeleton :rows="5" :cols="8" />
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else-if="!versions.length"
      :title="$t('super.no_versions_published')"
      :description="$t('super.no_versions_published')"
      icon="🚀"
    >
      <template #action>
        <BaseButton
          type="button"
          variant="primary"
          size="md"
          @click="$emit('open-create')"
          class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold"
        >
          {{ $t('super.publish_new_apk_btn') }}
        </BaseButton>
      </template>
    </EmptyState>

    <div v-else>
      <!-- Desktop Table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-xs text-start">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold text-[11px]">
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
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-mono">
            <tr v-for="v in versions" :key="v.id" class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition">
              <td class="py-3.5">
                <div class="flex items-center gap-2">
                  <span class="font-black text-slate-900 dark:text-white font-mono text-sm">v{{ v.version_name }}</span>
                  <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-[10px] font-mono">
                    Code: {{ v.version_code }}
                  </span>
                </div>
              </td>
              <td class="py-3.5 font-sans">
                <span class="capitalize font-bold text-slate-700 dark:text-slate-300">{{ v.platform }}</span>
              </td>
              <td class="py-3.5 font-sans">
                <span
                  v-if="v.is_force_update"
                  class="px-2 py-0.5 rounded-md bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-[10px] font-bold"
                >
                  {{ $t('super.mandatory_update_badge') }}
                </span>
                <span
                  v-else
                  class="px-2 py-0.5 rounded-md bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-[10px] font-bold"
                >
                  {{ $t('super.optional_update_badge') }}
                </span>
              </td>
              <td class="py-3.5 font-mono text-slate-700 dark:text-slate-300">
                {{ formatBytes(v.apk_size_bytes) }}
              </td>
              <td class="py-3.5 font-mono font-bold text-theme-primary">
                {{ v.download_count }}
              </td>
              <td class="py-3.5 text-slate-500 dark:text-slate-400 text-[11px] font-sans">
                {{ v.published_at ? v.published_at.split('T')[0] : '—' }}
              </td>
              <td class="py-3.5 font-sans">
                <button
                  type="button"
                  @click="$emit('toggle-active', v)"
                  class="px-2.5 py-1 rounded-full text-[10px] font-bold transition cursor-pointer active:scale-95"
                  :class="v.is_active ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'"
                >
                  {{ v.is_active ? $t('super.active_available_badge') : $t('super.disabled_badge') }}
                </button>
              </td>
              <td class="py-3.5 text-end font-sans">
                <div class="flex items-center justify-end gap-2">
                  <a
                    :href="`/api/v1/app/download-apk?platform=${v.platform}`"
                    class="p-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-theme-primary rounded-xl transition cursor-pointer active:scale-95"
                    :title="$t('super.download_package_hint')"
                    download
                  >
                    <Download class="w-4 h-4" />
                  </a>

                  <button
                    type="button"
                    @click="$emit('delete-version', v)"
                    class="p-2 bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 rounded-xl transition cursor-pointer active:scale-95"
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

      <!-- Mobile Tactile Cards -->
      <div class="block md:hidden divide-y divide-slate-100 dark:divide-slate-800 space-y-2">
        <div
          v-for="v in versions"
          :key="v.id"
          class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-3"
        >
          <div class="flex items-start justify-between">
            <div class="flex items-center gap-2">
              <span class="font-black text-slate-900 dark:text-white font-mono text-base">v{{ v.version_name }}</span>
              <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500 text-[10px] font-mono">
                Code: {{ v.version_code }}
              </span>
            </div>
            <button
              type="button"
              @click="$emit('toggle-active', v)"
              class="px-2.5 py-1 rounded-full text-[10px] font-bold border"
              :class="v.is_active ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'"
            >
              {{ v.is_active ? $t('super.active_available_badge') : $t('super.disabled_badge') }}
            </button>
          </div>

          <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 font-mono">
            <span>{{ v.platform }} • {{ formatBytes(v.apk_size_bytes) }}</span>
            <span class="text-theme-primary font-bold">{{ v.download_count }} {{ $t('super.download_unit') }}</span>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
            <a
              :href="`/api/v1/app/download-apk?platform=${v.platform}`"
              class="min-h-[36px] px-3 py-1.5 bg-theme-light text-theme-primary rounded-xl text-xs font-bold transition flex items-center gap-1.5 active:scale-95"
              download
            >
              <Download class="w-3.5 h-3.5" />
              <span>{{ $t('super.download_package_hint') }}</span>
            </a>

            <button
              type="button"
              @click="$emit('delete-version', v)"
              class="min-h-[36px] px-3 py-1.5 bg-rose-500/10 text-rose-600 dark:text-rose-400 rounded-xl text-xs font-bold transition flex items-center gap-1.5 active:scale-95"
            >
              <Trash2 class="w-3.5 h-3.5" />
              <span>{{ $t('common.delete') }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { HardDrive, RefreshCw, Download, Trash2 } from 'lucide-vue-next';
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import BaseButton from '../Common/BaseButton.vue';

defineProps({
  versions: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

defineEmits(['refresh', 'open-create', 'toggle-active', 'delete-version']);

const formatBytes = (bytes) => {
    if (!bytes) return '0 B';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return bytes + ' B';
};
</script>
