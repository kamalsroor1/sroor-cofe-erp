<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl font-tajawal">
    <!-- Tabs Header -->
    <div class="flex items-center border-b border-slate-200 dark:border-slate-800 bg-slate-100/80 dark:bg-slate-900/60 p-1.5 gap-2">
      <button
        type="button"
        @click="$emit('update:activeTab', 'invoices')"
        class="min-h-[40px] flex-1 py-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer active:scale-95"
        :class="activeTab === 'invoices' ? 'bg-theme-primary text-white font-black shadow-theme-primary' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-200/60 dark:hover:bg-slate-800'"
      >
        <ShoppingCart class="w-4 h-4" />
        <span>{{ $t('treasury.journal_invoices_tab') }} ({{ invoices.length }})</span>
      </button>

      <button
        type="button"
        @click="$emit('update:activeTab', 'expenses')"
        class="min-h-[40px] flex-1 py-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer active:scale-95"
        :class="activeTab === 'expenses' ? 'bg-theme-primary text-white font-black shadow-theme-primary' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-200/60 dark:hover:bg-slate-800'"
      >
        <Receipt class="w-4 h-4" />
        <span>{{ $t('treasury.journal_expenses_tab') }} ({{ expenses.length }})</span>
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="p-6">
      <TableSkeleton :rows="8" :cols="8" />
    </div>

    <!-- Tab 1: Invoices Table -->
    <div v-else-if="activeTab === 'invoices'">
      <div v-if="invoices.length > 0">
        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr class="bg-slate-100/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                <th class="py-3 px-4 text-start font-bold">#</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('invoices.invoice_number') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('invoices.customer') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('treasury.time') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('treasury.payment_method') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('common.total') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('invoices.paid') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('common.status') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
              <tr v-for="(inv, idx) in invoices" :key="inv.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                <td class="py-3.5 px-4 font-mono text-slate-500">{{ idx + 1 }}</td>
                <td class="py-3.5 px-4 font-mono font-bold text-theme-primary">{{ inv.invoice_number }}</td>
                <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white font-tajawal">{{ inv.customer_name }}</td>
                <td class="py-3.5 px-4 font-mono text-slate-500 dark:text-slate-400">{{ inv.time || '—' }}</td>
                <td class="py-3.5 px-4 text-center">
                  <span class="px-2.5 py-0.5 rounded-md text-[10px] font-bold font-tajawal bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300">
                    {{ formatPaymentMethod(inv.payment_method) }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-end font-mono font-bold text-slate-900 dark:text-slate-200">
                  {{ formatMoney(inv.net_total) }} {{ $t('common.currency') }}
                </td>
                <td class="py-3.5 px-4 text-end font-mono font-black text-emerald-500 dark:text-emerald-400">
                  {{ formatMoney(inv.paid_amount) }} {{ $t('common.currency') }}
                </td>
                <td class="py-3.5 px-4 text-center">
                  <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold font-tajawal border bg-emerald-500/10 border-emerald-500/30 text-emerald-500 dark:text-emerald-400">
                    {{ $t('treasury.approved_status') }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Tactile Cards -->
        <div class="block md:hidden divide-y divide-slate-200 dark:divide-slate-800 p-2 space-y-2">
          <div
            v-for="inv in invoices"
            :key="inv.id"
            class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-2.5"
          >
            <div class="flex items-center justify-between gap-2">
              <div>
                <span class="text-xs font-mono font-bold text-theme-primary">#{{ inv.invoice_number }}</span>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ inv.customer_name }}</h4>
              </div>
              <div class="text-end">
                <span class="text-sm font-black font-mono text-emerald-500">{{ formatMoney(inv.paid_amount) }} {{ $t('common.currency') }}</span>
                <span class="text-[10px] text-slate-400 block font-mono">{{ inv.time || '—' }}</span>
              </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
              <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                {{ formatPaymentMethod(inv.payment_method) }}
              </span>
              <span class="text-slate-500">{{ $t('common.total') }}: <span class="font-mono font-bold text-slate-900 dark:text-white">{{ formatMoney(inv.net_total) }}</span></span>
            </div>
          </div>
        </div>
      </div>

      <EmptyState
        v-else
        :title="$t('treasury.no_invoices_date')"
        :description="$t('treasury.no_invoices_date_desc')"
        icon="🛒"
      />
    </div>

    <!-- Tab 2: Expenses Table -->
    <div v-else-if="activeTab === 'expenses'">
      <div v-if="expenses.length > 0">
        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr class="bg-slate-100/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                <th class="py-3 px-4 text-start font-bold">#</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('invoices.invoice_number') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('expenses.expense_item') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('treasury.cost_center') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('treasury.expense_method') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('common.amount') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
              <tr v-for="(e, idx) in expenses" :key="e.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                <td class="py-3.5 px-4 font-mono text-slate-500">{{ idx + 1 }}</td>
                <td class="py-3.5 px-4 font-mono font-bold text-theme-primary">{{ e.expense_number }}</td>
                <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white font-tajawal">{{ e.title }}</td>
                <td class="py-3.5 px-4 font-tajawal text-slate-700 dark:text-slate-300">{{ e.cost_center_label || e.cost_center }}</td>
                <td class="py-3.5 px-4 text-center">
                  <span class="px-2.5 py-0.5 rounded-md text-[10px] font-bold font-tajawal bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300">
                    {{ formatPaymentMethod(e.payment_method) }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-end font-mono font-black text-rose-500 dark:text-rose-400">
                  {{ formatMoney(e.amount) }} {{ $t('common.currency') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Tactile Cards -->
        <div class="block md:hidden divide-y divide-slate-200 dark:divide-slate-800 p-2 space-y-2">
          <div
            v-for="e in expenses"
            :key="e.id"
            class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-2"
          >
            <div class="flex items-center justify-between gap-2">
              <div>
                <span class="text-xs font-mono text-theme-primary">#{{ e.expense_number }}</span>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ e.title }}</h4>
                <p class="text-xs text-slate-500">{{ e.cost_center_label || e.cost_center }}</p>
              </div>
              <div class="text-end">
                <span class="text-sm font-black font-mono text-rose-500">{{ formatMoney(e.amount) }} {{ $t('common.currency') }}</span>
                <span class="text-[10px] font-bold block text-slate-400">{{ formatPaymentMethod(e.payment_method) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <EmptyState
        v-else
        :title="$t('treasury.no_expenses_date')"
        :description="$t('treasury.no_expenses_date_desc')"
        icon="💸"
      />
    </div>
  </div>
</template>

<script setup>
import { ShoppingCart, Receipt } from 'lucide-vue-next';
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useTrans } from '../../Composables/useTrans';

const { formatMoney } = useFormatters();
const { t } = useTrans();

defineProps({
  activeTab: { type: String, default: 'invoices' },
  invoices: { type: Array, default: () => [] },
  expenses: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

defineEmits(['update:activeTab']);

const formatPaymentMethod = (method) => {
  const map = {
    cash: t('contacts.cash'),
    instapay: t('contacts.instapay'),
    e_wallet: t('contacts.wallet'),
    visa: '💳 Visa',
    credit: t('invoices.credit'),
    partial: t('invoices.partial'),
  };
  return map[method] || method;
};
</script>
