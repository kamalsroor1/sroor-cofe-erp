<script setup>
import { computed } from 'vue';
import { Hourglass, CalendarClock, X } from 'lucide-vue-next';
import BaseButton from '@/Components/Common/BaseButton.vue';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
  request: { type: Object, required: true },
  busy: { type: Boolean, default: false },
  // End of the paid term: when a scheduled downgrade to the free plan takes effect.
  endsAt: { type: String, default: null },
  // #232: no paid term (trial / expired) -> support answers a plan change with a plan invoice, not a difference.
  hasPaidTerm: { type: Boolean, default: true },
});

defineEmits(['cancel']);

const { formatDate } = useFormatters();

// PLAN-13: a downgrade to the free plan waits for the end of the term instead of an invoice from support.
const isScheduled = computed(() => props.request.status?.value === 'scheduled');
const hintKey = computed(() =>
  props.request.kind.value === 'plan_change' && !props.hasPaidTerm
    ? 'subscription.billing.requests.pending.hint_plan_invoice'
    : 'subscription.billing.requests.pending.hint'
);
</script>

<template>
  <div
    class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/30 p-3 sm:p-4 font-tajawal"
    :data-testid="`pending-request-${request.kind.value}`"
  >
    <div class="flex items-start gap-2.5 min-w-0">
      <component
        :is="isScheduled ? CalendarClock : Hourglass"
        class="w-5 h-5 shrink-0 mt-0.5 text-amber-600 dark:text-amber-400"
      />
      <div class="min-w-0 space-y-0.5">
        <p class="text-xs font-black text-amber-700 dark:text-amber-300">
          {{
            isScheduled
              ? $t('subscription.downgrade.scheduled_title')
              : `${$t('subscription.billing.requests.pending.title')} - ${request.kind.label}`
          }}
        </p>
        <p class="text-sm font-black text-slate-900 dark:text-white">
          <template v-if="isScheduled">
            {{
              endsAt
                ? $t('subscription.downgrade.scheduled_text', {
                    plan: request.requested_plan?.name || '',
                    date: formatDate(endsAt),
                  })
                : $t('subscription.downgrade.scheduled_text_no_date', { plan: request.requested_plan?.name || '' })
            }}
          </template>
          <template v-else-if="request.kind.value === 'addon'">
            {{
              $t('subscription.billing.requests.pending.addon', {
                addon: request.addon?.name || '',
                quantity: request.quantity,
                cycle: request.billing_cycle.label,
              })
            }}
          </template>
          <template v-else>
            {{
              $t('subscription.billing.requests.pending.plan_change', {
                plan: request.requested_plan?.name || '',
                cycle: request.billing_cycle.label,
              })
            }}
          </template>
        </p>
        <p class="text-xs font-bold text-slate-600 dark:text-slate-400">
          <template v-if="isScheduled">{{ $t('subscription.downgrade.scheduled_hint') }}</template>
          <template v-else> {{ $t(hintKey) }} ({{ formatDate(request.created_at) }}) </template>
        </p>
      </div>
    </div>
    <BaseButton variant="outline" :icon="X" :loading="busy" @click="$emit('cancel', request)">
      {{ $t('subscription.billing.requests.cancel') }}
    </BaseButton>
  </div>
</template>
