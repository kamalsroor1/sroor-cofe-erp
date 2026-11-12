<script setup>
import { computed } from 'vue';
import { CalendarClock, ShieldAlert } from 'lucide-vue-next';
import { useFormatters } from '../../Composables/useFormatters';

const props = defineProps({
  // result of helpers/returnWindow.returnWindowStatus (limited windows only)
  status: { type: Object, required: true },
  maxDays: { type: [Number, String], default: null },
  // the user holds returns.override_window: past the window they may still save
  canOverride: { type: Boolean, default: false },
});

const { formatDate } = useFormatters();
const lastDay = computed(() => formatDate(props.status.lastDay));
</script>

<template>
  <div
    v-if="status.expired"
    role="status"
    data-testid="return-window-expired"
    class="flex items-start gap-2.5 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-3.5 text-xs font-bold text-amber-700 dark:text-amber-400"
  >
    <ShieldAlert class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
    <span>{{
      $t(canOverride ? 'returns.window_expired_notice_override' : 'returns.window_expired_notice', { days: maxDays })
    }}</span>
  </div>
  <div
    v-else
    data-testid="return-window-last-day"
    class="flex items-start gap-2.5 rounded-2xl border border-slate-200 bg-slate-50 p-3.5 text-xs font-bold text-slate-600 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300"
  >
    <CalendarClock class="mt-0.5 h-4 w-4 shrink-0 text-theme-primary" aria-hidden="true" />
    <span>{{ $t('returns.window_last_day', { date: lastDay }) }}</span>
  </div>
</template>
