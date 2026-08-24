<template>
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 font-tajawal no-print">
    <!-- Subtotal -->
    <MetricCard
      :title="$t('invoices.subtotal')"
      :value="formatMoney(invoice?.subtotal || 0)"
      :currency="$t('common.currency')"
      variant="default"
      icon="💵"
      icon-bg="bg-slate-500/10"
      icon-color="text-slate-600 dark:text-slate-300"
    />

    <!-- Discount -->
    <MetricCard
      :title="$t('invoices.discount')"
      :value="formatMoney(invoice?.discount_amount || 0)"
      :currency="$t('common.currency')"
      variant="danger"
      icon="🏷️"
      icon-bg="bg-rose-500/10"
      icon-color="text-rose-500"
    />

    <!-- Shipping / Extra Fees -->
    <MetricCard
      :title="$t('invoices.shipping')"
      :value="formatMoney(invoice?.shipping_cost || 0)"
      :currency="$t('common.currency')"
      variant="cyan"
      icon="🚚"
      icon-bg="bg-cyan-500/10"
      icon-color="text-cyan-500"
    />

    <!-- Net Total (Required) -->
    <MetricCard
      :title="$t('invoices.net_total')"
      :value="formatMoney(invoice?.net_total || 0)"
      :currency="$t('common.currency')"
      variant="primary"
      icon="💰"
      icon-bg="bg-theme-light"
      icon-color="text-theme-primary"
      class="border-theme-primary/40 shadow-md shadow-theme-primary/10"
    />

    <!-- Paid Amount -->
    <MetricCard
      :title="$t('invoices.paid')"
      :value="formatMoney(invoice?.paid_amount || 0)"
      :currency="$t('common.currency')"
      variant="success"
      icon="✅"
      icon-bg="bg-emerald-500/10"
      icon-color="text-emerald-500"
    />

    <!-- Remaining Credit -->
    <MetricCard
      :title="$t('invoices.remaining')"
      :value="formatMoney(invoice?.remaining_amount || 0)"
      :currency="$t('common.currency')"
      :variant="parseFloat(invoice?.remaining_amount || 0) > 0 ? 'warning' : 'default'"
      icon="📝"
      :icon-bg="parseFloat(invoice?.remaining_amount || 0) > 0 ? 'bg-amber-500/10' : 'bg-slate-500/10'"
      :icon-color="parseFloat(invoice?.remaining_amount || 0) > 0 ? 'text-amber-500' : 'text-slate-400'"
    />
  </div>
</template>

<script setup>
import MetricCard from '../../Common/MetricCard.vue';
import { useFormatters } from '../../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  invoice: { type: Object, default: null },
});
</script>
