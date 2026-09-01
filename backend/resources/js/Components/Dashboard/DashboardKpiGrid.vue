<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <MetricCard
      :title="$t('dashboard.today_sales')"
      :value="formatMoney(metrics.today_sales || 0)"
      :currency="$t('common.currency')"
      variant="default"
      :icon="Banknote"
      icon-bg="bg-emerald-500/10"
      icon-color="text-emerald-500"
      :footer-left="`${metrics.today_invoices_count || 0} ${$t('dashboard.approved_invoices')}`"
      :footer-right="`${$t('dashboard.cash_label')}: ${formatMoney(metrics.cash_sales || 0)}`"
      footer-right-class="text-emerald-500"
    />
    <MetricCard
      :title="$t('dashboard.monthly_gross_profit')"
      :value="formatMoney(metrics.monthly_gross_profit || 0)"
      :currency="$t('common.currency')"
      variant="cyan"
      :icon="TrendingUp"
      icon-bg="bg-cyan-500/10"
      icon-color="text-cyan-500 dark:text-cyan-400"
      :footer-left="$t('dashboard.profit_margin') + ':'"
      :footer-right="`%${metrics.monthly_margin || '0.00'}`"
      footer-right-class="text-emerald-500"
    />
    <MetricCard
      :title="$t('dashboard.customers_debt')"
      :value="formatMoney(metrics.customers_debt || 0)"
      :currency="$t('common.currency')"
      variant="primary"
      :icon="CreditCard"
      icon-bg="bg-theme-light"
      icon-color="text-theme-primary"
      :footer-left="$t('dashboard.active_customers') + ':'"
      :footer-right="`${metrics.customers_count || 0} ${$t('dashboard.customer_unit')}`"
      footer-right-class="text-slate-700 dark:text-slate-300"
    />
    <MetricCard
      :title="$t('dashboard.monthly_sales')"
      :value="formatMoney(metrics.monthly_sales || 0)"
      :currency="$t('common.currency')"
      variant="indigo"
      :icon="BarChart3"
      icon-bg="bg-indigo-500/10"
      icon-color="text-indigo-500 dark:text-indigo-400"
      :footer-left="$t('dashboard.net_cash_today') + ':'"
      :footer-right="`${formatMoney(metrics.net_cash_today || 0)} ${$t('common.currency')}`"
      footer-right-class="text-emerald-500"
    />
  </div>
</template>
<script setup>
import { TrendingUp, CreditCard, BarChart3, Banknote } from 'lucide-vue-next';
import MetricCard from '../Common/MetricCard.vue';
import { useFormatters } from '../../Composables/useFormatters';
const { formatMoney } = useFormatters();
defineProps({ metrics: { type: Object, default: () => ({}) } });
</script>