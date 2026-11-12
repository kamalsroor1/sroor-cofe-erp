<template>
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4" data-testid="dashboard-kpis">
    <MetricCard
      v-if="hasMetric(metrics.today_sales)"
      data-testid="kpi-today-sales"
      :title="$t('dashboard.today_sales')"
      :value="formatMoney(metrics.today_sales)"
      :currency="currencyLabel"
      variant="success"
      :icon="Banknote"
      icon-bg="bg-emerald-500/10"
      icon-color="text-emerald-600 dark:text-emerald-400"
      :footer-left="hasAmount(metrics.today_returns) || !hasMetric(metrics.cash_sales) ? '' : cashLabel"
    >
      <DashboardSalesNotes
        :returns="metrics.today_returns"
        :returns-label="$t('dashboard.returns_today', { count: metrics.today_returns_count || 0 })"
        :cash="metrics.cash_sales"
        :credit="metrics.credit_sales"
      />
    </MetricCard>

    <MetricCard
      v-if="hasMetric(metrics.today_invoices_count)"
      data-testid="kpi-today-invoices"
      :title="$t('dashboard.today_invoices')"
      :value="formatNumber(metrics.today_invoices_count)"
      variant="default"
      :icon="FileText"
      icon-bg="bg-slate-100 dark:bg-slate-800"
      icon-color="text-slate-500 dark:text-slate-400"
      :footer-left="hasMetric(metrics.monthly_sales) ? $t('dashboard.monthly_sales') : ''"
      :footer-right="hasMetric(metrics.monthly_sales) ? `${formatMoney(metrics.monthly_sales)} ${currencyLabel}` : ''"
    >
      <DashboardSalesNotes :returns="metrics.monthly_returns" :returns-label="$t('dashboard.returns_month')" />
    </MetricCard>

    <MetricCard
      v-if="hasMetric(metrics.monthly_gross_profit)"
      data-testid="kpi-monthly-profit"
      :title="$t('dashboard.monthly_gross_profit')"
      :value="formatMoney(metrics.monthly_gross_profit)"
      :currency="currencyLabel"
      variant="success"
      :icon="TrendingUp"
      icon-bg="bg-emerald-500/10"
      icon-color="text-emerald-600 dark:text-emerald-400"
      :footer-left="hasMetric(metrics.monthly_margin) ? $t('dashboard.profit_margin') + ':' : ''"
      :footer-right="hasMetric(metrics.monthly_margin) ? `%${metrics.monthly_margin}` : ''"
      footer-right-class="text-emerald-600 dark:text-emerald-400"
    />

    <MetricCard
      v-if="hasMetric(metrics.customers_debt)"
      data-testid="kpi-customers-debt"
      class="[&_[data-metric-value]]:text-orange-600 dark:[&_[data-metric-value]]:text-orange-400"
      :title="$t('dashboard.customers_debt')"
      :value="formatMoney(metrics.customers_debt)"
      :currency="currencyLabel"
      variant="default"
      :icon="HandCoins"
      icon-bg="bg-orange-500/10"
      icon-color="text-orange-600 dark:text-orange-400"
      :footer-left="hasMetric(metrics.customers_count) ? $t('dashboard.active_customers') + ':' : ''"
      :footer-right="
        hasMetric(metrics.customers_count) ? `${metrics.customers_count} ${$t('dashboard.customer_unit')}` : ''
      "
      footer-right-class="text-slate-700 dark:text-slate-300"
    />
  </div>
</template>
<script setup>
import { computed } from 'vue';
import { TrendingUp, HandCoins, FileText, Banknote } from 'lucide-vue-next';
import MetricCard from '../Common/MetricCard.vue';
import DashboardSalesNotes from './DashboardSalesNotes.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useReturnsDisplay } from '../../Composables/useReturnsDisplay';
import { useTrans } from '../../Composables/useTrans';
import { hasMetric } from '../../helpers/dashboardMetrics';

const { formatMoney, formatNumber, currencyLabel } = useFormatters();
const { hasAmount } = useReturnsDisplay();
const { t } = useTrans();

// A card is drawn only for figures the API returned: absent means "not for this user", never zero.
const props = defineProps({ metrics: { type: Object, default: () => ({}) } });
const cashLabel = computed(() => `${t('dashboard.cash_label')}: ${formatMoney(props.metrics.cash_sales)}`);
</script>
