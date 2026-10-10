<script setup>
import { ShieldCheck, Info } from 'lucide-vue-next';

defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  notice: { type: String, default: '' },
});
</script>

<template>
  <div
    class="min-h-full w-full overflow-y-auto bg-slate-100 dark:bg-slate-950 flex items-center justify-center px-4 py-8 font-tajawal"
  >
    <div class="w-full max-w-md space-y-5">
      <div class="flex flex-col items-center text-center gap-3">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-theme-primary text-slate-950 shadow-lg">
          <ShieldCheck class="w-7 h-7" aria-hidden="true" />
        </div>
        <div class="space-y-1">
          <p class="text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
            {{ $t('super.platform_title') }}
          </p>
          <h1 class="text-xl font-black text-slate-900 dark:text-white">{{ title }}</h1>
          <p v-if="subtitle" class="text-xs leading-relaxed text-slate-600 dark:text-slate-400">{{ subtitle }}</p>
        </div>
      </div>

      <div
        v-if="notice"
        role="status"
        class="flex items-start gap-2 rounded-2xl border border-amber-300 bg-amber-50 p-3 text-xs font-bold text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300"
      >
        <Info class="w-4 h-4 shrink-0 mt-0.5" aria-hidden="true" />
        <span>{{ notice }}</span>
      </div>

      <div
        class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-800 dark:bg-slate-900 sm:p-6"
      >
        <slot />
      </div>

      <div v-if="$slots.footer" class="text-center text-xs">
        <slot name="footer" />
      </div>
    </div>
  </div>
</template>
