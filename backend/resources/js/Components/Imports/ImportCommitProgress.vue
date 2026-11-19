<script setup>
import { Loader2 } from 'lucide-vue-next';
</script>

<template>
  <div
    class="p-4 rounded-2xl border bg-white dark:bg-slate-900 space-y-3 font-tajawal"
    style="border-color: var(--color-primary-border)"
    role="status"
    aria-live="polite"
    data-testid="import-commit-progress"
  >
    <div class="flex items-center gap-2 text-sm font-black text-slate-900 dark:text-white">
      <Loader2 class="w-4 h-4 animate-spin" style="color: var(--color-primary)" />
      {{ $t('import.ui.committing_title') }}
    </div>
    <div class="relative h-2 rounded-full overflow-hidden bg-slate-100 dark:bg-slate-800">
      <div class="import-progress-bar absolute inset-y-0 w-1/3 rounded-full" style="background: var(--color-primary)" />
    </div>
    <p class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('import.ui.committing_text') }}</p>
  </div>
</template>

<style scoped>
.import-progress-bar {
  inset-inline-start: 0;
  animation: import-progress 1.2s ease-in-out infinite;
}
@keyframes import-progress {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(300%);
  }
}
[dir='rtl'] .import-progress-bar {
  animation-name: import-progress-rtl;
}
@keyframes import-progress-rtl {
  0% {
    transform: translateX(100%);
  }
  100% {
    transform: translateX(-300%);
  }
}
@media (prefers-reduced-motion: reduce) {
  .import-progress-bar {
    animation-duration: 3s;
  }
}
</style>
