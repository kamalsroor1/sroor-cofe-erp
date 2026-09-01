<template>
  <AppModal
    :show="!!selectedLog"
    :title="selectedLog ? $t('activity.log_details_title', { id: selectedLog.id }) : ''"
    @close="$emit('close')"
  >
    <div v-if="selectedLog" class="space-y-3 text-xs font-tajawal">
      <div class="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-800">
        <div class="text-slate-500 dark:text-slate-400 font-bold mb-1">{{ $t('activity.full_description') }}</div>
        <div class="text-slate-900 dark:text-white font-bold">{{ selectedLog.description }}</div>
      </div>

      <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 font-mono text-[11px] overflow-x-auto max-h-60" dir="ltr">
        <pre class="text-emerald-400">{{ payloadText }}</pre>
      </div>

      <div class="flex justify-end pt-2 border-t border-slate-200 dark:border-slate-800">
        <button
          type="button"
          @click="$emit('close')"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 rounded-xl font-bold text-xs cursor-pointer"
        >
          {{ $t('common.close') }}
        </button>
      </div>
    </div>
  </AppModal>
</template>

<script setup>
import { computed } from 'vue';
import AppModal from '../Common/AppModal.vue';

const props = defineProps({
  selectedLog: { type: Object, default: null },
});

defineEmits(['close']);

const payloadText = computed(() => {
  if (!props.selectedLog) return '';
  const data = props.selectedLog.properties || props.selectedLog.payload;
  if (!data) return '{}';
  if (typeof data === 'string') {
    try {
      return JSON.stringify(JSON.parse(data), null, 2);
    } catch {
      return data;
    }
  }
  return JSON.stringify(data, null, 2);
});
</script>
