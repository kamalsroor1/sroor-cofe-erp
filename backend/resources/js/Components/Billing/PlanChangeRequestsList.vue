<script setup>
import { FileText, History } from 'lucide-vue-next';
import BaseButton from '@/Components/Common/BaseButton.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import ErrorState from '@/Components/Common/ErrorState.vue';
import Skeleton from '@/Components/Common/Skeletons/Skeleton.vue';
import BillingStatusBadge from './BillingStatusBadge.vue';
import SupportContact from './SupportContact.vue';
import { useFormatters } from '@/Composables/useFormatters';

defineProps({
  requests: { type: Array, default: () => [] },
  isLoading: { type: Boolean, default: false },
  hasError: { type: Boolean, default: false },
  support: { type: Object, default: () => ({}) },
});

defineEmits(['retry', 'open-invoice']);

const { formatDate } = useFormatters();

const TONES = { pending: 'warning', invoiced: 'success', rejected: 'danger', cancelled: 'neutral' };
</script>

<template>
  <section
    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 space-y-4 shadow-xs font-tajawal"
  >
    <header class="flex items-center gap-2.5">
      <History class="w-5 h-5 text-theme-primary" />
      <h2 class="font-black text-sm sm:text-base text-slate-900 dark:text-white">
        {{ $t('subscription.billing.requests.history.title') }}
      </h2>
    </header>

    <div v-if="isLoading" class="space-y-2" aria-busy="true">
      <Skeleton v-for="n in 3" :key="n" height="h-16" rounded="rounded-2xl" />
    </div>

    <ErrorState
      v-else-if="hasError"
      :title="$t('subscription.billing.requests.history.load_failed')"
      :retry-label="$t('subscription.billing.page.retry')"
      @retry="$emit('retry')"
    />

    <EmptyState
      v-else-if="!requests.length"
      :icon="History"
      :title="$t('subscription.billing.requests.history.empty_title')"
      :description="$t('subscription.billing.requests.history.empty_description')"
    />

    <ul v-else class="space-y-2">
      <li
        v-for="request in requests"
        :key="request.id"
        class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40 p-3 space-y-2"
      >
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="text-sm font-black text-slate-900 dark:text-white">
              <template v-if="request.kind.value === 'addon'">
                {{
                  $t('subscription.billing.requests.history.addon_summary', {
                    addon: request.addon?.name || '',
                    quantity: request.quantity,
                  })
                }}
              </template>
              <template v-else>
                {{
                  $t('subscription.billing.requests.history.plan_summary', {
                    from: request.current_plan?.name || '-',
                    to: request.requested_plan?.name || '-',
                  })
                }}
              </template>
            </p>
            <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
              {{ request.kind.label }} - {{ request.billing_cycle.label }} - {{ formatDate(request.created_at) }}
            </p>
          </div>
          <BillingStatusBadge :label="request.status.label" :tone="TONES[request.status.value] || 'neutral'" />
        </div>
        <p v-if="request.rejection_reason" class="text-[11px] font-bold text-rose-600 dark:text-rose-400">
          {{ $t('subscription.billing.requests.history.rejected_reason', { reason: request.rejection_reason }) }}
        </p>
        <BaseButton
          v-if="request.invoice"
          size="sm"
          variant="secondary"
          :icon="FileText"
          @click="$emit('open-invoice', request.invoice.id)"
        >
          {{ $t('subscription.billing.requests.history.invoice', { number: request.invoice.number }) }}
        </BaseButton>
      </li>
    </ul>

    <SupportContact :support="support" />
  </section>
</template>
