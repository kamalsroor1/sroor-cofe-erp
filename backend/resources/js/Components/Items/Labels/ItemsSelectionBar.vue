<template>
  <div
    v-if="count > 0"
    class="flex flex-wrap items-center justify-between gap-3 p-3 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-md font-tajawal"
    role="region"
    :aria-label="$t('inventory.labels.selected_count', { count })"
    data-testid="items-selection-bar"
  >
    <span class="text-sm font-bold text-slate-800 dark:text-slate-200" data-testid="items-selected-count">
      {{ $t('inventory.labels.selected_count', { count }) }}
    </span>
    <div class="flex items-center gap-2">
      <BaseButton
        type="button"
        variant="ghost"
        size="md"
        :icon="X"
        :label="$t('inventory.labels.clear_selection')"
        @click="$emit('clear')"
      />
      <BaseButton
        type="button"
        variant="gradient"
        size="md"
        :icon="Tag"
        :label="$t('inventory.labels.print_labels')"
        data-testid="items-print-labels"
        @click="$emit('print')"
      />
    </div>
  </div>
</template>

<script setup>
import { Tag, X } from 'lucide-vue-next';
import BaseButton from '../../Common/BaseButton.vue';

defineProps({
  count: { type: Number, default: 0 },
});

defineEmits(['clear', 'print']);
</script>
