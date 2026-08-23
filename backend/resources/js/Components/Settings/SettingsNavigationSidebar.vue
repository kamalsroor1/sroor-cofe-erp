<template>
  <div class="space-y-2.5 font-tajawal">
    <!-- Desktop Sidebar Menu -->
    <div class="p-3 bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl space-y-1.5">
      <div class="px-3.5 py-2 text-[11px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-wider">
        {{ $t('settings.settings_sections_title') }}
      </div>

      <button
        v-for="sec in sections"
        :key="sec.id"
        type="button"
        @click="$emit('select-section', sec.id)"
        class="w-full p-3 rounded-2xl text-xs font-bold transition-all flex items-center justify-between gap-3 text-start cursor-pointer group select-none"
        :class="selectedSection === sec.id ? 'bg-theme-primary text-white shadow-theme-primary font-black' : 'text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800'"
      >
        <div class="flex items-center gap-3 min-w-0">
          <div
            class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
            :class="selectedSection === sec.id ? 'bg-white/20 text-white' : sec.iconBg"
          >
            <component :is="sec.icon" class="w-4.5 h-4.5" :class="selectedSection === sec.id ? 'text-white' : sec.iconColor" />
          </div>
          <div class="min-w-0">
            <div class="truncate">{{ sec.label }}</div>
            <div class="text-[10px] font-normal truncate" :class="selectedSection === sec.id ? 'text-white/80' : 'text-slate-400 dark:text-slate-500'">
              {{ sec.subtitle }}
            </div>
          </div>
        </div>

        <ChevronLeft class="w-4 h-4 opacity-40 group-hover:opacity-100 transition-opacity shrink-0" />
      </button>
    </div>

    <!-- Quick Info Box -->
    <div class="p-4 bg-white dark:bg-slate-800/60 rounded-3xl border border-slate-200 dark:border-slate-800/80 text-xs text-slate-500 dark:text-slate-400 space-y-2 shadow-xs">
      <div class="flex items-center gap-2 text-slate-800 dark:text-slate-200 font-bold">
        <ShieldCheck class="w-4 h-4 text-emerald-500 dark:text-emerald-400" />
        <span>{{ $t('settings.secure_management_title') }}</span>
      </div>
      <p class="text-[11px] leading-relaxed">
        {{ $t('settings.secure_management_desc') }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { ChevronLeft, ShieldCheck } from 'lucide-vue-next';

defineProps({
  sections: { type: Array, default: () => [] },
  selectedSection: { type: String, default: 'branding' },
});

defineEmits(['select-section']);
</script>
