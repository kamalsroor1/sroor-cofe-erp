<script setup>
import { computed } from 'vue';
import { AlertTriangle, Lock, TrendingUp } from 'lucide-vue-next';
import BaseButton from '@/Components/Common/BaseButton.vue';
import { useFormatters } from '@/Composables/useFormatters';
import { isPosInvoiceBannerVisible } from '@/helpers/planLimit';

const props = defineProps({
  // GET /pos/invoice-limit: { limit, used, remaining, percent, reached, resets_at }
  status: { type: Object, default: null },
  reached: { type: Boolean, default: false },
  nearLimit: { type: Boolean, default: false },
  canUpgrade: { type: Boolean, default: false },
});

const { formatNumber, formatDate } = useFormatters();

const isVisible = computed(() => isPosInvoiceBannerVisible(props.status, props.reached, props.nearLimit));
</script>

<template>
  <div
    v-if="isVisible"
    role="status"
    class="shrink-0 flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-2 border-b font-tajawal"
    :class="
      reached
        ? 'border-rose-200 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/60 text-rose-800 dark:text-rose-200'
        : 'border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/60 text-amber-800 dark:text-amber-200'
    "
    :data-state="reached ? 'reached' : 'near'"
    data-testid="pos-invoice-limit-banner"
  >
    <component :is="reached ? Lock : AlertTriangle" class="w-5 h-5 shrink-0" aria-hidden="true" />
    <div class="flex-1 min-w-[200px] text-start">
      <p class="text-sm font-black leading-snug">
        <template v-if="reached">
          {{ $t('subscription.pos_limit.reached_title', { max: formatNumber(status.limit) }) }}
        </template>
        <template v-else>
          {{
            $t('subscription.pos_limit.near_title', {
              used: formatNumber(status.used),
              max: formatNumber(status.limit),
            })
          }}
        </template>
      </p>
      <p class="text-xs font-bold leading-snug opacity-90">
        <template v-if="reached">
          {{
            $t('subscription.pos_limit.reached_hint', { date: status.resets_at ? formatDate(status.resets_at) : '' })
          }}
        </template>
        <template v-else>
          {{ $t('subscription.pos_limit.near_hint', { remaining: formatNumber(status.remaining) }) }}
        </template>
        {{ canUpgrade ? '' : $t('subscription.alerts.ask_owner_to_upgrade') }}
      </p>
    </div>
    <BaseButton
      v-if="canUpgrade"
      :variant="reached ? 'primary' : 'secondary'"
      :icon="TrendingUp"
      :to="{ name: 'billing.index' }"
      data-testid="pos-invoice-limit-upgrade"
    >
      {{ $t('subscription.usage_widget.upgrade') }}
    </BaseButton>
  </div>
</template>
