<template>
  <DashboardSectionCard :title="$t('dashboard.recent_invoices')" dot-color="bg-emerald-500">
    <template #action>
      <router-link to="/invoices" class="text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-theme-primary transition flex items-center gap-1 cursor-pointer">
        <span>{{ $t('dashboard.all_invoices') }}</span><span>←</span>
      </router-link>
    </template>
    <!-- 1. Desktop & Tablet Tabular View (MD Screens and Up) -->
    <div class="hidden md:block overflow-x-auto">
      <table class="w-full text-start text-xs font-tajawal">
        <thead class="text-slate-400 text-[11px] font-bold border-b border-slate-200 dark:border-slate-800/80">
          <tr>
            <th class="py-3 text-start">{{ $t('dashboard.invoice_number') }}</th>
            <th class="py-3 text-start">{{ $t('dashboard.customer') }}</th>
            <th class="py-3 text-start">{{ $t('dashboard.date') }}</th>
            <th class="py-3 text-start">{{ $t('dashboard.total') }}</th>
            <th class="py-3 text-center">{{ $t('dashboard.status') }}</th>
            <th class="py-3 text-end">{{ $t('dashboard.actions') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-mono">
          <tr v-for="inv in invoices" :key="inv.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
            <td class="py-3.5 text-cyan-600 dark:text-cyan-400 font-bold font-mono">{{ inv.invoice_number }}</td>
            <td class="py-3.5 font-sans font-bold text-slate-800 dark:text-slate-200">{{ inv.customer_name }}</td>
            <td class="py-3.5 text-slate-500 dark:text-slate-400 font-mono text-[11px]">{{ inv.invoice_date || inv.created_at }}</td>
            <td class="py-3.5 font-bold text-slate-900 dark:text-white font-mono">{{ formatMoney(inv.net_total) }} <span class="text-[10px] font-sans text-slate-400">{{ $t('common.currency') }}</span></td>
            <td class="py-3.5 text-center font-sans">
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border" :class="statusBadge(inv)">{{ statusLabel(inv) }}</span>
            </td>
            <td class="py-3.5 text-end font-sans">
              <button type="button" @click="$emit('preview', inv)" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-[11px] font-bold transition cursor-pointer border border-slate-300 dark:border-slate-700 active:scale-95">
                {{ $t('dashboard.preview_print') }}
              </button>
            </td>
          </tr>
          <tr v-if="invoices.length === 0">
            <td colspan="6" class="py-12 text-center text-xs text-slate-400 font-bold font-sans">{{ $t('dashboard.no_invoices') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 2. Mobile Touch-Friendly Card Stack (Small and Large Phones) -->
    <div class="block md:hidden space-y-2.5">
      <div
        v-for="inv in invoices"
        :key="inv.id"
        @click="$emit('preview', inv)"
        class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 hover:border-theme-primary/50 transition-all active:scale-[0.98] cursor-pointer space-y-2 select-none"
      >
        <!-- Top Row: Invoice Number + Status Badge -->
        <div class="flex items-center justify-between">
          <span class="text-xs font-black font-mono text-cyan-600 dark:text-cyan-400">{{ inv.invoice_number }}</span>
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border" :class="statusBadge(inv)">
            {{ statusLabel(inv) }}
          </span>
        </div>

        <!-- Middle Row: Customer + Date -->
        <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 font-bold font-tajawal">
          <span class="text-slate-800 dark:text-slate-200 truncate max-w-[180px]">{{ inv.customer_name }}</span>
          <span class="font-mono text-[10px] text-slate-400">{{ inv.invoice_date || inv.created_at }}</span>
        </div>

        <!-- Bottom Row: Net Total + 44px Touch Action Button -->
        <div class="flex items-center justify-between pt-1 border-t border-slate-200/60 dark:border-slate-800/60">
          <div class="font-mono font-black text-sm text-slate-900 dark:text-white">
            {{ formatMoney(inv.net_total) }} <span class="text-[10px] font-sans font-bold text-slate-400">{{ $t('common.currency') }}</span>
          </div>
          <button
            type="button"
            @click.stop="$emit('preview', inv)"
            class="min-h-[44px] px-4 py-2 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 rounded-xl text-xs font-bold transition border border-slate-300 dark:border-slate-700 active:scale-95 shadow-xs flex items-center gap-1.5"
          >
            <span>{{ $t('dashboard.preview_print') }}</span>
          </button>
        </div>
      </div>

      <div v-if="invoices.length === 0" class="py-10 text-center text-xs text-slate-400 font-bold font-sans">
        {{ $t('dashboard.no_invoices') }}
      </div>
    </div>
  </DashboardSectionCard>
</template>
<script setup>
import DashboardSectionCard from '../Common/DashboardSectionCard.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { trans } from '../../helpers/trans';

const { formatMoney } = useFormatters();
defineProps({ invoices: { type: Array, default: () => [] } });
defineEmits(['preview']);

const statusBadge = (inv) => {
  if (inv.status === 'cancelled') return 'bg-rose-500/10 text-rose-500 border-rose-500/30';
  if (inv.remaining_amount > 0) return 'bg-theme-light text-theme-primary border-theme-border';
  return 'bg-emerald-500/10 text-emerald-500 border-emerald-500/30';
};
const statusLabel = (inv) => {
  if (inv.status === 'cancelled') return trans('dashboard.status_cancelled');
  if (inv.remaining_amount > 0) return trans('dashboard.status_credit');
  return trans('dashboard.status_paid');
};
</script>