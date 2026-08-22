<template>
  <div class="space-y-6 max-w-5xl mx-auto font-tajawal">
      <!-- Header & Action Bar -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 no-print">
        <div class="space-y-1">
          <div class="flex items-center gap-3">
            <router-link
              to="/customers"
              class="w-10 h-10 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-200 flex items-center justify-center font-bold text-sm transition active:scale-90 shadow-sm border border-slate-700 shrink-0"
              :title="$t('common.back')"
            >
              <ArrowRight class="w-5 h-5" />
            </router-link>
            <div>
              <h1 class="text-base sm:text-xl font-black text-slate-900 dark:text-white flex flex-wrap items-center gap-1.5">
                <span>{{ $t('contacts.ledger_title') }}:</span>
                <span class="text-amber-400">{{ customer?.name }}</span>
              </h1>
              <p class="text-xs text-slate-400 font-bold">
                {{ $t('contacts.ledger_subtitle') }}
              </p>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2 self-end sm:self-auto">
          <button
            type="button"
            @click="printStatement"
            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl text-xs font-bold transition-all flex items-center gap-2 shadow-sm cursor-pointer"
          >
            <Printer class="w-4 h-4 text-amber-400" />
            <span>{{ $t('common.print') }}</span>
          </button>
        </div>
      </div>

      <!-- Customer Summary Profile & Financial Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Total Debit -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
          <div class="text-xs font-bold text-slate-400">{{ $t('contacts.period_debit') }} ({{ $t('contacts.withdrawals') }})</div>
          <div class="text-xl font-black text-slate-900 dark:text-white font-mono">
            {{ formatMoney(summary.total_debit || 0) }} <span class="text-xs text-slate-400">{{ $t('common.currency') }}</span>
          </div>
        </div>

        <!-- Total Credit -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
          <div class="text-xs font-bold text-slate-400">{{ $t('contacts.period_credit') }} ({{ $t('contacts.payments_received') }})</div>
          <div class="text-xl font-black text-emerald-400 font-mono">
            {{ formatMoney(summary.total_credit || 0) }} <span class="text-xs text-slate-400">{{ $t('common.currency') }}</span>
          </div>
        </div>

        <!-- Closing Balance -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
          <div class="text-xs font-bold text-slate-400">{{ $t('contacts.closing_balance') }} ({{ $t('contacts.net_balance') }})</div>
          <div
            class="text-xl font-black font-mono"
            :class="customer?.current_balance > 0 ? 'text-rose-400' : 'text-emerald-400'"
          >
            {{ formatMoney(customer?.current_balance || 0) }} <span class="text-xs text-slate-400">{{ $t('common.currency') }}</span>
          </div>
        </div>
      </div>

      <!-- Date Range Filter Presets Bar -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-md flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 no-print">
        <!-- Date Inputs -->
        <div class="flex items-center gap-2 flex-wrap">
          <div class="flex items-center gap-1.5">
            <span class="text-xs font-bold text-slate-400">{{ $t('common.from') }}:</span>
            <input
              v-model="dateFrom"
              type="date"
              class="h-9 px-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
            >
          </div>
          <div class="flex items-center gap-1.5">
            <span class="text-xs font-bold text-slate-400">{{ $t('common.to') }}:</span>
            <input
              v-model="dateTo"
              type="date"
              class="h-9 px-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
            >
          </div>
          <button
            type="button"
            @click="fetchStatement"
            class="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-xl text-xs font-black transition-all cursor-pointer"
          >
            {{ $t('common.filter') }}
          </button>
        </div>

        <!-- Quick Date Range Pills -->
        <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-900 p-1 rounded-xl border border-slate-200 dark:border-slate-800 overflow-x-auto">
          <button
            type="button"
            @click="applyPreset('today')"
            class="px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer whitespace-nowrap"
            :class="activePreset === 'today' ? 'bg-theme-primary text-white shadow-theme-primary font-black' : 'text-slate-400 hover:text-white'"
          >
            {{ $t('common.today') }}
          </button>
          <button
            type="button"
            @click="applyPreset('this_month')"
            class="px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer whitespace-nowrap"
            :class="activePreset === 'this_month' ? 'bg-theme-primary text-white shadow-theme-primary font-black' : 'text-slate-400 hover:text-white'"
          >
            {{ $t('common.this_month') }}
          </button>
          <button
            type="button"
            @click="applyPreset('this_year')"
            class="px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer whitespace-nowrap"
            :class="activePreset === 'this_year' ? 'bg-theme-primary text-white shadow-theme-primary font-black' : 'text-slate-400 hover:text-white'"
          >
            {{ $t('common.this_year') }}
          </button>
          <button
            type="button"
            @click="applyPreset('all')"
            class="px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer whitespace-nowrap"
            :class="activePreset === 'all' ? 'bg-theme-primary text-white shadow-theme-primary font-black' : 'text-slate-400 hover:text-white'"
          >
            {{ $t('common.all') }}
          </button>
        </div>
      </div>

      <!-- Ledger Table -->
      <div class="bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div v-if="isLoading" class="p-12 text-center">
          <div class="w-8 h-8 border-4 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
          <p class="text-xs text-slate-400 font-bold">{{ $t('common.loading') }}</p>
        </div>

        <div v-else-if="ledger.length > 0" class="overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr class="bg-slate-900/90 text-slate-400 border-b border-slate-800">
                <th class="py-3 px-4 text-start font-bold">#</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('common.date') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('contacts.transaction_type') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('contacts.reference_no') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('contacts.period_debit') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('contacts.period_credit') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('contacts.closing_balance') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('common.notes') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
              <tr
                v-for="(row, idx) in ledger"
                :key="idx"
                class="hover:bg-slate-50 dark:hover:bg-slate-100 dark:hover:bg-slate-900/50 transition-colors"
              >
                <td class="py-3.5 px-4 font-mono text-slate-500">{{ idx + 1 }}</td>
                <td class="py-3.5 px-4 font-mono text-slate-300">{{ row.date }}</td>
                <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white font-tajawal">{{ row.type }}</td>
                <td class="py-3.5 px-4 font-mono text-amber-400">{{ row.ref_number || '—' }}</td>
                <td class="py-3.5 px-4 text-end font-mono font-bold" :class="row.debit > 0 ? 'text-white' : 'text-slate-500'">
                  {{ formatMoney(row.debit) }}
                </td>
                <td class="py-3.5 px-4 text-end font-mono font-bold" :class="row.credit > 0 ? 'text-emerald-400' : 'text-slate-500'">
                  {{ formatMoney(row.credit) }}
                </td>
                <td class="py-3.5 px-4 text-end font-mono font-black" :class="row.balance_after > 0 ? 'text-rose-400' : (row.balance_after < 0 ? 'text-cyan-400' : 'text-emerald-400')">
                  {{ formatMoney(row.balance_after) }} {{ $t('common.currency') }}
                </td>
                <td class="py-3.5 px-4 font-tajawal text-slate-400 max-w-xs truncate">{{ row.notes || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <EmptyState
          v-else
          :title="$t('contacts.no_transactions_found')"
          :description="$t('contacts.no_transactions_in_period')"
          :icon="'📄'"
        />
      </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import EmptyState from '../../Components/Common/EmptyState.vue';
import api from '../../services/api';
import {
    ArrowRight,
    Printer
} from 'lucide-vue-next';

const route = useRoute();
const customerId = route.params.id;

const customer = ref(null);
const ledger = ref([]);
const summary = ref({
    total_debit: 0,
    total_credit: 0,
    current_balance: 0,
});

const dateFrom = ref('');
const dateTo = ref('');
const activePreset = ref('all');
const isLoading = ref(false);

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const applyPreset = (preset) => {
    activePreset.value = preset;
    const now = new Date();
    const formatDate = (d) => d.toISOString().split('T')[0];

    if (preset === 'today') {
        dateFrom.value = formatDate(now);
        dateTo.value = formatDate(now);
    } else if (preset === 'this_month') {
        const start = new Date(now.getFullYear(), now.getMonth(), 1);
        const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        dateFrom.value = formatDate(start);
        dateTo.value = formatDate(end);
    } else if (preset === 'this_year') {
        const start = new Date(now.getFullYear(), 0, 1);
        const end = new Date(now.getFullYear(), 11, 31);
        dateFrom.value = formatDate(start);
        dateTo.value = formatDate(end);
    } else if (preset === 'all') {
        dateFrom.value = '';
        dateTo.value = '';
    }
    fetchStatement();
};

const fetchStatement = async () => {
    isLoading.value = true;
    try {
        const response = await api.get(`/customers/${customerId}/statement`, {
            params: {
                from_date: dateFrom.value || undefined,
                to_date: dateTo.value || undefined,
            },
        });
        const data = response.data?.data;
        if (data) {
            customer.value = data.customer;
            ledger.value = data.ledger || [];
            summary.value = data.summary || {};
        }
    } catch (error) {
        console.error('Failed to load customer statement:', error);
    } finally {
        isLoading.value = false;
    }
};

const printStatement = () => {
    window.print();
};

onMounted(fetchStatement);
</script>

<style scoped>
@media print {
  .no-print {
    display: none !important;
  }
}
</style>
