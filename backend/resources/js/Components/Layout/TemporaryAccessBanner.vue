<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { CheckCircle2, Clock, Info, X, XCircle } from 'lucide-vue-next';
import { useTemporaryAccess } from '@/Composables/useTemporaryAccess';
import { useFormatters } from '@/Composables/useFormatters';
import { useTrans } from '@/Composables/useTrans';

const { t } = useTrans();
const { formatDate } = useFormatters();
const { banner, invoiceId, dismissBanner } = useTemporaryAccess();

const TONE = {
  info: 'bg-theme-primary/10 border-theme-primary text-teal-900 dark:text-teal-100',
  warning: 'bg-amber-50 border-amber-500 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200',
  success: 'bg-emerald-50 border-emerald-500 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200',
  danger: 'bg-rose-50 border-rose-500 text-rose-900 dark:bg-rose-500/10 dark:text-rose-200',
  neutral: 'bg-slate-100 border-slate-400 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
};
const ICON = { info: Clock, warning: Clock, success: CheckCircle2, danger: XCircle, neutral: Info };
const CHIP = {
  info: 'bg-theme-primary text-slate-950',
  warning: 'bg-amber-500 text-amber-950',
};

// Sentence without the ticking countdown: the polite announcement only changes with the state.
const text = computed(() => {
  if (!banner.value) return '';
  const params = { ...banner.value.params };
  if (params.end) params.end = formatDate(params.end);
  return t(banner.value.textKey, params);
});
const actionLabelKey = computed(() =>
  banner.value?.action === 'upload_again'
    ? 'subscription.billing.pay.upload_again'
    : 'subscription.billing.grace.details'
);
const target = computed(() => ({ name: 'billing.index', query: { invoice: invoiceId.value } }));
</script>

<template>
  <div
    v-if="banner"
    data-testid="temporary-access-banner"
    :data-state="banner.state"
    class="no-print shrink-0 flex flex-wrap items-center gap-x-3 gap-y-1 border-y sm:border sm:mx-4 sm:mt-2 sm:rounded-xl px-3 py-1.5 font-tajawal"
    :class="TONE[banner.tone]"
  >
    <component :is="ICON[banner.tone]" class="w-5 h-5 shrink-0" aria-hidden="true" />
    <p class="flex-1 min-w-0 basis-40 text-sm font-bold leading-snug text-start line-clamp-1 sm:line-clamp-none">
      {{ text }}
    </p>
    <span
      v-if="banner.countdown"
      class="shrink-0 rounded-full px-3 py-1 text-xs font-black"
      :class="CHIP[banner.tone]"
      aria-live="off"
    >
      {{ $t('subscription.billing.grace.remaining', { time: banner.countdown }) }}
    </span>
    <RouterLink
      v-if="banner.action"
      :to="target"
      data-testid="temporary-access-banner-action"
      class="shrink-0 min-h-11 inline-flex items-center px-2 text-sm font-black underline underline-offset-2"
    >
      <span class="sm:hidden">{{
        banner.action === 'details' ? $t('subscription.billing.grace.details_short') : $t(actionLabelKey)
      }}</span>
      <span class="hidden sm:inline">{{ $t(actionLabelKey) }}</span>
    </RouterLink>
    <button
      v-if="banner.dismissible"
      type="button"
      class="shrink-0 min-w-11 min-h-11 inline-flex items-center justify-center rounded-lg cursor-pointer"
      :aria-label="$t('subscription.billing.grace.dismiss')"
      @click="dismissBanner"
    >
      <X class="w-4 h-4" />
    </button>
    <p class="sr-only" role="status">{{ text }}</p>
  </div>
</template>
