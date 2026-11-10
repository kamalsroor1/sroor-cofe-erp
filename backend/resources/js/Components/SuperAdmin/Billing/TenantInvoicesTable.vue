<script setup>
import { Eye, Printer, Ban } from 'lucide-vue-next';
import StatusBadge from '../../Common/StatusBadge.vue';
import { useFormatters } from '../../../Composables/useFormatters';

defineProps({
  invoices: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
  voidingId: { type: Number, default: null },
});

defineEmits(['void']);

const { formatMoney, formatDate } = useFormatters();

const STATUS_VARIANTS = { paid: 'success', pending: 'warning', void: 'slate', refunded: 'info', draft: 'slate' };

const printHref = (invoice) => `/super-admin/billing-invoices/${invoice.id}/print`;
const columns = ['number', 'type', 'status', 'total', 'paid', 'remaining', 'issued', 'due'];
</script>

<template>
  <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-800">
    <table class="w-full min-w-[820px] text-xs font-tajawal text-start">
      <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400">
        <tr>
          <th v-for="column in columns" :key="column" class="px-3 py-3 font-black text-start whitespace-nowrap">
            {{ $t(`billing.console.tenant.columns.${column}`) }}
          </th>
          <th class="px-3 py-3">
            <span class="sr-only">{{ $t('common.actions') }}</span>
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200 dark:divide-slate-800 bg-white dark:bg-slate-900/60">
        <tr v-for="invoice in invoices" :key="invoice.id" :data-testid="`billing-invoice-${invoice.id}`">
          <td class="px-3 py-3 font-black text-slate-900 dark:text-white whitespace-nowrap" dir="ltr">
            {{ invoice.number }}
          </td>
          <td class="px-3 py-3 font-bold text-slate-700 dark:text-slate-300 whitespace-nowrap">
            {{ invoice.type?.label }}
          </td>
          <td class="px-3 py-3">
            <StatusBadge
              :label="invoice.status?.label"
              :variant="STATUS_VARIANTS[invoice.status?.value] || 'slate'"
              dot
            />
          </td>
          <td class="px-3 py-3 font-black text-slate-900 dark:text-white whitespace-nowrap" dir="ltr">
            {{ formatMoney(invoice.total, 3) }}
          </td>
          <td class="px-3 py-3 font-bold text-emerald-600 dark:text-emerald-400 whitespace-nowrap" dir="ltr">
            {{ formatMoney(invoice.paid_amount, 3) }}
          </td>
          <td class="px-3 py-3 font-bold text-slate-700 dark:text-slate-300 whitespace-nowrap" dir="ltr">
            {{ formatMoney(invoice.remaining, 3) }}
          </td>
          <td class="px-3 py-3 font-bold text-slate-600 dark:text-slate-400 whitespace-nowrap">
            {{ formatDate(invoice.issued_at) }}
          </td>
          <td class="px-3 py-3 font-bold text-slate-600 dark:text-slate-400 whitespace-nowrap">
            {{ invoice.due_at ? formatDate(invoice.due_at) : '-' }}
          </td>
          <td class="px-3 py-2">
            <div class="flex items-center justify-end gap-1.5">
              <router-link
                :to="printHref(invoice)"
                :aria-label="$t('billing.console.tenant.actions.view')"
                :title="$t('billing.console.tenant.actions.view')"
                class="w-11 h-11 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-200 flex items-center justify-center transition active:scale-95"
              >
                <Eye class="w-4 h-4" />
              </router-link>
              <a
                :href="`${printHref(invoice)}?print=1`"
                target="_blank"
                rel="noopener"
                :aria-label="$t('billing.console.tenant.actions.print')"
                :title="$t('billing.console.tenant.actions.print')"
                class="w-11 h-11 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-200 flex items-center justify-center transition active:scale-95"
              >
                <Printer class="w-4 h-4" />
              </a>
              <button
                v-if="canManage && invoice.status?.value === 'pending'"
                type="button"
                :disabled="voidingId === invoice.id"
                :aria-label="$t('billing.console.tenant.actions.void')"
                :title="$t('billing.console.tenant.actions.void')"
                class="w-11 h-11 rounded-xl border border-rose-300 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400 flex items-center justify-center cursor-pointer transition active:scale-95 disabled:opacity-50"
                @click="$emit('void', invoice)"
              >
                <Ban class="w-4 h-4" />
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
