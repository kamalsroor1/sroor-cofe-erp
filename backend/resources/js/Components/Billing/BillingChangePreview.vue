<script setup>
import { computed } from 'vue';
import { AlertCircle, RefreshCw } from 'lucide-vue-next';
import BaseButton from '@/Components/Common/BaseButton.vue';
import Skeleton from '@/Components/Common/Skeletons/Skeleton.vue';
import { useFormatters } from '@/Composables/useFormatters';
import { previewNoteKey, timelineSteps } from '@/helpers/billingChange';

const props = defineProps({
  preview: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  failed: { type: Boolean, default: false },
  message: { type: String, default: '' },
});

defineEmits(['retry']);

const { formatMoney, formatCurrency, formatDate } = useFormatters();

const steps = computed(() => timelineSteps(props.preview));
const noteKey = computed(() => previewNoteKey(props.preview));
const reloading = computed(() => props.loading && !!props.preview);
const hasNumbers = computed(() => !!props.preview && props.preview.total !== null);

const stepLabelKey = { today: 'timeline_today', end_fixed: 'timeline_end_fixed', new_end: 'timeline_new_end' };
const stepText = (step, t) =>
  t(`subscription.billing.change.${stepLabelKey[step.key]}`, step.date ? { date: formatDate(step.date) } : {});

const isProration = (line) => line.kind === 'proration';
</script>

<template>
  <section
    class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-3 space-y-3"
    aria-live="polite"
    :aria-busy="loading"
  >
    <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400">
      {{ $t('subscription.billing.change.preview_title') }}
    </h3>

    <div
      v-if="failed"
      class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-rose-500 bg-rose-50 dark:bg-rose-500/10 p-3 text-sm font-bold text-rose-900 dark:text-rose-200"
      role="alert"
    >
      <span class="flex items-center gap-2 min-w-0">
        <AlertCircle class="w-4 h-4 shrink-0" />
        {{ message || $t('subscription.billing.change.preview_error') }}
      </span>
      <BaseButton variant="outline" :icon="RefreshCw" :loading="loading" @click="$emit('retry')">
        {{ $t('subscription.billing.page.retry') }}
      </BaseButton>
    </div>

    <div v-else-if="!preview" class="space-y-2" aria-hidden="true">
      <Skeleton height="h-4" width="w-2/3" />
      <Skeleton height="h-4" width="w-1/2" />
      <Skeleton height="h-8" width="w-1/3" />
      <Skeleton height="h-4" />
    </div>

    <div v-else-if="hasNumbers" class="space-y-3 transition-opacity" :class="reloading ? 'opacity-50' : ''">
      <ul class="space-y-2">
        <li v-for="(line, index) in preview.lines" :key="`${line.kind}-${index}`" class="space-y-0.5">
          <div class="flex items-start justify-between gap-3 text-sm font-bold text-slate-800 dark:text-slate-200">
            <span class="min-w-0">{{ line.label }}</span>
            <span class="shrink-0" dir="ltr">{{ formatCurrency(line.amount) }}</span>
          </div>
          <p
            v-if="isProration(line) && line.params?.new !== undefined"
            class="text-xs font-medium text-slate-500 dark:text-slate-400"
            dir="auto"
          >
            {{
              $t('subscription.billing.change.line_proration_formula', {
                new: formatMoney(line.params.new),
                old: formatMoney(line.params.old),
                days: line.params.days,
                period_days: line.params.period_days,
              })
            }}
          </p>
        </li>
      </ul>

      <div
        class="flex items-center justify-between gap-3 border-t border-slate-200 dark:border-slate-800 pt-3 text-lg font-black text-slate-950 dark:text-white"
      >
        <span>{{ $t('subscription.billing.change.total') }}</span>
        <span class="relative" dir="ltr">
          {{ formatCurrency(preview.total) }}
          <span
            v-if="reloading"
            class="absolute inset-x-0 -bottom-1 h-1 rounded-full bg-theme-primary/40 animate-pulse"
            aria-hidden="true"
          ></span>
        </span>
      </div>

      <ol v-if="steps.length" class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-bold text-theme-primary">
        <template v-for="(step, index) in steps" :key="step.key">
          <li class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-theme-primary shrink-0" aria-hidden="true"></span>
            <span dir="auto">{{ stepText(step, $t) }}</span>
          </li>
          <li v-if="index < steps.length - 1" class="h-px w-6 bg-theme-primary/50" aria-hidden="true"></li>
        </template>
      </ol>

      <p v-if="noteKey" class="text-xs font-medium text-slate-600 dark:text-slate-300 leading-relaxed">
        {{ $t(`subscription.billing.change.${noteKey}`, { plan: preview.plan?.name || '' }) }}
      </p>
      <p v-if="preview.estimate" class="text-xs font-medium text-slate-500 dark:text-slate-400 leading-relaxed">
        {{ $t('subscription.billing.change.estimate_note') }}
      </p>
    </div>
  </section>
</template>
