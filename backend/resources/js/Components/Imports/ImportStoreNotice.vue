<script setup>
import { MapPin, AlertTriangle } from 'lucide-vue-next';

defineProps({
  store: { type: Object, default: null },
  showStock: { type: Boolean, default: false },
});
</script>

<template>
  <div
    v-if="!store"
    class="flex items-start gap-3 p-4 rounded-2xl border border-amber-300 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 text-amber-800 dark:text-amber-300 font-tajawal"
    role="alert"
  >
    <AlertTriangle class="w-5 h-5 shrink-0 mt-0.5" />
    <div class="space-y-1">
      <p class="font-black text-sm">{{ $t('import.ui.no_store_title') }}</p>
      <p class="text-xs font-bold">{{ $t('import.ui.no_store_text') }}</p>
    </div>
  </div>
  <div
    v-else
    class="flex items-start gap-3 p-4 rounded-2xl border font-tajawal"
    style="background: var(--color-primary-light); border-color: var(--color-primary-border)"
  >
    <MapPin class="w-5 h-5 shrink-0 mt-0.5" style="color: var(--color-primary)" />
    <div class="space-y-1">
      <p class="font-black text-sm text-slate-900 dark:text-white">
        {{ $t('import.ui.active_store', { store: store.name }) }}
      </p>
      <p v-if="showStock" class="text-xs font-bold text-slate-600 dark:text-slate-300">
        {{ $t('import.ui.opening_stock_into') }}
      </p>
    </div>
  </div>
</template>
