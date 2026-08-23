<template>
  <DashboardSectionCard
    :title="'⚡ ' + $t('dashboard.peak_hours')"
    :subtitle="$t('dashboard.peak_hours_subtitle')"
    :icon="Zap"
    icon-bg="bg-purple-500/10"
    icon-color="text-purple-500"
    header-class="flex-col sm:flex-row"
  >
    <template #action>
      <div v-if="peakHour && parseFloat(peakHour.sales) > 0" class="flex items-center gap-2 px-3.5 py-1.5 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-600 dark:text-purple-400 text-xs font-bold font-tajawal">
        <span>{{ $t('dashboard.peak_hour_badge') }}:</span>
        <span class="font-black font-mono">({{ peakHour.label }})</span>
        <span class="font-mono text-emerald-500 font-bold">[{{ formatMoney(peakHour.sales) }} {{ $t('common.currency') }}]</span>
      </div>
    </template>
    <div class="pt-3 pb-1 overflow-x-auto scrollbar-none">
      <div class="min-w-[640px] sm:min-w-[700px]">
        <div class="h-28 flex items-end gap-1 sm:gap-1.5 justify-between border-b border-slate-200 dark:border-slate-800 pb-2">
          <div
            v-for="slot in hourlySales"
            :key="slot.hour"
            @click="toggleSlot(slot.hour)"
            class="flex-1 flex flex-col items-center gap-1 sm:gap-1.5 h-full justify-end group cursor-pointer relative select-none"
          >
            <!-- Tooltip (Hover & Touch Tap) -->
            <div
              class="transition-all duration-200 absolute -top-12 z-20 bg-slate-900 text-white text-[10px] font-mono py-1 px-2 rounded-xl shadow-xl pointer-events-none whitespace-nowrap border border-slate-700"
              :class="activeHour === slot.hour ? 'opacity-100 scale-100' : 'opacity-0 group-hover:opacity-100 scale-95 group-hover:scale-100'"
            >
              <div class="font-bold">{{ slot.label }}: {{ slot.sales_formatted }}</div>
              <div class="text-slate-400 font-sans">{{ slot.invoices }} {{ $t('dashboard.total_invoices') }}</div>
            </div>
            <div class="w-full bg-slate-100 dark:bg-slate-800/80 rounded-lg relative overflow-hidden flex flex-col justify-end h-full">
              <div class="w-full rounded-lg transition-all duration-300 group-hover:brightness-125"
                :style="{ height: `${Math.max(slot.intensity, 6)}%`, backgroundColor: slot.intensity > 70 ? '#a855f7' : (slot.intensity > 30 ? 'var(--color-primary, #10b981)' : '#64748b') }"
              ></div>
            </div>
            <div
              class="text-[9px] font-mono text-center text-slate-400 dark:text-slate-500 group-hover:text-purple-400 transition-colors"
              :class="{ 'text-purple-500 font-black': activeHour === slot.hour }"
            >
              {{ slot.label }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardSectionCard>
</template>
<script setup>
import { ref } from 'vue';
import { Zap } from 'lucide-vue-next';
import DashboardSectionCard from '../Common/DashboardSectionCard.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();
const activeHour = ref(null);
const toggleSlot = (hour) => {
  activeHour.value = activeHour.value === hour ? null : hour;
};

defineProps({
  hourlySales: { type: Array, default: () => [] },
  peakHour: { type: Object, default: null },
});
</script>