<script setup>
import { AlertTriangle, RefreshCw } from 'lucide-vue-next';
import DynamicIcon from './DynamicIcon.vue';
import BaseButton from './BaseButton.vue';

defineProps({
  icon: {
    type: [Object, Function, String],
    default: () => AlertTriangle,
  },
  title: {
    type: String,
    default: '',
  },
  message: {
    type: String,
    default: '',
  },
  retryLabel: {
    type: String,
    default: '',
  },
  canRetry: {
    type: Boolean,
    default: true,
  },
  compact: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['retry']);
</script>

<template>
  <div
    class="text-center font-tajawal select-none transition-all duration-200"
    :class="[compact ? 'py-8 px-4 space-y-2.5' : 'py-14 sm:py-16 px-4 space-y-3.5']"
  >
    <div
      class="mx-auto rounded-3xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 flex items-center justify-center text-rose-500 dark:text-rose-400 shadow-inner"
      :class="[compact ? 'w-12 h-12' : 'w-16 h-16 sm:w-20 sm:h-20']"
    >
      <DynamicIcon :name="icon" :class="[compact ? 'w-6 h-6 stroke-[1.75]' : 'w-8 h-8 sm:w-10 sm:h-10 stroke-[1.5]']" />
    </div>

    <div class="space-y-1 max-w-md mx-auto">
      <p
        class="font-black text-slate-800 dark:text-slate-200"
        :class="[compact ? 'text-xs sm:text-sm' : 'text-sm sm:text-base']"
      >
        {{ title || $t('common.server_error') }}
      </p>
      <p
        v-if="message"
        class="text-slate-500 dark:text-slate-400 font-medium leading-relaxed"
        :class="[compact ? 'text-[11px]' : 'text-xs sm:text-sm']"
      >
        {{ message }}
      </p>
    </div>

    <div v-if="canRetry" class="pt-2 flex justify-center">
      <BaseButton
        variant="outline"
        size="sm"
        :icon="RefreshCw"
        :loading="loading"
        class="coarse:min-h-[44px] coarse:min-w-[44px] border-rose-300 dark:border-rose-800/80 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30"
        @click="$emit('retry')"
      >
        {{ retryLabel || $t('connectivity.retry') }}
      </BaseButton>
    </div>
  </div>
</template>
