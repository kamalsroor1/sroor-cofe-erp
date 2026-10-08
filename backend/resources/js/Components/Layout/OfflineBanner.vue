<script setup>
import { computed, ref } from 'vue';
import { WifiOff, ServerCrash, RefreshCw } from 'lucide-vue-next';
import { useConnectivity } from '../../Composables/useConnectivity';

const { isOnline, isBrowserOnline, checkNow } = useConnectivity();

const isChecking = ref(false);

const titleKey = computed(() =>
  isBrowserOnline.value ? 'connectivity.server_unreachable_title' : 'connectivity.offline_title'
);
const icon = computed(() => (isBrowserOnline.value ? ServerCrash : WifiOff));

const retry = async () => {
  if (isChecking.value) return;
  isChecking.value = true;
  try {
    await checkNow();
  } finally {
    isChecking.value = false;
  }
};
</script>

<template>
  <Transition
    enter-active-class="transition duration-200 ease-out"
    enter-from-class="opacity-0 -translate-y-2"
    leave-active-class="transition duration-150 ease-in"
    leave-to-class="opacity-0 -translate-y-2"
  >
    <div
      v-if="!isOnline"
      role="alert"
      aria-live="assertive"
      data-testid="offline-banner"
      class="no-print shrink-0 w-full flex items-center gap-3 px-3 sm:px-4 py-2 border-b border-rose-200 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/60 text-rose-800 dark:text-rose-200"
    >
      <span
        class="shrink-0 w-9 h-9 rounded-xl flex items-center justify-center bg-rose-100 dark:bg-rose-900/50 text-rose-600 dark:text-rose-300"
      >
        <component :is="icon" class="w-5 h-5" aria-hidden="true" />
      </span>

      <div class="flex-1 min-w-0 text-start">
        <p class="text-sm font-black leading-tight">{{ $t(titleKey) }}</p>
        <p class="text-xs font-bold leading-snug text-rose-700/90 dark:text-rose-300/80">
          {{ $t('connectivity.offline_desc') }}
        </p>
      </div>

      <button
        v-if="isBrowserOnline"
        type="button"
        data-testid="offline-banner-retry"
        :disabled="isChecking"
        @click="retry"
        class="shrink-0 min-h-[44px] min-w-[44px] px-3 rounded-xl flex items-center justify-center gap-2 text-xs font-black bg-white dark:bg-slate-900 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-200 active:scale-95 transition disabled:opacity-60 cursor-pointer"
      >
        <RefreshCw class="w-4 h-4" :class="isChecking ? 'animate-spin' : ''" aria-hidden="true" />
        <span class="hidden sm:inline">
          {{ isChecking ? $t('connectivity.checking') : $t('connectivity.retry') }}
        </span>
        <span class="sr-only sm:hidden">{{ $t('connectivity.retry') }}</span>
      </button>
    </div>
  </Transition>
</template>
