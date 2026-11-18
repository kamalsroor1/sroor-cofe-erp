<script setup>
import { computed } from 'vue';
import { Gauge, TrendingUp } from 'lucide-vue-next';
import BaseButton from '@/Components/Common/BaseButton.vue';
import InlineErrorBar from '@/Components/Common/InlineErrorBar.vue';
import Skeleton from '@/Components/Common/Skeletons/Skeleton.vue';
import { useFormatters } from '@/Composables/useFormatters';
import { storageParts, usageBarClass } from '@/helpers/billing';
import { useTrans } from '@/Composables/useTrans';

const props = defineProps({
  rows: { type: Array, default: () => [] },
  level: { type: String, default: 'none' },
  isLoading: { type: Boolean, default: false },
  error: { type: String, default: '' },
});

defineEmits(['retry']);

const { t } = useTrans();
const { formatNumber, formatDate } = useFormatters();

const isVisible = computed(() => props.isLoading || !!props.error || props.rows.length > 0);

const formatAmount = (row, value) => {
  if (row.unit !== 'mb') return formatNumber(value);
  const { value: amount, unit } = storageParts(value);
  return t(`subscription.billing.usage.unit_${unit}`, { value: formatNumber(amount) });
};

const frameClass = computed(() => {
  if (props.level === 'full') return 'border-rose-300 dark:border-rose-900/70';
  if (props.level === 'warn') return 'border-amber-300 dark:border-amber-900/70';
  return 'border-slate-200 dark:border-slate-800';
});

const hint = computed(() => {
  if (props.level === 'full') return t('subscription.usage_widget.hint_full');
  if (props.level === 'warn') return t('subscription.usage_widget.hint_warn');
  return '';
});
</script>

<template>
  <section
    v-if="isVisible"
    class="rounded-3xl border bg-white dark:bg-slate-900 p-4 sm:p-5 space-y-4 shadow-xs font-tajawal"
    :class="frameClass"
    data-testid="plan-usage-widget"
    :data-level="level"
    :aria-busy="isLoading"
  >
    <InlineErrorBar v-if="error && !rows.length" :message="error" @retry="$emit('retry')" />

    <template v-else-if="isLoading && !rows.length">
      <Skeleton width="w-48" height="h-5" />
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <div v-for="n in 3" :key="n" class="space-y-2">
          <Skeleton width="w-2/3" height="h-3" />
          <Skeleton height="h-2.5" rounded="rounded-full" />
        </div>
      </div>
    </template>

    <template v-else>
      <header class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2.5 min-w-0">
          <Gauge class="w-5 h-5 shrink-0 text-theme-primary" />
          <div class="min-w-0">
            <h2 class="font-black text-sm sm:text-base text-slate-900 dark:text-white">
              {{ $t('subscription.usage_widget.title') }}
            </h2>
            <p
              v-if="hint"
              class="text-xs font-bold"
              :class="level === 'full' ? 'text-rose-600 dark:text-rose-400' : 'text-amber-700 dark:text-amber-400'"
              role="status"
            >
              {{ hint }}
            </p>
          </div>
        </div>
        <BaseButton
          :variant="level === 'none' || level === 'ok' ? 'secondary' : 'primary'"
          :icon="TrendingUp"
          :to="{ name: 'billing.index' }"
          data-testid="plan-usage-upgrade"
        >
          {{ $t('subscription.usage_widget.upgrade') }}
        </BaseButton>
      </header>

      <ul class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-x-6 gap-y-4">
        <li v-for="row in rows" :key="row.resource" class="space-y-1.5" :data-resource="row.resource">
          <div class="flex items-center justify-between gap-2 text-xs">
            <span class="font-black text-slate-800 dark:text-slate-200 truncate">{{ row.label }}</span>
            <span class="font-bold text-slate-500 dark:text-slate-400 whitespace-nowrap">
              {{
                $t('subscription.billing.usage.of_limit', {
                  used: formatAmount(row, row.used),
                  limit: formatAmount(row, row.limit),
                })
              }}
            </span>
          </div>
          <div
            class="h-2.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden"
            role="progressbar"
            :aria-label="row.label"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-valuenow="Math.min(100, row.percent ?? 0)"
          >
            <div
              class="h-full rounded-full transition-all duration-500"
              :class="usageBarClass(row.percent)"
              :style="{ width: `${Math.min(100, row.percent ?? 0)}%` }"
            />
          </div>
          <p v-if="row.resets_at" class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
            {{ $t('subscription.billing.usage.resets_on', { date: formatDate(row.resets_at) }) }}
          </p>
        </li>
      </ul>
    </template>
  </section>
</template>
