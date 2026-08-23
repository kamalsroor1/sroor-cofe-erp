<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
    <!-- Total Sales -->
    <MetricCard
      :title="$t('common.total_sales')"
      :value="formatMoney(summary.total_sales || 0)"
      :currency="$t('common.currency')"
      variant="success"
      :icon="TrendingUp"
      icon-bg="bg-emerald-500/10"
      icon-color="text-emerald-500"
      :subtitle="$t('invoices.confirmed_sales_sub')"
    />

    <!-- Total Paid -->
    <MetricCard
      :title="$t('invoices.collected_cash_electronic')"
      :value="formatMoney(summary.total_paid || 0)"
      :currency="$t('common.currency')"
      variant="cyan"
      :icon="CheckCircle2"
      icon-bg="bg-cyan-500/10"
      icon-color="text-cyan-500 dark:text-cyan-400"
      :subtitle="$t('invoices.inflows_in_drawer_sub')"
    />

    <!-- Total Due -->
    <MetricCard
      :title="$t('invoices.remaining_credit_due')"
      :value="formatMoney(summary.total_due || 0)"
      :currency="$t('common.currency')"
      variant="danger"
      :icon="Clock"
      icon-bg="bg-rose-500/10"
      icon-color="text-rose-500"
      :subtitle="$t('invoices.debt_under_collection_sub')"
    />

    <!-- Invoices Count -->
    <MetricCard
      :title="$t('invoices.invoices_count_label')"
      :value="summary.total_count || 0"
      :currency="$t('invoices.invoice_unit')"
      variant="primary"
      :icon="FileText"
      icon-bg="bg-theme-light"
      icon-color="text-theme-primary"
      :subtitle="$t('invoices.sales_log_sub')"
    />
  </div>
</template>

<script setup>
import { TrendingUp, CheckCircle2, Clock, FileText } from 'lucide-vue-next';
import MetricCard from '../Common/MetricCard.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  summary: {
    type: Object,
    default: () => ({
      total_sales: 0,
      total_paid: 0,
      total_due: 0,
      total_count: 0,
    }),
  },
});
</script>
