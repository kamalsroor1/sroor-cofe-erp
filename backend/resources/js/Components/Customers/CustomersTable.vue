<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl">
    <!-- Loading State -->
    <div v-if="loading" class="p-6">
      <TableSkeleton :rows="8" :cols="7" />
    </div>

    <!-- Data Loaded -->
    <div v-else-if="customers.length > 0">
      <!-- 1. Desktop & Tablet Table (hidden md:block) -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-400 font-tajawal border-b border-slate-200 dark:border-slate-800">
              <th class="py-3 px-4 text-start font-bold">#</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('contacts.customer_name') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('contacts.phone') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('contacts.address') }}</th>
              <th class="py-3 px-4 text-end font-bold">{{ $t('contacts.current_balance') }}</th>
              <th class="py-3 px-4 text-center font-bold">{{ $t('common.status') }}</th>
              <th class="py-3 px-4 text-center font-bold">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
            <tr
              v-for="(customer, idx) in customers"
              :key="customer.id"
              class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
              :class="customer.current_balance > 0 ? 'bg-rose-500/5' : ''"
            >
              <td class="py-3.5 px-4 font-mono text-slate-500">
                {{ idx + 1 + (pagination.current_page - 1) * pagination.per_page }}
              </td>
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900 dark:text-white font-tajawal text-sm">{{ customer.name }}</div>
                <div v-if="customer.tax_number" class="text-[10px] text-slate-500 dark:text-slate-400 font-mono mt-0.5">
                  {{ $t('contacts.tax_number_label') }} {{ customer.tax_number }}
                </div>
              </td>
              <td class="py-3.5 px-4 font-mono text-slate-700 dark:text-slate-300" dir="ltr">
                {{ customer.phone || '—' }}
              </td>
              <td class="py-3.5 px-4 font-tajawal text-slate-700 dark:text-slate-400 max-w-xs truncate">
                {{ customer.address || '—' }}
              </td>
              <td class="py-3.5 px-4 text-end">
                <div
                  class="font-mono font-black text-sm"
                  :class="customer.current_balance > 0 ? 'text-rose-500 dark:text-rose-400' : (customer.current_balance < 0 ? 'text-cyan-500 dark:text-cyan-400' : 'text-emerald-500 dark:text-emerald-400')"
                >
                  {{ formatMoney(customer.current_balance) }} <span class="text-xs font-normal font-tajawal">{{ $t('common.currency') }}</span>
                </div>
                <div class="text-[10px] font-tajawal text-slate-500 dark:text-slate-400 mt-0.5">
                  {{ customer.current_balance > 0 ? $t('contacts.debt_due') : (customer.current_balance < 0 ? $t('contacts.credit_balance') : $t('contacts.settled')) }}
                </div>
              </td>
              <td class="py-3.5 px-4 text-center">
                <span
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-bold font-tajawal border"
                  :class="customer.is_active ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-500'"
                >
                  {{ customer.is_active ? $t('common.active') : $t('common.inactive') }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-center">
                <div class="flex items-center justify-center gap-1">
                  <!-- Collect Payment Button -->
                  <button
                    type="button"
                    @click="$emit('pay', customer)"
                    class="px-2.5 py-1.5 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 rounded-xl text-xs font-bold transition flex items-center gap-1 font-tajawal cursor-pointer active:scale-95"
                    :title="$t('contacts.collect_payment')"
                  >
                    <Receipt class="w-3.5 h-3.5" />
                    <span>{{ $t('contacts.collect_payment') }}</span>
                  </button>

                  <!-- Statement Button -->
                  <router-link
                    :to="`/customers/${customer.id}/statement`"
                    class="p-2 text-slate-500 hover:text-theme-primary hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition active:scale-95"
                    :title="$t('contacts.statement')"
                  >
                    <FileText class="w-4 h-4" />
                  </router-link>

                  <!-- Edit Button -->
                  <button
                    type="button"
                    @click="$emit('edit', customer)"
                    class="p-2 text-slate-500 hover:text-cyan-500 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition cursor-pointer active:scale-95"
                    :title="$t('common.edit')"
                  >
                    <Pencil class="w-4 h-4" />
                  </button>

                  <!-- Delete Button -->
                  <button
                    type="button"
                    @click="$emit('delete', customer)"
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
          v-for="customer in customers"
          :key="customer.id"
          class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-3"
          :class="customer.current_balance > 0 ? 'bg-rose-500/5' : ''"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <div class="flex items-center gap-2">
                <h4 class="text-sm font-black text-slate-900 dark:text-white">{{ customer.name }}</h4>
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
                  :class="customer.is_active ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-500' : 'bg-slate-800 text-slate-500 border-slate-700'"
                >
                  {{ customer.is_active ? $t('common.active') : $t('common.inactive') }}
                </span>
              </div>
              <p v-if="customer.phone" class="text-[11px] text-slate-400 font-mono mt-0.5" dir="ltr">📞 {{ customer.phone }}</p>
              <p v-if="customer.address" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 font-tajawal">{{ customer.address }}</p>
            </div>

            <div class="text-end shrink-0">
              <div
                class="text-sm font-black font-mono"
                :class="customer.current_balance > 0 ? 'text-rose-500' : (customer.current_balance < 0 ? 'text-cyan-500' : 'text-emerald-500')"
              >
                {{ formatMoney(customer.current_balance) }} <span class="text-[10px] font-sans text-slate-400">{{ $t('common.currency') }}</span>
              </div>
              <p class="text-[10px] text-slate-400">{{ customer.current_balance > 0 ? $t('contacts.debt_due') : (customer.current_balance < 0 ? $t('contacts.credit_balance') : $t('contacts.settled')) }}</p>
            </div>
          </div>

          <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
            <button
              type="button"
              @click="$emit('pay', customer)"
              class="flex-1 min-h-[38px] px-3 py-1.5 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1 active:scale-95"
            >
              <Receipt class="w-3.5 h-3.5" />
              <span>{{ $t('contacts.collect_payment') }}</span>
            </button>

            <router-link
              :to="`/customers/${customer.id}/statement`"
              class="min-h-[38px] px-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold flex items-center justify-center gap-1 active:scale-95"
            >
              <FileText class="w-3.5 h-3.5" />
              <span>{{ $t('contacts.statement') }}</span>
            </router-link>

            <button
              type="button"
              @click="$emit('edit', customer)"
              class="p-2 text-slate-500 hover:text-cyan-500 rounded-xl bg-slate-100 dark:bg-slate-800 transition active:scale-95"
            >
              <Pencil class="w-4 h-4" />
            </button>

            <button
              type="button"
              @click="$emit('delete', customer)"
              class="p-2 text-slate-500 hover:text-rose-500 rounded-xl bg-rose-500/10 transition active:scale-95"
            >
              <Trash2 class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else
      :title="$t('contacts.no_customers_found')"
      :description="$t('contacts.no_customers_description')"
      icon="👥"
    >
      <template #action>
        <button
          type="button"
          @click="$emit('create')"
          class="px-5 py-2.5 bg-theme-primary text-white font-bold rounded-xl text-xs font-black shadow-lg shadow-theme-primary cursor-pointer"
        >
          {{ $t('contacts.add_first_customer') }}
        </button>
      </template>
    </EmptyState>

    <!-- Pagination Bar -->
    <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
      <div class="text-xs text-slate-500 dark:text-slate-400">
        {{ $t('activity.total_records') }} <span class="font-mono text-theme-primary font-bold">{{ pagination.total }}</span> {{ $t('contacts.customer_unit') }}
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
import { Receipt, FileText, Pencil, Trash2 } from 'lucide-vue-next';
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  customers: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, per_page: 15, total: 0 }) },
  loading: { type: Boolean, default: false },
});

defineEmits(['create', 'pay', 'edit', 'delete', 'page-change']);
</script>
