<script setup>
import { TriangleAlert } from 'lucide-vue-next';
import { useFormatters } from '../../../Composables/useFormatters';

defineProps({
  // [{ id, name, status: { value, label }, created_at }] from approval_preview.matching_tenants.
  tenants: { type: Array, required: true },
});

const { formatDate } = useFormatters();
</script>

<template>
  <div
    class="flex items-start gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-3 dark:border-amber-500/40 dark:bg-amber-500/10"
    role="alert"
    data-testid="signup-matching-tenants"
  >
    <TriangleAlert class="mt-0.5 h-5 w-5 shrink-0 text-warning-fg" />
    <div class="min-w-0 space-y-1">
      <p class="text-xs font-black text-warning-fg">
        {{ $t('signup.console.matching_title', { count: tenants.length }) }}
      </p>
      <ul class="space-y-0.5">
        <li v-for="tenant in tenants" :key="tenant.id" class="text-xs font-bold text-slate-700 dark:text-slate-200">
          {{
            $t('signup.console.matching_item', {
              name: tenant.name,
              status: tenant.status?.label ?? '',
              date: formatDate(tenant.created_at),
            })
          }}
        </li>
      </ul>
      <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $t('signup.console.matching_note') }}</p>
    </div>
  </div>
</template>
