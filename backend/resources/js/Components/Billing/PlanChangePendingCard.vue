<script setup>
import { Hourglass, X } from 'lucide-vue-next';
import BaseButton from '@/Components/Common/BaseButton.vue';
import { useFormatters } from '@/Composables/useFormatters';

defineProps({
  request: { type: Object, required: true },
  busy: { type: Boolean, default: false },
});

defineEmits(['cancel']);

const { formatDate } = useFormatters();
</script>

<template>
  <div
    class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/30 p-3 sm:p-4 font-tajawal"
    :data-testid="`pending-request-${request.kind.value}`"
  >
    <div class="flex items-start gap-2.5 min-w-0">
      <Hourglass class="w-5 h-5 shrink-0 mt-0.5 text-amber-600 dark:text-amber-400" />
      <div class="min-w-0 space-y-0.5">
        <p class="text-xs font-black text-amber-700 dark:text-amber-300">
          {{ $t('subscription.billing.requests.pending.title') }} - {{ request.kind.label }}
        </p>
        <p class="text-sm font-black text-slate-900 dark:text-white">
          <template v-if="request.kind.value === 'addon'">
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
        <p class="text-[11px] font-bold text-slate-600 dark:text-slate-400">
          {{ $t('subscription.billing.requests.pending.hint') }} ({{ formatDate(request.created_at) }})
        </p>
      </div>
    </div>
    <BaseButton variant="outline" :icon="X" :loading="busy" @click="$emit('cancel', request)">
      {{ $t('subscription.billing.requests.cancel') }}
    </BaseButton>
  </div>
</template>
