<template>
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <DashboardSectionCard
      class="lg:col-span-7"
      :title="'📊 ' + $t('dashboard.weekly_sales_trend')"
      :subtitle="$t('dashboard.weekly_trend_subtitle')"
      :icon="BarChart3"
      icon-bg="bg-emerald-500/10"
      icon-color="text-emerald-500"
      header-class="flex-col sm:flex-row"
    >
      <template #action>
        <div class="flex items-center gap-2 text-xs font-mono font-bold text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800">
          <span>{{ $t('dashboard.weekly_total') }}:</span>
          <span class="text-emerald-500 font-black">{{ formatMoney(period.sales || 0) }} {{ $t('common.currency') }}</span>
        </div>
      </template>
      <SimpleBarChart
        :items="dailyTrend"
        key-field="date"
        value-field="sales"
        label-field="label"
        tooltip-primary-field="sales_formatted"
        tooltip-secondary-field="invoices"
        :highlight-fn="isToday"
        highlight-color="var(--color-primary, #10b981)"
        default-color="#0ea5e9"
        :height="192"
        bar-gap="1rem"
      >
        <template #footer>
          <div class="flex items-center justify-between pt-3 text-[11px] text-slate-500 dark:text-slate-400 font-bold">
            <div class="flex items-center gap-4">
              <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-md bg-theme-primary"></span>{{ $t('dashboard.current_day') }}</span>
              <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-md bg-sky-500"></span>{{ $t('dashboard.previous_days') }}</span>
            </div>
            <div class="font-mono text-slate-600 dark:text-slate-300">
              {{ $t('dashboard.avg_basket') }}: <span class="font-black text-emerald-500">{{ formatMoney(period.basket_size || 0) }} {{ $t('common.currency') }}</span>
            </div>
          </div>
        </template>
      </SimpleBarChart>
    </DashboardSectionCard>

    <DashboardSectionCard
      class="lg:col-span-5 flex flex-col justify-between"
      :title="'💳 ' + $t('dashboard.payment_distribution')"
      :subtitle="$t('dashboard.payment_dist_subtitle')"
      :icon="CreditCard"
      icon-bg="bg-theme-light"
      icon-color="text-theme-primary"
    >
      <ProgressDistributionList
        :items="paymentDistribution"
        :icon-fn="getPaymentMethodIcon"
        :color-fn="getPaymentMethodColor"
        :format-fn="formatMoney"
        :empty-message="$t('dashboard.no_payments')"
      />
      <div v-if="activeShift" class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs mt-2">
        <div class="flex items-center gap-2">
          <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
          <span class="font-bold text-slate-700 dark:text-slate-300">{{ $t('dashboard.active_shift') }} (#{{ activeShift.shift_number }}):</span>
        </div>
        <div class="font-mono font-black text-emerald-500">{{ formatMoney(activeShift.current_cash || activeShift.starting_cash) }} {{ $t('common.currency') }}</div>
      </div>
    </DashboardSectionCard>
  </div>
</template>
<script setup>
import { BarChart3, CreditCard } from 'lucide-vue-next';
import DashboardSectionCard from '../Common/DashboardSectionCard.vue';
import SimpleBarChart from '../Common/SimpleBarChart.vue';
import ProgressDistributionList from '../Common/ProgressDistributionList.vue';
import { useFormatters } from '../../Composables/useFormatters';
const { formatMoney } = useFormatters();
defineProps({
  dailyTrend: { type: Array, default: () => [] },
  period: { type: Object, default: () => ({}) },
  paymentDistribution: { type: Array, default: () => [] },
  activeShift: { type: Object, default: null },
});
const isToday = (day) => day?.date === new Date().toISOString().split('T')[0];
const getPaymentMethodIcon = (key) => ({ cash: '💵', instapay: '⚡', visa: '💳', e_wallet: '📱', bank_transfer: '🏦' }[key] || '💰');
const getPaymentMethodColor = (key) => ({ cash: 'bg-emerald-500', instapay: 'bg-indigo-500', visa: 'bg-sky-500', e_wallet: 'bg-theme-primary', bank_transfer: 'bg-teal-500' }[key] || 'bg-emerald-500');
</script>