<script setup>
import { Gauge } from 'lucide-vue-next';
import EmptyState from '@/Components/Common/EmptyState.vue';
import { useFormatters } from '@/Composables/useFormatters';
import { usageBarClass } from '@/helpers/billing';

defineProps({
  usage: { type: Array, default: () => [] },
});

const { formatNumber } = useFormatters();
</script>

<template>
  <section
    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 space-y-4 shadow-xs font-tajawal"
  >
    <header class="flex items-center gap-2.5">
      <Gauge class="w-5 h-5 text-theme-primary" />
      <h2 class="font-black text-sm sm:text-base text-slate-900 dark:text-white">
        {{ $t('subscription.billing.usage.title') }}
      </h2>
    </header>

    <EmptyState v-if="!usage.length" :icon="Gauge" :title="$t('subscription.billing.usage.empty')" />

    <ul v-else class="space-y-4">
      <li v-for="row in usage" :key="row.resource" class="space-y-1.5">
        <div class="flex items-center justify-between gap-2 text-xs">
          <span class="font-black text-slate-800 dark:text-slate-200 truncate">{{ row.label }}</span>
          <span class="font-bold text-slate-500 dark:text-slate-400 whitespace-nowrap">
            <template v-if="row.limit === null || row.limit === undefined">
              {{ formatNumber(row.used) }} / {{ $t('subscription.billing.usage.unlimited') }}
            </template>
            <template v-else>
              {{
                $t('subscription.billing.usage.of_limit', {
                  used: formatNumber(row.used),
                  limit: formatNumber(row.limit),
                })
              }}
            </template>
          </span>
        </div>
        <div
          class="h-2.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden"
          role="progressbar"
          :aria-label="row.label"
          aria-valuemin="0"
          aria-valuemax="100"
          :aria-valuenow="row.percent ?? 0"
        >
          <div
            class="h-full rounded-full transition-all duration-500"
            :class="usageBarClass(row.percent)"
            :style="{ width: `${Math.min(100, row.percent ?? 0)}%` }"
          />
        </div>
      </li>
    </ul>
  </section>
</template>
