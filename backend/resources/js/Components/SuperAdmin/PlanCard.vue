<template>
  <div
    class="bg-white dark:bg-slate-900/90 border rounded-3xl p-6 shadow-sm dark:shadow-xl flex flex-col justify-between relative space-y-6 transition hover:shadow-md font-tajawal"
    :class="plan.is_popular ? 'border-theme-primary ring-2 ring-theme-primary/30' : 'border-slate-200 dark:border-slate-800'"
  >
    <!-- Popular Badge -->
    <div v-if="plan.is_popular" class="absolute -top-3 start-1/2 -translate-x-1/2 px-3 py-0.5 bg-theme-primary text-white font-black text-[10px] rounded-full uppercase tracking-wider shadow-sm">
      {{ $t('super.popular_badge') }}
    </div>

    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-base font-black text-slate-900 dark:text-white">{{ plan.name }}</h3>
          <span class="text-[10px] text-slate-400 font-mono">Slug: {{ plan.slug }}</span>
        </div>
        <span
          class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border"
          :class="plan.is_active ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/10 border-rose-500/30 text-rose-600 dark:text-rose-400'"
        >
          {{ plan.is_active ? $t('super.plan_enabled') : $t('super.plan_disabled') }}
        </span>
      </div>

      <!-- Pricing Box -->
      <div class="p-4 bg-slate-50 dark:bg-slate-900/80 rounded-2xl border border-slate-200 dark:border-slate-800 text-center space-y-1">
        <div class="text-2xl font-black text-theme-primary font-mono">
          {{ formatMoney(plan.price_monthly) }} <span class="text-xs text-slate-500 dark:text-slate-400 font-tajawal">{{ $t('common.currency') }} / {{ $t('super.per_month') }}</span>
        </div>
        <div class="text-xs text-slate-500 dark:text-slate-400 font-mono">
          {{ $t('super.yearly_rate', { amount: formatMoney(plan.price_yearly) }) }}
        </div>
      </div>

      <!-- Resource Limits List -->
      <div class="space-y-2 text-xs text-slate-700 dark:text-slate-300">
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100 dark:border-slate-800/80">
          <span class="text-slate-500 dark:text-slate-400">{{ $t('super.max_users_label') }}</span>
          <span class="font-mono font-bold text-slate-900 dark:text-white">{{ plan.max_users }}</span>
        </div>
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100 dark:border-slate-800/80">
          <span class="text-slate-500 dark:text-slate-400">{{ $t('super.max_stores_label') }}</span>
          <span class="font-mono font-bold text-slate-900 dark:text-white">{{ plan.max_stores }}</span>
        </div>
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100 dark:border-slate-800/80">
          <span class="text-slate-500 dark:text-slate-400">{{ $t('super.max_items_label') }}</span>
          <span class="font-mono font-bold text-slate-900 dark:text-white">{{ plan.max_items }}</span>
        </div>
        <div class="flex items-center justify-between py-1.5">
          <span class="text-slate-500 dark:text-slate-400">{{ $t('super.monthly_invoices_label') }}</span>
          <span class="font-mono font-bold text-slate-900 dark:text-white">{{ plan.max_invoices_per_month }}</span>
        </div>
      </div>
    </div>

    <!-- Edit Button -->
    <BaseButton
      type="button"
      variant="secondary"
      size="md"
      class="w-full font-bold shadow-xs"
      @click="$emit('edit', plan)"
    >
      {{ $t('super.edit_prices_and_limits_btn') }}
    </BaseButton>
  </div>
</template>

<script setup>
import BaseButton from '../Common/BaseButton.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  plan: { type: Object, required: true },
});

defineEmits(['edit']);
</script>
