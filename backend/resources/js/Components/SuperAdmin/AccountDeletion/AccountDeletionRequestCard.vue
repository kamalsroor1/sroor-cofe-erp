<script setup>
import { CheckCheck } from 'lucide-vue-next';
import BaseButton from '../../Common/BaseButton.vue';
import StatusBadge from '../../Common/StatusBadge.vue';
import { useFormatters } from '../../../Composables/useFormatters';

const props = defineProps({
  request: { type: Object, required: true },
  canManage: { type: Boolean, default: false },
});

defineEmits(['handle']);

const { formatDateTime } = useFormatters();

const fields = [
  { key: 'phone', label: 'account_deletion.form.phone', value: (r) => r.phone, ltr: true },
  { key: 'email', label: 'account_deletion.form.email', value: (r) => r.email, ltr: true },
  { key: 'shop_code', label: 'account_deletion.console.columns.shop_code', value: (r) => r.shop_code, ltr: true },
];

const statusVariant = { pending: 'warning', handled: 'success' };
</script>

<template>
  <article
    class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
    :data-testid="`account-deletion-request-${request.id}`"
  >
    <header class="flex flex-wrap items-start justify-between gap-2">
      <div class="min-w-0">
        <h3 class="truncate text-sm font-black text-slate-900 dark:text-white">{{ request.contact_name }}</h3>
        <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400">{{ formatDateTime(request.created_at) }}</p>
      </div>
      <StatusBadge
        :label="$t(`account_deletion.statuses.${request.status}`)"
        :variant="statusVariant[request.status]"
      />
    </header>

    <dl class="grid grid-cols-1 gap-x-4 gap-y-2 text-xs sm:grid-cols-3">
      <template v-for="field in fields" :key="field.key">
        <div v-if="field.value(props.request)" class="flex flex-col">
          <dt class="font-bold text-slate-500 dark:text-slate-400">{{ $t(field.label) }}</dt>
          <dd class="break-words font-black text-slate-900 dark:text-slate-100" :dir="field.ltr ? 'ltr' : undefined">
            {{ field.value(props.request) }}
          </dd>
        </div>
      </template>
    </dl>

    <p
      v-if="request.reason"
      class="rounded-xl bg-slate-50 p-3 text-xs leading-relaxed whitespace-pre-line text-slate-600 dark:bg-slate-800/60 dark:text-slate-300"
    >
      <span class="font-black">{{ $t('account_deletion.console.columns.reason') }}:</span> {{ request.reason }}
    </p>
    <p
      v-if="request.status === 'handled'"
      class="rounded-xl bg-emerald-500/10 p-3 text-xs leading-relaxed text-emerald-700 dark:text-emerald-300"
    >
      <span v-if="request.handled_by_name" class="font-black">
        {{ $t('account_deletion.console.handled_by', { name: request.handled_by_name }) }}
      </span>
      <span v-if="request.handled_at"> - {{ formatDateTime(request.handled_at) }}</span>
      <span v-if="request.handled_note" class="block pt-1">{{ request.handled_note }}</span>
    </p>

    <footer v-if="canManage && request.status === 'pending'" class="flex flex-wrap gap-2 pt-1">
      <BaseButton
        variant="success"
        size="md"
        :icon="CheckCheck"
        data-testid="account-deletion-handle"
        @click="$emit('handle', request)"
      >
        {{ $t('account_deletion.console.mark_handled') }}
      </BaseButton>
    </footer>
  </article>
</template>
