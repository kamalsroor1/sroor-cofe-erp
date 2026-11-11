<script setup>
import { ref } from 'vue';
import { UploadCloud, Download, ArrowRight, FileSpreadsheet } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';
import Skeleton from '../Common/Skeletons/Skeleton.vue';
import ImportStoreNotice from './ImportStoreNotice.vue';

defineProps({
  type: { type: String, required: true },
  store: { type: Object, default: null },
  notice: { type: String, default: '' },
  isUploading: { type: Boolean, default: false },
  isDownloading: { type: Boolean, default: false },
  fileName: { type: String, default: '' },
});

const emit = defineEmits(['file', 'download', 'back']);

const fileInput = ref(null);
const isDragging = ref(false);

const pick = (event) => {
  const selected = event.target.files?.[0];
  if (selected) emit('file', selected);
  event.target.value = '';
};

const drop = (event) => {
  isDragging.value = false;
  const dropped = event.dataTransfer?.files?.[0];
  if (dropped) emit('file', dropped);
};
</script>

<template>
  <section class="space-y-4 font-tajawal">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div class="space-y-1">
        <h2 class="text-base font-black text-slate-900 dark:text-white">
          {{ $t('import.ui.upload_title', { type: $t(`import.ui.types.${type}.title`) }) }}
        </h2>
        <p class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('import.ui.template_hint') }}</p>
      </div>
      <BaseButton
        variant="outline"
        :icon="Download"
        :loading="isDownloading"
        :label="isDownloading ? $t('import.ui.downloading') : $t('import.ui.download_template')"
        @click="$emit('download')"
      />
    </div>

    <ImportStoreNotice :store="store" :show-stock="type === 'items'" />

    <div
      v-if="isUploading"
      class="p-6 rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-4"
      aria-busy="true"
    >
      <p class="flex items-center gap-2 text-sm font-black text-slate-700 dark:text-slate-200">
        <FileSpreadsheet class="w-5 h-5" style="color: var(--color-primary)" />
        <span class="truncate">{{ fileName }}</span>
      </p>
      <Skeleton height="h-4" width="w-3/4" />
      <Skeleton height="h-4" width="w-1/2" />
      <Skeleton height="h-4" />
      <p class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('import.ui.uploading') }}</p>
    </div>

    <button
      v-else
      type="button"
      class="w-full min-h-[200px] p-6 rounded-3xl border-2 border-dashed flex flex-col items-center justify-center gap-2 text-center bg-white dark:bg-slate-900 transition cursor-pointer"
      :class="isDragging ? '' : 'border-slate-300 dark:border-slate-700'"
      :style="isDragging ? { borderColor: 'var(--color-primary)', background: 'var(--color-primary-light)' } : null"
      @click="fileInput.click()"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="drop"
    >
      <UploadCloud class="w-10 h-10" style="color: var(--color-primary)" />
      <span class="font-black text-sm text-slate-900 dark:text-white">{{ $t('import.ui.drop_here') }}</span>
      <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('import.ui.or_browse') }}</span>
      <span class="text-[11px] font-bold text-slate-400 dark:text-slate-500">{{ $t('import.ui.accepted_files') }}</span>
    </button>
    <input
      ref="fileInput"
      type="file"
      class="hidden"
      data-testid="import-file-input"
      accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
      @change="pick"
    />

    <p
      v-if="notice"
      class="p-3 rounded-xl border border-rose-200 dark:border-rose-500/30 bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 text-xs font-bold"
      role="alert"
    >
      {{ notice }}
    </p>

    <BaseButton
      variant="ghost"
      :icon="ArrowRight"
      icon-class="ltr:rotate-180"
      :label="$t('import.ui.back')"
      @click="$emit('back')"
    />
  </section>
</template>
