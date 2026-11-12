<script setup>
import { Hourglass } from 'lucide-vue-next';
import { useFormatters } from '../../../Composables/useFormatters';

defineProps({
  // { planName, trialDays, endsAt, tenantStatus, subscriptionStatus } from resolveApprovalTerms().
  terms: { type: Object, required: true },
});

const { formatDate } = useFormatters();
</script>

<template>
  <div
    class="flex items-start gap-3 rounded-2xl border p-3 border-[var(--color-primary-border)] bg-[var(--color-primary-light)]"
    role="note"
    data-testid="approve-terms"
  >
    <Hourglass class="mt-0.5 h-5 w-5 shrink-0 text-[var(--color-primary)]" />
    <div class="min-w-0 space-y-1">
      <p class="text-xs font-black text-slate-900 dark:text-white">{{ $t('signup.console.terms_title') }}</p>
      <p
        v-if="terms.endsAt"
        class="text-xs font-bold text-slate-700 dark:text-slate-200"
        data-testid="approve-terms-trial"
      >
        {{
          $t('signup.console.terms_trial', {
            date: formatDate(terms.endsAt),
            days: terms.trialDays,
            plan: terms.planName,
          })
        }}
      </p>
      <p v-if="terms.tenantStatus || terms.subscriptionStatus" class="text-[11px] text-slate-500 dark:text-slate-400">
        {{ $t('signup.console.terms_status', { shop: terms.tenantStatus, subscription: terms.subscriptionStatus }) }}
      </p>
    </div>
  </div>
</template>
