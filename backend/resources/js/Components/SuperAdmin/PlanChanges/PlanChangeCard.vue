<script setup>
import { computed } from 'vue';
import { Check, FileText, X } from 'lucide-vue-next';
import BaseButton from '../../Common/BaseButton.vue';
import StatusBadge from '../../Common/StatusBadge.vue';
import { useFormatters } from '../../../Composables/useFormatters';

const props = defineProps({
  request: { type: Object, required: true },
  canManage: { type: Boolean, default: false },
});

defineEmits(['issue', 'reject']);

const { formatDateTime, formatMoney } = useFormatters();

const STATUS_VARIANTS = { pending: 'warning', invoiced: 'success', rejected: 'danger', cancelled: 'slate' };

const isAddon = computed(() => props.request.kind.value === 'addon');
const isPending = computed(() => props.request.status.value === 'pending');
</script>

<template>
  <article
    class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
    :data-testid="`plan-change-${request.id}`"
  >
    <header class="flex flex-wrap items-start justify-between gap-2">
      <div class="min-w-0">
        <h3 class="truncate text-sm font-black text-slate-900 dark:text-white">{{ request.tenant?.name }}</h3>
        <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
          {{ request.kind.label }} - {{ formatDateTime(request.created_at) }}
        </p>
      </div>
      <StatusBadge :label="request.status.label" :variant="STATUS_VARIANTS[request.status.value] || 'slate'" />
    </header>

    <p class="text-sm font-black text-slate-900 dark:text-white">
      <template v-if="isAddon">
        {{
          $t('billing.console.plan_changes.addon_summary', {
            addon: request.addon?.name || '',
            quantity: request.quantity,
          })
        }}
      </template>
      <template v-else>
        {{
          $t('billing.console.plan_changes.plan_summary', {
            from: request.current_plan?.name || '-',
            to: request.requested_plan?.name || '-',
          })
        }}
      </template>
    </p>

    <dl class="grid grid-cols-1 gap-x-4 gap-y-2 text-xs sm:grid-cols-2 lg:grid-cols-3">
      <div class="flex flex-col">
        <dt class="font-bold text-slate-500 dark:text-slate-400">
          {{ $t('billing.console.plan_changes.fields.cycle') }}
        </dt>
        <dd class="font-black text-slate-900 dark:text-slate-100">{{ request.billing_cycle.label }}</dd>
      </div>
      <div v-if="request.requested_by_name" class="flex flex-col">
        <dt class="font-bold text-slate-500 dark:text-slate-400">
          {{ $t('billing.console.plan_changes.fields.requested_by') }}
        </dt>
        <dd class="break-words font-black text-slate-900 dark:text-slate-100">{{ request.requested_by_name }}</dd>
      </div>
      <div v-if="request.reviewed_by" class="flex flex-col">
        <dt class="font-bold text-slate-500 dark:text-slate-400">
          {{ $t('billing.console.plan_changes.fields.reviewed_by') }}
        </dt>
        <dd class="break-words font-black text-slate-900 dark:text-slate-100">
          {{ request.reviewed_by.name }} - {{ formatDateTime(request.reviewed_at) }}
        </dd>
      </div>
    </dl>

    <p
      v-if="request.notes"
      class="rounded-xl bg-slate-50 p-3 text-xs leading-relaxed text-slate-600 dark:bg-slate-800/60 dark:text-slate-300"
    >
      <span class="font-black">{{ $t('billing.console.plan_changes.fields.notes') }}:</span> {{ request.notes }}
    </p>
    <p
      v-if="request.rejection_reason"
      class="rounded-xl bg-rose-500/10 p-3 text-xs leading-relaxed text-rose-600 dark:text-rose-400"
    >
      <span class="font-black">{{ $t('billing.console.plan_changes.fields.rejection_reason') }}:</span>
      {{ request.rejection_reason }}
    </p>

    <footer class="flex flex-wrap gap-2 pt-1">
      <template v-if="canManage && isPending">
        <BaseButton variant="success" :icon="Check" @click="$emit('issue', request)">
          {{ $t('billing.console.plan_changes.actions.issue') }}
        </BaseButton>
        <BaseButton variant="danger" :icon="X" @click="$emit('reject', request)">
          {{ $t('billing.console.plan_changes.actions.reject') }}
        </BaseButton>
      </template>
      <BaseButton
        v-if="request.invoice"
        variant="secondary"
        :icon="FileText"
        :to="`/super-admin/billing-invoices/${request.invoice.id}/print`"
      >
        {{ request.invoice.number }} - {{ formatMoney(request.invoice.total, 3) }}
      </BaseButton>
    </footer>
  </article>
</template>
