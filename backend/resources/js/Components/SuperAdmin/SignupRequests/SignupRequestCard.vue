<script setup>
import { Check, X } from 'lucide-vue-next';
import BaseButton from '../../Common/BaseButton.vue';
import StatusBadge from '../../Common/StatusBadge.vue';
import { useFormatters } from '../../../Composables/useFormatters';

const props = defineProps({
  request: { type: Object, required: true },
  canManage: { type: Boolean, default: false },
});

defineEmits(['approve', 'reject']);

const { formatDateTime } = useFormatters();

const fields = [
  { key: 'contact', value: (r) => r.contact_name },
  { key: 'phone', value: (r) => r.phone, mono: true },
  { key: 'email', value: (r) => r.email, mono: true },
  { key: 'city', value: (r) => r.city },
  { key: 'plan', value: (r) => r.plan_code, mono: true },
  { key: 'subdomain', value: (r) => r.preferred_subdomain, mono: true },
];

const statusVariant = { pending: 'warning', approved: 'success', rejected: 'danger' };
</script>

<template>
  <article
    class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
    :data-testid="`signup-request-${request.id}`"
  >
    <header class="flex flex-wrap items-start justify-between gap-2">
      <div class="min-w-0">
        <h3 class="truncate text-sm font-black text-slate-900 dark:text-white">{{ request.business_name }}</h3>
        <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
          {{ $t(`signup.business_types.${request.business_type}`) }} - {{ formatDateTime(request.created_at) }}
        </p>
      </div>
      <StatusBadge :label="$t(`signup.statuses.${request.status}`)" :variant="statusVariant[request.status]" />
    </header>

    <dl class="grid grid-cols-1 gap-x-4 gap-y-2 text-xs sm:grid-cols-2 lg:grid-cols-3">
      <template v-for="field in fields" :key="field.key">
        <div v-if="field.value(props.request)" class="flex flex-col">
          <dt class="font-bold text-slate-500 dark:text-slate-400">{{ $t(`signup.console.fields.${field.key}`) }}</dt>
          <dd class="break-words font-black text-slate-900 dark:text-slate-100" :dir="field.mono ? 'ltr' : undefined">
            {{ field.value(props.request) }}
          </dd>
        </div>
      </template>
    </dl>

    <p
      v-if="request.notes"
      class="rounded-xl bg-slate-50 p-3 text-xs leading-relaxed text-slate-600 dark:bg-slate-800/60 dark:text-slate-300"
    >
      <span class="font-black">{{ $t('signup.console.fields.notes') }}:</span> {{ request.notes }}
    </p>
    <p
      v-if="request.rejection_reason"
      class="rounded-xl bg-rose-500/10 p-3 text-xs leading-relaxed text-rose-600 dark:text-rose-400"
    >
      <span class="font-black">{{ $t('signup.console.fields.rejection_reason') }}:</span> {{ request.rejection_reason }}
    </p>

    <footer v-if="canManage && request.status === 'pending'" class="flex flex-wrap gap-2 pt-1">
      <BaseButton variant="success" size="md" :icon="Check" @click="$emit('approve', request)">
        {{ $t('signup.console.approve') }}
      </BaseButton>
      <BaseButton variant="danger" size="md" :icon="X" @click="$emit('reject', request)">
        {{ $t('signup.console.reject') }}
      </BaseButton>
    </footer>
  </article>
</template>
