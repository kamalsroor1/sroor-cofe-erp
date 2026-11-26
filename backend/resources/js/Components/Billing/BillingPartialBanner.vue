<script setup>
import { useFormatters } from '@/Composables/useFormatters';

defineProps({
  payment: { type: Object, required: true },
});

const { formatMoney } = useFormatters();
</script>

<template>
  <div
    class="rounded-2xl border border-amber-500 bg-amber-50 dark:bg-amber-500/10 p-3 text-xs font-bold text-amber-900 dark:text-amber-200"
    role="status"
  >
    {{
      $t('subscription.billing.partial_banner', {
        paid: formatMoney(payment.paid),
        remaining: formatMoney(payment.remaining),
      })
    }}
  </div>
</template>
