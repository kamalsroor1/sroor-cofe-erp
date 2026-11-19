<template>
  <div class="mx-auto mb-6 w-full max-w-3xl space-y-3" data-testid="labels-toolbar">
    <div
      class="flex flex-wrap items-center justify-between gap-3 p-3 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-lg"
    >
      <div class="min-w-0">
        <h1 class="text-base font-black text-slate-900 dark:text-white">
          {{ $t('inventory.labels.print_page_title') }}
        </h1>
        <p v-if="result.labels.length" class="text-xs text-slate-500 dark:text-slate-400" data-testid="labels-count">
          {{ $t('inventory.labels.pages_count', { labels: result.labels.length, pages: pageCount }) }}
        </p>
      </div>
      <div class="flex items-center gap-2">
        <BaseButton
          type="button"
          variant="secondary"
          size="md"
          :icon="ArrowRight"
          icon-class="rtl:rotate-0 ltr:rotate-180"
          :label="$t('inventory.labels.back_to_items')"
          @click="$emit('back')"
        />
        <BaseButton
          type="button"
          variant="gradient"
          size="md"
          :icon="Printer"
          :disabled="!canPrint"
          :label="$t('inventory.labels.print_now')"
          data-testid="labels-print"
          @click="$emit('print')"
        />
      </div>
    </div>

    <p v-if="result.labels.length" class="text-xs text-slate-600 dark:text-slate-400">
      {{ $t('inventory.labels.print_hint') }}
    </p>

    <div
      v-if="result.skipped.length"
      class="p-3 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900 text-xs text-amber-800 dark:text-amber-300 space-y-1"
      role="status"
      data-testid="labels-skipped"
    >
      <div class="font-bold">
        <AlertTriangle class="inline w-4 h-4 me-1 align-text-bottom" />
        {{ $t('inventory.labels.skipped_summary', { count: result.skipped.length }) }}
      </div>
      <ul class="list-disc ps-5">
        <li v-for="entry in result.skipped" :key="entry.id">
          {{ entry.name }}: {{ $t(`inventory.labels.skip_${entry.reason}`) }}
        </li>
      </ul>
    </div>
    <p v-if="result.truncated" class="text-xs font-bold text-rose-600 dark:text-rose-400" role="alert">
      {{ $t('inventory.labels.truncated', { max: MAX_LABELS }) }}
    </p>
  </div>
</template>

<script setup>
import { AlertTriangle, ArrowRight, Printer } from 'lucide-vue-next';
import BaseButton from '../../Common/BaseButton.vue';
import { MAX_LABELS } from '../../../helpers/labelPrint';

defineProps({
  result: { type: Object, required: true },
  pageCount: { type: Number, default: 0 },
  canPrint: { type: Boolean, default: false },
});

defineEmits(['print', 'back']);
</script>
