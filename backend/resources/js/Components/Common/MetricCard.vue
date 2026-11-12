<script setup>
import { computed } from 'vue';
import DynamicIcon from './DynamicIcon.vue';

const props = defineProps({
  // --- Core Props (Original) ---
  title: { type: String, required: true },
  value: { type: [String, Number], required: true },
  currency: { type: String, default: '' },
  variant: {
    type: String,
    default: 'default', // 'default' | 'primary' | 'success' | 'danger' | 'warning' | 'cyan' | 'indigo' | 'slate'
  },
  icon: { type: [Object, Function, String], default: null },
  subtitle: { type: String, default: '' },

  // --- Extended Props (Dashboard & compact KPIs) ---
  compact: { type: Boolean, default: false },
  // Strip mode for list pages: one short row (icon, title, value), footer/subtitle move to the tooltip.
  dense: { type: Boolean, default: false },
  // Custom icon background and text color classes (e.g. 'bg-emerald-500/10', 'text-emerald-500')
  iconBg: { type: String, default: 'bg-slate-100 dark:bg-slate-800' },
  iconColor: { type: String, default: 'text-slate-500' },
  // Footer row: two items aligned start & end
  footerLeft: { type: String, default: '' },
  footerRight: { type: String, default: '' },
  footerRightClass: { type: String, default: 'text-slate-600 dark:text-slate-300' },
});

const valueColorClass = computed(() => {
  switch (props.variant) {
    case 'primary':
      return 'text-theme-primary';
    case 'success':
      return 'text-emerald-600 dark:text-emerald-400';
    case 'danger':
      return 'text-rose-600 dark:text-rose-400';
    case 'warning':
      return 'text-theme-primary';
    case 'cyan':
      return 'text-cyan-600 dark:text-cyan-400';
    case 'indigo':
      return 'text-indigo-600 dark:text-indigo-400';
    case 'slate':
      return 'text-slate-700 dark:text-slate-300';
    default:
      return 'text-slate-900 dark:text-white';
  }
});
</script>

<template>
  <div
    class="metric-card @container bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 shadow-xs dark:shadow-xl relative overflow-hidden group font-tajawal transition hover:border-slate-300 dark:hover:border-slate-700 select-none"
    :class="[
      dense
        ? 'p-2.5 rounded-xl'
        : compact
          ? 'p-2.5 sm:p-3.5 rounded-xl sm:rounded-2xl space-y-1'
          : 'p-3 sm:p-5 rounded-2xl sm:rounded-3xl space-y-1 sm:space-y-2',
    ]"
    :title="dense ? [footerLeft, subtitle].filter(Boolean).join(' - ') || undefined : undefined"
    data-metric-card="true"
    :data-dense="dense ? 'true' : undefined"
  >
    <!-- Dense strip layout: icon + title over value, always one short row -->
    <div v-if="dense" class="flex items-center gap-2.5 min-w-0">
      <div
        v-if="icon"
        class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
        :class="[iconBg, iconColor]"
      >
        <DynamicIcon :name="icon" class="w-4 h-4 shrink-0" />
      </div>
      <div class="min-w-0 flex-1">
        <div class="text-[10px] sm:text-[11px] leading-tight font-bold text-slate-500 dark:text-slate-400 line-clamp-2">
          {{ title }}
        </div>
        <div
          class="flex flex-wrap items-baseline gap-x-1 font-black font-mono tracking-tight leading-tight"
          :class="valueColorClass"
        >
          <span class="whitespace-nowrap text-[clamp(0.9rem,9cqw,1.125rem)]" data-metric-value="true">{{ value }}</span>
          <span v-if="currency" class="font-sans text-slate-400 font-bold text-[10px] whitespace-nowrap">
            {{ currency }}
          </span>
        </div>
      </div>
    </div>

    <template v-else>
      <!-- Header: Title + Icon -->
      <div class="flex items-center justify-between gap-1.5 min-w-0">
        <span
          class="font-bold text-slate-500 dark:text-slate-400 line-clamp-2 leading-snug block"
          :class="compact ? 'text-[10px] sm:text-[11px]' : 'text-[11px] sm:text-xs'"
        >
          {{ title }}
        </span>
        <div
          v-if="icon"
          class="flex items-center justify-center shrink-0"
          :class="[
            compact ? 'w-6 h-6 rounded-lg text-xs' : 'w-7 h-7 sm:w-8 sm:h-8 rounded-lg sm:rounded-xl text-sm',
            iconBg,
            iconColor,
          ]"
        >
          <DynamicIcon :name="icon" class="shrink-0" :class="compact ? 'w-3 h-3' : 'w-3.5 h-3.5 sm:w-4 sm:h-4'" />
        </div>
      </div>

      <!-- Main Value -->
      <div class="flex flex-wrap items-baseline gap-x-1 font-black font-mono tracking-tight" :class="valueColorClass">
        <span
          class="whitespace-nowrap leading-tight"
          :class="compact ? 'text-[clamp(0.9rem,10cqw,1.125rem)]' : 'text-[clamp(1rem,11cqw,1.5rem)]'"
          data-metric-value="true"
        >
          {{ value }}
        </span>
        <span
          v-if="currency"
          class="font-sans text-slate-400 font-bold whitespace-nowrap"
          :class="compact ? 'text-[9.5px] sm:text-[10px]' : 'text-[10px] sm:text-xs'"
        >
          {{ currency }}
        </span>
      </div>

      <!-- Footer Row (start + end) -->
      <div
        v-if="footerLeft || footerRight"
        class="font-bold flex items-center justify-between gap-1 text-slate-500 dark:text-slate-400 min-w-0"
        :class="compact ? 'text-[9.5px] sm:text-[10px]' : 'text-[10px] sm:text-[11px]'"
      >
        <span class="truncate">{{ footerLeft }}</span>
        <span v-if="footerRight" class="font-mono font-black shrink-0" :class="footerRightClass">
          {{ footerRight }}
        </span>
      </div>

      <!-- Legacy subtitle (for backward compatibility with other pages) -->
      <p
        v-else-if="subtitle"
        class="text-slate-400 font-medium truncate"
        :class="compact ? 'text-[9.5px] sm:text-[10px]' : 'text-[10px] sm:text-[10.5px]'"
      >
        {{ subtitle }}
      </p>

      <!-- Optional extra rows under the footer (e.g. returns note) -->
      <slot />
    </template>
  </div>
</template>
