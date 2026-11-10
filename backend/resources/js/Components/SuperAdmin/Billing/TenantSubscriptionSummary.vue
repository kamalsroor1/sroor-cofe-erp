<script setup>
import { computed } from 'vue';
import { Crown } from 'lucide-vue-next';
import StatusBadge from '../../Common/StatusBadge.vue';
import { useFormatters } from '../../../Composables/useFormatters';

const props = defineProps({
  subscription: { type: Object, default: null },
  daysLeft: { type: Number, default: null },
});

const { formatDate } = useFormatters();

const STATUS_VARIANTS = { active: 'success', trialing: 'info', past_due: 'warning', pending_payment: 'warning' };

const endsAt = computed(() => props.subscription?.ends_at || props.subscription?.subscription_ends_at);
const statusVariant = computed(() => STATUS_VARIANTS[props.subscription?.status?.value] || 'slate');
</script>

<template>
  <div
    v-if="!subscription?.plan && !subscription?.status"
    class="rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 p-4 text-xs font-bold text-slate-500 dark:text-slate-400"
  >
    {{ $t('billing.console.tenant.no_subscription') }}
  </div>

  <dl v-else class="grid grid-cols-2 lg:grid-cols-5 gap-4 text-xs font-tajawal">
    <div>
      <dt class="text-slate-500 dark:text-slate-400 font-bold">{{ $t('billing.console.tenant.plan') }}</dt>
      <dd class="font-black text-slate-900 dark:text-white flex items-center gap-1.5">
        <span>{{ subscription.plan?.name || '-' }}</span>
        <span
          v-if="subscription.is_founder"
          class="inline-flex items-center gap-1 text-[10px] text-amber-600 dark:text-amber-400"
          :title="$t('billing.console.tenant.founder')"
        >
          <Crown class="w-3.5 h-3.5" />
        </span>
      </dd>
    </div>
    <div>
      <dt class="text-slate-500 dark:text-slate-400 font-bold">{{ $t('billing.console.tenant.status') }}</dt>
      <dd><StatusBadge :label="subscription.status?.label" :variant="statusVariant" dot /></dd>
    </div>
    <div>
      <dt class="text-slate-500 dark:text-slate-400 font-bold">{{ $t('billing.console.tenant.cycle') }}</dt>
      <dd class="font-black text-slate-800 dark:text-slate-200">{{ subscription.billing_cycle?.label || '-' }}</dd>
    </div>
    <div>
      <dt class="text-slate-500 dark:text-slate-400 font-bold">{{ $t('billing.console.tenant.ends_at') }}</dt>
      <dd class="font-black text-slate-800 dark:text-slate-200">{{ endsAt ? formatDate(endsAt) : '-' }}</dd>
    </div>
    <div v-if="daysLeft !== null">
      <dt class="text-slate-500 dark:text-slate-400 font-bold">&nbsp;</dt>
      <dd
        class="font-black"
        :class="daysLeft > 7 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
      >
        {{
          daysLeft >= 0
            ? $t('billing.console.tenant.days_left', { days: daysLeft })
            : $t('billing.console.tenant.ended')
        }}
      </dd>
    </div>
  </dl>
</template>
