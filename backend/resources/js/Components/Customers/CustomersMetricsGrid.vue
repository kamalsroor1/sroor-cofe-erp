<template>
  <MetricStrip v-if="loading" :columns="3">
    <StatCardSkeleton v-for="i in 3" :key="i" dense />
  </MetricStrip>

  <MetricStrip v-else :columns="3">
    <MetricCard
      dense
      :title="$t('contacts.total_receivables')"
      :value="formatMoney(metrics.total_debt || 0)"
      :currency="currencyLabel"
      variant="danger"
      :icon="TrendingUp"
      icon-bg="bg-rose-500/10"
      icon-color="text-rose-500"
      :footer-left="$t('contacts.total_receivables_sub')"
    />
    <MetricCard
      dense
      :title="$t('contacts.debtors_count')"
      :value="String(metrics.debtors_count || 0)"
      :currency="$t('contacts.customer_unit')"
      variant="primary"
      :icon="AlertCircle"
      icon-bg="bg-theme-light"
      icon-color="text-theme-primary"
      :footer-left="$t('contacts.debtors_count_sub')"
    />
    <MetricCard
      dense
      :title="$t('contacts.total_customers_count')"
      :value="String(metrics.total_customers || 0)"
      :currency="$t('contacts.customer_unit')"
      :icon="Users"
      icon-bg="bg-emerald-500/10"
      icon-color="text-emerald-500"
      :footer-left="$t('contacts.total_customers_sub')"
    />
  </MetricStrip>
</template>

<script setup>
import { TrendingUp, AlertCircle, Users } from 'lucide-vue-next';
import MetricCard from '../Common/MetricCard.vue';
import MetricStrip from '../Common/MetricStrip.vue';
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney, currencyLabel } = useFormatters();

defineProps({
  metrics: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
});
</script>
