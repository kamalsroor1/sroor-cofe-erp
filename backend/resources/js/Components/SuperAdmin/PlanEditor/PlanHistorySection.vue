<script setup>
import { History } from 'lucide-vue-next';
import EmptyState from '../../Common/EmptyState.vue';
import ErrorState from '../../Common/ErrorState.vue';
import Skeleton from '../../Common/Skeletons/Skeleton.vue';
import { usePlanChanges } from '../../../Composables/usePlanChanges';
import { useFormatters } from '../../../Composables/useFormatters';

const props = defineProps({
  planId: { type: Number, required: true },
});

const { formatDateTime } = useFormatters();
const { changes, isLoading, hasError, fetchChanges } = usePlanChanges(props.planId);
</script>

<template>
  <section class="space-y-3" data-testid="plan-history">
    <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('plan_console.history.title') }}</h3>

    <div v-if="isLoading" class="space-y-3" aria-busy="true">
      <div
        v-for="n in 3"
        :key="n"
        class="p-3 rounded-xl border border-slate-200 dark:border-slate-800 space-y-2 bg-slate-50 dark:bg-slate-900"
      >
        <Skeleton width="w-1/3" height="h-4" />
        <Skeleton width="w-2/3" height="h-3" />
      </div>
    </div>

    <ErrorState v-else-if="hasError" :title="$t('plan_console.history.load_failed')" @retry="fetchChanges" />

    <EmptyState v-else-if="!changes.length" :icon="History" :title="$t('plan_console.history.empty')" />

    <ul v-else class="space-y-3">
      <li
        v-for="change in changes"
        :key="change.id"
        class="p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 space-y-2"
      >
        <div class="flex flex-wrap items-center justify-between gap-2">
          <span class="text-xs font-black text-slate-900 dark:text-white">
            {{
              change.event === 'plan_created' ? $t('plan_console.history.created') : $t('plan_console.history.updated')
            }}
          </span>
          <span class="text-[11px] font-mono text-slate-500 dark:text-slate-400" dir="ltr">
            {{ formatDateTime(change.created_at) }}
          </span>
        </div>
        <p class="text-[11px] font-medium text-slate-600 dark:text-slate-300">
          {{ change.operator || $t('plan_console.history.unknown_operator') }}
        </p>
        <div v-if="change.changed.length" class="space-y-1">
          <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
            {{ $t('plan_console.history.changed_fields') }}
          </p>
          <div class="flex flex-wrap gap-1.5" dir="ltr">
            <code
              v-for="field in change.changed"
              :key="field"
              class="px-2 py-0.5 rounded-md text-[10px] font-mono bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200"
            >
              {{ field }}
            </code>
          </div>
        </div>
      </li>
    </ul>
  </section>
</template>
