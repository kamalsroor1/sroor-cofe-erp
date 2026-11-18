<script setup>
import { computed } from 'vue';
import { ChevronRight } from 'lucide-vue-next';
import { useFormatters } from '../../../Composables/useFormatters';
import { useTrans } from '../../../Composables/useTrans';

const props = defineProps({
  row: { type: Object, required: true },
});

const { formatNumber } = useFormatters();
const { t } = useTrans();

const unit = computed(() => (props.row.unit === 'mb' ? ` ${t('plan_console.unit_mb')}` : ''));
const usage = computed(() => `${formatNumber(props.row.used)} / ${formatNumber(props.row.limit)}${unit.value}`);
const barWidth = computed(() => `${Math.min(100, Math.max(0, Number(props.row.percent) || 0))}%`);
</script>

<template>
  <router-link
    :to="`/super-admin/tenants/${row.tenant_id}`"
    :aria-label="$t('plan_console.near_limit.open_shop', { name: row.tenant_name })"
    class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center p-4 min-h-[44px] rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/60 hover:border-[var(--color-primary-border)] transition"
  >
    <div class="md:col-span-4 min-w-0">
      <p class="text-sm font-black text-slate-900 dark:text-white truncate">{{ row.tenant_name }}</p>
      <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400 truncate">{{ row.plan.name }}</p>
    </div>

    <p class="md:col-span-2 text-xs font-bold text-slate-700 dark:text-slate-200">{{ row.label }}</p>

    <div class="md:col-span-5 space-y-1.5">
      <div class="h-2.5 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden" aria-hidden="true">
        <div
          class="h-full rounded-full"
          :class="row.over_limit ? 'bg-rose-500' : 'bg-amber-500'"
          :style="{ width: barWidth }"
        ></div>
      </div>
      <div class="flex flex-wrap items-center justify-between gap-2">
        <span class="text-xs font-mono font-bold text-slate-700 dark:text-slate-200" dir="ltr">{{ usage }}</span>
        <span
          class="px-2 py-0.5 rounded-full text-[10px] font-black border"
          :class="
            row.over_limit
              ? 'bg-rose-500/10 border-rose-500/30 text-rose-600 dark:text-rose-400'
              : 'bg-amber-500/10 border-amber-500/30 text-amber-600 dark:text-amber-400'
          "
        >
          {{ row.over_limit ? $t('plan_console.near_limit.over_limit') : `${formatNumber(row.percent)}%` }}
        </span>
      </div>
    </div>

    <ChevronRight
      class="hidden md:block md:col-span-1 w-4 h-4 text-slate-400 justify-self-end rtl:rotate-180"
      aria-hidden="true"
    />
  </router-link>
</template>
