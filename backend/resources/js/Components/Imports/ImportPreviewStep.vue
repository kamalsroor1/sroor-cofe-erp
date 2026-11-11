<script setup>
import { ArrowRight, CheckCircle2 } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';
import ImportSummaryBar from './ImportSummaryBar.vue';
import ImportPreviewTable from './ImportPreviewTable.vue';

defineProps({
  type: { type: String, required: true },
  preview: { type: Object, required: true },
  summary: { type: Object, required: true },
  rows: { type: Array, required: true },
  filter: { type: String, required: true },
  skipInvalid: { type: Boolean, default: false },
  needsSkipConfirmation: { type: Boolean, default: false },
  canCommit: { type: Boolean, default: false },
  importableCount: { type: Number, default: 0 },
  isCommitting: { type: Boolean, default: false },
});

defineEmits(['update:filter', 'update:skipInvalid', 'commit', 'back']);
</script>

<template>
  <section class="space-y-4 font-tajawal" data-testid="import-preview-step">
    <ImportSummaryBar :summary="summary" :filter="filter" @update:filter="$emit('update:filter', $event)" />

    <p
      v-if="preview.rows_truncated"
      class="p-3 rounded-xl border border-sky-200 dark:border-sky-500/30 bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300 text-xs font-bold"
    >
      {{ $t('import.ui.truncated', { shown: preview.rows.length, total: summary.total }) }}
    </p>

    <ImportPreviewTable :type="type" :rows="rows" />

    <label
      v-if="needsSkipConfirmation"
      class="flex items-start gap-3 p-4 min-h-[44px] rounded-2xl border border-rose-200 dark:border-rose-500/30 bg-rose-50 dark:bg-rose-500/10 cursor-pointer"
    >
      <input
        type="checkbox"
        class="w-5 h-5 mt-0.5 shrink-0 accent-rose-600"
        :checked="skipInvalid"
        @change="$emit('update:skipInvalid', $event.target.checked)"
      />
      <span class="space-y-0.5">
        <span class="block text-sm font-black text-slate-900 dark:text-white">{{ $t('import.ui.skip_invalid') }}</span>
        <span class="block text-xs font-bold text-rose-700 dark:text-rose-300">
          {{ $t('import.ui.skip_invalid_required', { count: summary.invalid }) }}
        </span>
      </span>
    </label>

    <p v-if="importableCount === 0" class="text-xs font-bold text-slate-500 dark:text-slate-400">
      {{ $t('import.ui.nothing_to_import') }}
    </p>

    <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
      <BaseButton
        variant="ghost"
        :icon="ArrowRight"
        icon-class="ltr:rotate-180"
        :label="$t('import.ui.back')"
        @click="$emit('back')"
      />
      <BaseButton
        variant="primary"
        size="lg"
        :icon="CheckCircle2"
        :loading="isCommitting"
        :disabled="!canCommit"
        data-testid="import-commit-btn"
        :label="isCommitting ? $t('import.ui.importing') : $t('import.ui.import_now', { count: importableCount })"
        @click="$emit('commit')"
      />
    </div>
  </section>
</template>
