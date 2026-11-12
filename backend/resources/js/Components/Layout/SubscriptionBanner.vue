<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { Clock, AlertTriangle, Lock } from 'lucide-vue-next';
import { useAppConfigStore } from '../../stores/appConfig';
import { describeSubscriptionBanner } from '../../helpers/subscriptionBanner';

const appConfig = useAppConfigStore();
const banner = computed(() => describeSubscriptionBanner(appConfig.subscriptionBanner));

const TONE_CLASSES = {
  info: 'border-sky-200 dark:border-sky-900/60 bg-sky-50 dark:bg-sky-950/60 text-info-fg',
  warning: 'border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/60 text-warning-fg',
  danger: 'border-rose-200 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/60 text-danger-fg',
};
const ICONS = { info: Clock, warning: AlertTriangle, danger: Lock };
</script>

<template>
  <div
    v-if="banner"
    role="status"
    data-testid="subscription-banner"
    :data-state="banner.state"
    class="no-print shrink-0 w-full flex items-center gap-3 px-3 sm:px-4 py-1 border-b"
    :class="TONE_CLASSES[banner.tone]"
  >
    <component :is="ICONS[banner.tone]" class="w-5 h-5 shrink-0" aria-hidden="true" />
    <p class="flex-1 min-w-0 text-sm font-bold leading-snug text-start">
      {{ $t(banner.textKey, banner.params) }}
    </p>
    <RouterLink
      v-if="banner.actionPath"
      :to="banner.actionPath"
      data-testid="subscription-banner-action"
      class="shrink-0 min-h-[44px] inline-flex items-center px-3 text-sm font-black underline underline-offset-2"
    >
      {{ $t('subscription.banner.action') }}
    </RouterLink>
  </div>
</template>
