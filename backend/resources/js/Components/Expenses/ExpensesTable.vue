<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl font-tajawal">
    <!-- Loading State -->
    <div v-if="loading" class="p-6">
      <TableSkeleton :rows="8" :cols="8" />
    </div>

    <!-- Data Loaded -->
    <div v-else-if="expenses.length > 0">
      <!-- 1. Desktop & Tablet Table (hidden md:block) -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-400 font-tajawal border-b border-slate-200 dark:border-slate-800">
              <th class="py-3 px-4 text-start font-bold">#</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('invoices.invoice_number') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('expenses.expense_item') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('expenses.cost_center_and_category') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('common.date') }}</th>
              <th class="py-3 px-4 text-end font-bold">{{ $t('common.amount') }}</th>
              <th class="py-3 px-4 text-center font-bold">{{ $t('invoices.payment_method') }}</th>
              <th class="py-3 px-4 text-center font-bold">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
            <tr
              v-for="(expense, idx) in expenses"
              :key="expense.id"
              class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
            >
              <td class="py-3.5 px-4 font-mono text-slate-500">
                {{ idx + 1 + (pagination.current_page - 1) * pagination.per_page }}
              </td>
              <td class="py-3.5 px-4 font-mono font-bold text-theme-primary">
                {{ expense.expense_number }}
              </td>
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900 dark:text-white font-tajawal text-sm">{{ expense.title }}</div>
                <div v-if="expense.notes" class="text-[10px] text-slate-500 dark:text-slate-400 font-tajawal mt-0.5 max-w-xs truncate">
                  {{ expense.notes }}
                </div>
              </td>
              <td class="py-3.5 px-4">
                <div class="text-xs font-bold text-slate-700 dark:text-slate-300 font-tajawal">{{ expense.cost_center_label || expense.cost_center }}</div>
                <div class="text-[10px] text-slate-500 dark:text-slate-400 font-tajawal mt-0.5">{{ expense.category }}</div>
              </td>
              <td class="py-3.5 px-4 font-mono text-slate-700 dark:text-slate-300">
                {{ expense.expense_date }}
              </td>
              <td class="py-3.5 px-4 text-end font-mono font-black text-sm text-rose-500 dark:text-rose-400">
                {{ formatMoney(expense.amount) }} <span class="text-xs font-normal font-tajawal">{{ $t('common.currency') }}</span>
              </td>
              <td class="py-3.5 px-4 text-center">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold font-tajawal bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300">
                  {{ formatPaymentMethod(expense.payment_method) }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-center">
                <div class="flex items-center justify-center gap-1">
                  <!-- Edit Button -->
                  <button
                    type="button"
                    @click="$emit('edit', expense)"
                    class="p-2 text-slate-500 hover:text-cyan-500 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition cursor-pointer active:scale-95"
                    :title="$t('common.edit')"
                  >
                    <Pencil class="w-4 h-4" />
                  </button>

                  <!-- Delete Button -->
                  <button
                    type="button"
                    @click="$emit('delete', expense)"
                    class="p-2 text-slate-500 hover:text-rose-500 hover:bg-rose-500/10 rounded-xl transition cursor-pointer active:scale-95"
                    :title="$t('common.delete')"
                  >
                    <Trash2 class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 2. Mobile Responsive Tactile Cards (block md:hidden) -->
      <div class="block md:hidden divide-y divide-slate-200 dark:divide-slate-800 p-2 space-y-2">
        <div
          v-for="expense in expenses"
          :key="expense.id"
          class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-3"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <div class="flex items-center gap-2">
                <h4 class="text-sm font-black text-slate-900 dark:text-white">{{ expense.title }}</h4>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300">
                  {{ expense.category }}
                </span>
              </div>
              <p class="text-[11px] text-theme-primary font-mono mt-0.5">#{{ expense.expense_number }}</p>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ expense.cost_center_label || expense.cost_center }}</p>
            </div>

            <div class="text-end shrink-0">
              <div class="text-base font-black font-mono text-rose-500 dark:text-rose-400">
                {{ formatMoney(expense.amount) }} <span class="text-[10px] font-sans text-slate-400">{{ $t('common.currency') }}</span>
              </div>
              <p class="text-[10px] text-slate-400 font-mono">{{ expense.expense_date }}</p>
            </div>
          </div>

          <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
            <span class="text-xs font-bold text-slate-500">
              {{ formatPaymentMethod(expense.payment_method) }}
            </span>

            <div class="flex items-center gap-1">
              <button
                type="button"
                @click="$emit('edit', expense)"
                class="min-h-[38px] px-3 py-1.5 text-slate-700 dark:text-slate-300 hover:text-cyan-500 rounded-xl bg-slate-100 dark:bg-slate-800 text-xs font-bold flex items-center gap-1 active:scale-95"
              >
                <Pencil class="w-3.5 h-3.5" />
                <span>{{ $t('common.edit') }}</span>
              </button>

              <button
                type="button"
                @click="$emit('delete', expense)"
                class="p-2 text-rose-500 rounded-xl bg-rose-500/10 transition active:scale-95"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else
      :title="$t('expenses.no_expenses_found')"
      :description="$t('expenses.no_expenses_description')"
      icon="💸"
    >
      <template #action>
        <button
          type="button"
          @click="$emit('create')"
          class="px-5 py-2.5 bg-theme-primary text-white font-bold rounded-xl text-xs font-black shadow-lg shadow-theme-primary cursor-pointer"
        >
          {{ $t('expenses.add_first_expense') }}
        </button>
      </template>
    </EmptyState>

    <!-- Pagination Bar -->
    <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
      <div class="text-xs text-slate-500 dark:text-slate-400">
        {{ $t('expenses.total_results_count', { count: pagination.total }) }}
      </div>
      <div class="flex items-center gap-1">
        <button
          type="button"
          @click="$emit('page-change', pagination.current_page - 1)"
          :disabled="pagination.current_page <= 1"
          class="min-h-[38px] px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 disabled:opacity-40 cursor-pointer"
        >
          {{ $t('common.previous') }}
        </button>
        <span class="px-3 py-1.5 text-xs font-mono text-slate-700 dark:text-slate-300 font-bold">
          {{ pagination.current_page }} / {{ pagination.last_page }}
        </span>
        <button
          type="button"
          @click="$emit('page-change', pagination.current_page + 1)"
          :disabled="pagination.current_page >= pagination.last_page"
          class="min-h-[38px] px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 disabled:opacity-40 cursor-pointer"
        >
          {{ $t('common.next') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Pencil, Trash2 } from 'lucide-vue-next';
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useTrans } from '../../Composables/useTrans';

const { formatMoney } = useFormatters();
const { t } = useTrans();

defineProps({
  expenses: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, per_page: 20, total: 0 }) },
  loading: { type: Boolean, default: false },
});

defineEmits(['create', 'edit', 'delete', 'page-change']);

const formatPaymentMethod = (method) => {
  const map = {
    cash: t('contacts.cash'),
    instapay: t('contacts.instapay'),
    e_wallet: t('contacts.wallet'),
    visa: '💳 Visa',
    bank_transfer: t('contacts.bank_transfer'),
    check: t('invoices.check'),
  };
  return map[method] || method;
};
</script>
