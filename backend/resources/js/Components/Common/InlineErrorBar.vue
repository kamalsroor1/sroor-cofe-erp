<script setup>
import { computed } from 'vue';
import { AlertCircle } from 'lucide-vue-next';

const props = defineProps({
  message: {
    type: String,
    default: '',
  },
  canRetry: {
    type: Boolean,
    default: true,
  },
  statusCode: {
    type: [Number, String],
    default: null,
  },
  retryText: {
    type: String,
    default: '',
  },
});

defineEmits(['retry']);

const effectiveCanRetry = computed(() => {
  if (!props.canRetry) return false;
  const status = Number(props.statusCode);
  if (status === 403) return false;
  return true;
});
</script>

<template>
  <div
    data-testid="inline-error-bar"
    class="flex items-center justify-between p-3 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800/50 text-red-700 dark:text-red-400 mb-4 font-tajawal select-none"
  >
    <div class="flex items-center gap-2 text-sm font-bold min-w-0 me-3">
      <AlertCircle class="w-5 h-5 shrink-0" />
      <span class="truncate">{{ message || $t('common.error_occurred') }}</span>
    </div>
    <button
      v-if="effectiveCanRetry"
      type="button"
      data-testid="retry-button"
      class="min-h-[44px] min-w-[44px] px-3.5 py-1.5 rounded-lg bg-red-100 dark:bg-red-900/50 hover:bg-red-200 dark:hover:bg-red-900/80 text-xs font-bold text-red-800 dark:text-red-200 transition shrink-0 cursor-pointer active:scale-95"
      @click="$emit('retry')"
    >
      {{ retryText || $t('connectivity.retry') }}
    </button>
  </div>
</template>
