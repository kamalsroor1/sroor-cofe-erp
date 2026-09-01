<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl">
    <!-- Loading State -->
    <div v-if="loading" class="p-6">
      <TableSkeleton :rows="8" :cols="8" />
    </div>

    <!-- Data Loaded -->
    <div v-else-if="purchases.length > 0">
      <!-- 1. Desktop & Tablet Table (hidden md:block) -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
              <th class="py-3 px-4 text-start font-bold">#</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('invoices.invoice_number') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('purchases.supplier') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('common.date') }}</th>
              <th class="py-3 px-4 text-end font-bold">{{ $t('common.total') }}</th>
              <th class="py-3 px-4 text-end font-bold">{{ $t('invoices.paid') }}</th>
              <th class="py-3 px-4 text-end font-bold">{{ $t('invoices.remaining_due') }}</th>
              <th class="py-3 px-4 text-center font-bold">{{ $t('common.status') }}</th>
              <th class="py-3 px-4 text-center font-bold">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
            <tr
              v-for="(p, idx) in purchases"
              :key="p.id"
              class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
              :class="p.status === 'cancelled' ? 'opacity-50 line-through bg-rose-500/5' : ''"
            >
              <td class="py-3.5 px-4 font-mono text-slate-500">
                {{ idx + 1 + (pagination.current_page - 1) * pagination.per_page }}
              </td>
              <td class="py-3.5 px-4 font-mono font-bold text-theme-primary">
                {{ p.purchase_number }}
              </td>
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900 dark:text-white font-tajawal text-sm">{{ p.supplier_name }}</div>
                <div v-if="p.supplier_company" class="text-[10px] text-slate-500 dark:text-slate-400 font-tajawal mt-0.5">
                  {{ p.supplier_company }}
                </div>
              </td>
              <td class="py-3.5 px-4 font-mono text-slate-600 dark:text-slate-300">
                {{ p.purchase_date }}
              </td>
              <td class="py-3.5 px-4 text-end font-mono font-black text-slate-900 dark:text-white text-sm">
                {{ formatMoney(p.net_total) }} {{ $t('common.currency') }}
              </td>
              <td class="py-3.5 px-4 text-end font-mono font-bold text-emerald-500 dark:text-emerald-400">
                {{ formatMoney(p.paid_amount) }} {{ $t('common.currency') }}
              </td>
              <td class="py-3.5 px-4 text-end font-mono font-bold" :class="p.remaining_amount > 0 ? 'text-rose-500' : 'text-slate-400'">
                {{ formatMoney(p.remaining_amount) }} {{ $t('common.currency') }}
              </td>
              <td class="py-3.5 px-4 text-center font-tajawal">
                <span
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border"
                  :class="p.status === 'confirmed' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/10 border-rose-500/30 text-rose-500 dark:text-rose-400'"
                >
                  {{ p.status === 'confirmed' ? $t('invoices.confirmed_badge') : $t('invoices.cancelled_badge') }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-center">
                <div class="flex items-center justify-center gap-1">
                  <!-- Preview Button -->
                  <button
                    type="button"
                    @click="$emit('preview', p)"
                    class="p-2 text-slate-500 hover:text-cyan-500 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition cursor-pointer active:scale-90"
                    :title="$t('purchases.view_items_hint')"
                  >
                    <Eye class="w-4 h-4" />
                  </button>

                  <!-- Cancel Button -->
                  <button
                    v-if="p.status === 'confirmed'"
                    type="button"
                    @click="$emit('cancel', p)"
                    class="p-2 text-slate-500 hover:text-rose-500 hover:bg-rose-500/10 rounded-xl transition cursor-pointer active:scale-90"
                    :title="$t('purchases.cancel_invoice_hint')"
                  >
                    <Ban class="w-4 h-4" />
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
          v-for="p in purchases"
          :key="p.id"
          class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-3"
          :class="p.status === 'cancelled' ? 'opacity-60 bg-rose-500/5' : ''"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <div class="flex items-center gap-2">
                <span class="font-mono font-bold text-theme-primary text-sm">{{ p.purchase_number }}</span>
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
                  :class="p.status === 'confirmed' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-500' : 'bg-rose-500/10 border-rose-500/30 text-rose-500'"
                >
                  {{ p.status === 'confirmed' ? $t('invoices.confirmed_badge') : $t('invoices.cancelled_badge') }}
                </span>
              </div>
              <h4 class="text-sm font-black text-slate-900 dark:text-white mt-1">{{ p.supplier_name }}</h4>
              <p class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">{{ p.purchase_date }}</p>
            </div>

            <div class="flex items-center gap-1 shrink-0">
              <button
                type="button"
                @click="$emit('preview', p)"
                class="p-2 text-slate-500 hover:text-cyan-500 rounded-xl bg-slate-100 dark:bg-slate-800 transition active:scale-90"
              >
                <Eye class="w-4 h-4" />
              </button>
              <button
                v-if="p.status === 'confirmed'"
                type="button"
                @click="$emit('cancel', p)"
                class="p-2 text-slate-500 hover:text-rose-500 rounded-xl bg-rose-500/10 transition active:scale-90"
              >
                <Ban class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs font-mono">
            <div>
              <span class="text-[10px] text-slate-400 font-sans block">{{ $t('common.total') }}:</span>
              <span class="font-black text-slate-900 dark:text-white">{{ formatMoney(p.net_total) }}</span>
            </div>
            <div>
              <span class="text-[10px] text-slate-400 font-sans block">{{ $t('invoices.paid') }}:</span>
              <span class="font-bold text-emerald-500">{{ formatMoney(p.paid_amount) }}</span>
            </div>
            <div>
              <span class="text-[10px] text-slate-400 font-sans block">{{ $t('invoices.remaining_due') }}:</span>
              <span class="font-bold" :class="p.remaining_amount > 0 ? 'text-rose-500' : 'text-slate-400'">{{ formatMoney(p.remaining_amount) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else
      :title="$t('purchases.no_purchases_found')"
      :description="$t('purchases.no_purchases_description')"
      icon="🚛"
    >
      <template #action>
        <router-link
          to="/purchases/create"
          class="px-5 py-2.5 bg-theme-primary text-white font-bold rounded-xl text-xs font-black shadow-lg shadow-theme-primary"
        >
          {{ $t('purchases.add_first_purchase') }}
        </router-link>
      </template>
    </EmptyState>

    <!-- Pagination Bar -->
    <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
      <div class="text-xs text-slate-500 dark:text-slate-400">
        {{ $t('purchases.total_results_purchases', { count: pagination.total }) }}
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
import { Eye, Ban } from 'lucide-vue-next';
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  purchases: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, per_page: 15, total: 0 }) },
  loading: { type: Boolean, default: false },
});

defineEmits(['preview', 'cancel', 'page-change']);
</script>
