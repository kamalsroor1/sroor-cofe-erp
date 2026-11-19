<template>
  <section
    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xs space-y-4 font-tajawal"
  >
    <header class="flex items-start gap-3">
      <div
        class="w-10 h-10 shrink-0 rounded-2xl flex items-center justify-center bg-theme-primary/10 text-theme-primary"
      >
        <DatabaseBackup class="w-5 h-5" />
      </div>
      <div class="min-w-0">
        <h2 class="text-sm sm:text-base font-black text-slate-900 dark:text-white">
          {{ $t('settings.data_export.title') }}
        </h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">
          {{ $t('settings.data_export.subtitle') }}
        </p>
      </div>
    </header>

    <!-- Skeleton while the latest export loads -->
    <div v-if="isLoading" class="space-y-2">
      <Skeleton height="h-4" width="w-1/3" />
      <Skeleton height="h-11" width="w-full sm:w-56" rounded="rounded-2xl" />
    </div>

    <template v-else>
      <div
        class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 sm:p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800"
      >
        <div class="min-w-0 space-y-1">
          <span
            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-black border"
            :class="badgeClass"
          >
            <Loader2 v-if="isActive" class="w-3.5 h-3.5 animate-spin" />
            <span>{{ statusLabel }}</span>
          </span>
          <p v-if="isActive" class="text-xs text-slate-500 dark:text-slate-400">
            {{ $t('settings.data_export.preparing_hint') }}
          </p>
          <p v-else-if="status === 'failed'" class="text-xs text-rose-600 dark:text-rose-400">
            {{ $t('settings.data_export.failed_hint') }}
          </p>
          <template v-else-if="canDownload">
            <p class="text-xs text-slate-600 dark:text-slate-300">{{ summary }}</p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
              {{ $t('settings.data_export.ready_at', { date: formatDateTime(current.completed_at) }) }} ·
              {{ $t('settings.data_export.expires_at', { date: formatDateTime(current.expires_at) }) }}
            </p>
          </template>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
          <BaseButton
            v-if="canDownload"
            type="button"
            variant="primary"
            size="md"
            :loading="isDownloading"
            class="w-full sm:w-auto min-h-[44px] font-black flex items-center justify-center gap-2"
            @click="download"
          >
            <Download class="w-4 h-4" />
            <span>{{ $t('settings.data_export.download') }}</span>
          </BaseButton>
          <BaseButton
            type="button"
            :variant="canDownload ? 'secondary' : 'primary'"
            size="md"
            :loading="isRequesting"
            :disabled="isActive"
            class="w-full sm:w-auto min-h-[44px] font-black flex items-center justify-center gap-2"
            @click="requestExport"
          >
            <FileArchive class="w-4 h-4" />
            <span>{{ current ? $t('settings.data_export.restart') : $t('settings.data_export.start') }}</span>
          </BaseButton>
        </div>
      </div>

      <p class="text-[11px] text-slate-500 dark:text-slate-400 flex items-start gap-1.5">
        <ShieldCheck class="w-3.5 h-3.5 shrink-0 mt-0.5 text-emerald-500" />
        <span>{{ $t('settings.data_export.privacy_note', { hours: retentionHours }) }}</span>
      </p>
    </template>
  </section>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { DatabaseBackup, Download, FileArchive, Loader2, ShieldCheck } from 'lucide-vue-next';
import BaseButton from '../../Common/BaseButton.vue';
import Skeleton from '../../Common/Skeletons/Skeleton.vue';
import { useDataExport } from '../../../Composables/useDataExport';
import { useFormatters } from '../../../Composables/useFormatters';
import { useTrans } from '../../../Composables/useTrans';

defineProps({
  retentionHours: { type: Number, default: 24 },
});

const { t } = useTrans();
const { formatDateTime, formatNumber } = useFormatters();
const {
  current,
  status,
  isLoading,
  isRequesting,
  isDownloading,
  isActive,
  canDownload,
  load,
  requestExport,
  download,
} = useDataExport();

const statusLabel = computed(() =>
  status.value ? t(`settings.data_export.status_${status.value}`) : t('settings.data_export.status_none')
);

const badgeClass = computed(() => {
  switch (status.value) {
    case 'ready':
      return 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-900';
    case 'failed':
      return 'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-900';
    case 'pending':
    case 'processing':
      return 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-900';
    default:
      return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700';
  }
});

const formatSize = (bytes) => {
  const n = Number(bytes) || 0;
  if (n >= 1048576) return `${(n / 1048576).toFixed(1)} MB`;
  return `${Math.max(1, Math.round(n / 1024))} KB`;
};

const summary = computed(() =>
  t('settings.data_export.summary', {
    tables: formatNumber(current.value?.tables_count ?? 0),
    rows: formatNumber(current.value?.rows_count ?? 0),
    size: formatSize(current.value?.file_size),
  })
);

onMounted(() => load());
</script>
