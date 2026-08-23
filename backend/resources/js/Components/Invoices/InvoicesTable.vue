<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl">
    <!-- Loading State -->
    <div v-if="isLoading" class="p-12 text-center">
      <div class="w-8 h-8 border-4 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
      <p class="text-xs text-slate-400 font-bold font-tajawal">{{ $t('common.loading') }}</p>
    </div>

    <!-- Content State -->
    <template v-else-if="invoices.length > 0">
      <!-- 1. Desktop & Tablet High-Density Table (>= 768px) -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100/90 dark:bg-slate-950/80 text-slate-700 dark:text-slate-400 font-tajawal border-b border-slate-200 dark:border-slate-800">
              <th class="py-3 px-3 text-center w-10">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  @change="$emit('toggle-select-all')"
                  class="w-4 h-4 text-theme-primary rounded border-slate-300 dark:border-slate-700 focus:ring-theme-primary cursor-pointer"
                />
              </th>
              <th class="py-3 px-3 text-start font-bold">{{ $t('invoices.invoice_number') }}</th>
              <th class="py-3 px-3 text-start font-bold">{{ $t('invoices.customer') }}</th>
              <th class="py-3 px-3 text-start font-bold">{{ $t('invoices.date') }}</th>
              <th class="py-3 px-3 text-center font-bold">{{ $t('invoices.payment_method') }}</th>
              <th class="py-3 px-3 text-end font-bold">{{ $t('invoices.total_net') }}</th>
              <th class="py-3 px-3 text-end font-bold">{{ $t('invoices.paid') }}</th>
              <th class="py-3 px-3 text-end font-bold">{{ $t('invoices.remaining') }}</th>
              <th class="py-3 px-3 text-center font-bold">{{ $t('invoices.status') }}</th>
              <th class="py-3 px-3 text-center font-bold">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
            <tr
              v-for="inv in invoices"
              :key="inv.id"
              class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
              :class="[
                inv.is_cancelled ? 'opacity-60 bg-rose-500/5' : '',
                selectedIds.includes(inv.id) ? 'bg-theme-primary/10' : ''
              ]"
            >
              <!-- Checkbox -->
              <td class="py-3.5 px-3 text-center">
                <input
                  type="checkbox"
                  :value="inv.id"
                  :checked="selectedIds.includes(inv.id)"
                  @change="$emit('toggle-select', inv.id)"
                  class="w-4 h-4 text-theme-primary rounded border-slate-300 dark:border-slate-700 focus:ring-theme-primary cursor-pointer"
                />
              </td>

              <!-- Invoice Number -->
              <td class="py-3.5 px-3 font-mono font-bold text-theme-primary">
                <button
                  type="button"
                  @click="$emit('preview', inv)"
                  class="hover:underline cursor-pointer font-bold"
                >
                  {{ inv.invoice_number }}
                </button>
              </td>

              <!-- Customer -->
              <td class="py-3.5 px-3">
                <div class="font-bold text-slate-900 dark:text-white font-tajawal">{{ inv.customer_name }}</div>
                <div v-if="inv.customer_phone" class="text-[10px] text-slate-400 font-mono">{{ inv.customer_phone }}</div>
              </td>

              <!-- Date -->
              <td class="py-3.5 px-3 font-mono text-slate-500 text-[11px] whitespace-nowrap">
                {{ inv.invoice_date }}
              </td>

              <!-- Payment Method -->
              <td class="py-3.5 px-3 text-center">
                <span class="px-2 py-0.5 rounded-lg text-[11px] font-bold font-tajawal bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                  {{ formatPaymentType(inv.payment_type) }}
                </span>
              </td>

              <!-- Net Total -->
              <td class="py-3.5 px-3 text-end font-mono font-black text-slate-900 dark:text-white text-sm">
                {{ formatMoney(inv.net_total) }} {{ $t('common.currency') }}
              </td>

              <!-- Paid Amount -->
              <td class="py-3.5 px-3 text-end font-mono font-bold text-emerald-600 dark:text-emerald-400">
                {{ formatMoney(inv.paid_amount) }} {{ $t('common.currency') }}
              </td>

              <!-- Remaining / Credit Due -->
              <td class="py-3.5 px-3 text-end font-mono font-bold" :class="inv.remaining_amount > 0 ? 'text-rose-500' : 'text-slate-400'">
                {{ formatMoney(inv.remaining_amount) }} {{ $t('common.currency') }}
              </td>

              <!-- Status Badge -->
              <td class="py-3.5 px-3 text-center font-tajawal">
                <span
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border inline-block"
                  :class="!inv.is_cancelled ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-500' : 'bg-rose-500/10 border-rose-500/30 text-rose-400'"
                >
                  {{ !inv.is_cancelled ? $t('invoices.confirmed_badge') : $t('invoices.cancelled_badge') }}
                </span>
              </td>

              <!-- Actions Dropdown Standard -->
              <td class="py-3.5 px-3 text-center">
                <div class="flex items-center justify-center gap-1">
                  <button
                    type="button"
                    @click="$emit('preview', inv)"
                    class="p-1.5 text-slate-400 hover:text-cyan-500 hover:bg-cyan-50 dark:hover:bg-cyan-950/40 rounded-xl transition cursor-pointer"
                    :title="$t('invoices.view_details')"
                  >
                    <Eye class="w-4 h-4" />
                  </button>

                  <ActionMenu
                    :items="getInvoiceActions(inv)"
                    :title="`فاتورة #${inv.invoice_number}`"
                    button-class="h-8 w-8 min-w-[32px] p-0"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 2. Mobile Touch-Friendly Card Stack (Small & Large Phones) -->
      <div class="block md:hidden p-3 space-y-3 font-tajawal">
        <div
          v-for="inv in invoices"
          :key="inv.id"
          class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 space-y-2.5 transition-all"
          :class="[
            inv.is_cancelled ? 'opacity-60 bg-rose-500/5' : '',
            selectedIds.includes(inv.id) ? 'border-theme-primary bg-theme-primary/5' : ''
          ]"
        >
          <!-- Top Row: Checkbox + Number + Status -->
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <input
                type="checkbox"
                :value="inv.id"
                :checked="selectedIds.includes(inv.id)"
                @change="$emit('toggle-select', inv.id)"
                class="w-4 h-4 text-theme-primary rounded border-slate-300 dark:border-slate-700 focus:ring-theme-primary cursor-pointer"
              />
              <span class="font-mono font-black text-theme-primary text-xs">{{ inv.invoice_number }}</span>
            </div>
            <span
              class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border shrink-0"
              :class="!inv.is_cancelled ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-500' : 'bg-rose-500/10 border-rose-500/30 text-rose-400'"
            >
              {{ !inv.is_cancelled ? $t('invoices.confirmed_badge') : $t('invoices.cancelled_badge') }}
            </span>
          </div>

          <!-- Middle Row: Customer + Date + Payment -->
          <div class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300">
            <span class="truncate max-w-[160px]">{{ inv.customer_name }}</span>
            <span class="text-[11px] font-mono text-slate-400">{{ inv.invoice_date }}</span>
          </div>

          <!-- Financial Row -->
          <div class="flex items-center justify-between pt-1 border-t border-slate-200 dark:border-slate-800 text-xs">
            <div>
              <span class="text-slate-400 text-[10px] block">{{ $t('common.total') }}:</span>
              <span class="font-mono font-black text-slate-900 dark:text-white text-sm">
                {{ formatMoney(inv.net_total) }} {{ $t('common.currency') }}
              </span>
            </div>
            <div class="text-end">
              <span class="text-slate-400 text-[10px] block">{{ $t('invoices.payment_method') }}:</span>
              <span class="font-bold text-slate-700 dark:text-slate-300">{{ formatPaymentType(inv.payment_type) }}</span>
            </div>
          </div>

          <!-- Touch Actions Bar (>= 44px) -->
          <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200 dark:border-slate-800">
            <button
              type="button"
              @click="$emit('preview', inv)"
              class="min-h-[44px] px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 rounded-xl text-xs font-bold text-cyan-600 dark:text-cyan-400 flex items-center justify-center gap-1.5 transition active:scale-95 select-none"
            >
              <Eye class="w-4 h-4" />
              <span>{{ $t('invoices.view_details') }}</span>
            </button>

            <ActionMenu
              :items="getInvoiceActions(inv)"
              :title="`فاتورة #${inv.invoice_number}`"
              button-class="w-full min-h-[44px] rounded-xl font-bold text-xs"
            />
          </div>
        </div>
      </div>

      <!-- Pagination Bar -->
      <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between flex-wrap gap-2 font-tajawal">
        <div class="text-xs text-slate-500 dark:text-slate-400">
          {{ $t('invoices.total_results_invoices', { count: pagination.total }) }}
        </div>
        <div class="flex items-center gap-1">
          <button
            type="button"
            @click="$emit('change-page', pagination.current_page - 1)"
            :disabled="pagination.current_page <= 1"
            class="min-h-[38px] px-3.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 disabled:opacity-40 cursor-pointer active:scale-95 select-none"
          >
            {{ $t('common.previous') }}
          </button>
          <span class="px-3 py-1.5 text-xs font-mono text-slate-700 dark:text-slate-300 font-bold">
            {{ pagination.current_page }} / {{ pagination.last_page }}
          </span>
          <button
            type="button"
            @click="$emit('change-page', pagination.current_page + 1)"
            :disabled="pagination.current_page >= pagination.last_page"
            class="min-h-[38px] px-3.5 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 disabled:opacity-40 cursor-pointer active:scale-95 select-none"
          >
            {{ $t('common.next') }}
          </button>
        </div>
      </div>
    </template>

    <!-- Empty State -->
    <EmptyState
      v-else
      :title="$t('invoices.no_invoices_found')"
      :description="$t('invoices.no_invoices_description')"
      :icon="'🧾'"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Eye, Printer, Ban } from 'lucide-vue-next';
import EmptyState from '../Common/EmptyState.vue';
import ActionMenu from '../ActionMenu.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { trans } from '../../helpers/trans';

const { formatMoney } = useFormatters();

const props = defineProps({
  invoices: { type: Array, default: () => [] },
  selectedIds: { type: Array, default: () => [] },
  pagination: {
    type: Object,
    default: () => ({ current_page: 1, last_page: 1, per_page: 20, total: 0 }),
  },
  isLoading: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle-select', 'toggle-select-all', 'preview', 'print', 'cancel', 'change-page']);

const isAllSelected = computed(() => {
  return (
    props.invoices.length > 0 &&
    props.invoices.every((inv) => props.selectedIds.includes(inv.id))
  );
});

const formatPaymentType = (type) => {
  const map = {
    cash: trans('invoices.payment_cash'),
    credit: trans('invoices.payment_credit'),
    partial: trans('invoices.payment_partial'),
    card: trans('invoices.payment_card'),
    e_wallet: trans('invoices.payment_ewallet'),
    instapay: trans('invoices.payment_instapay'),
  };
  return map[type] || type || trans('invoices.payment_cash');
};

const getInvoiceActions = (inv) => [
  {
    label: trans('invoices.view_details'),
    icon: Eye,
    onClick: () => emit('preview', inv),
  },
  {
    label: trans('invoices.print_cashier_receipt'),
    icon: Printer,
    variant: 'success',
    onClick: () => emit('print', inv.id),
  },
  {
    label: trans('invoices.cancel_invoice'),
    icon: Ban,
    variant: 'danger',
    show: !inv.is_cancelled,
    onClick: () => emit('cancel', inv),
  },
];
</script>
