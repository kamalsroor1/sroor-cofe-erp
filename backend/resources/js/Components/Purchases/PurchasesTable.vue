<template>
  <DataTable
    :rows="purchases"
    :columns="columns"
    :loading="loading"
    :pagination="pagination"
    :empty-title="$t('purchases.no_purchases_found')"
    :empty-message="$t('purchases.no_purchases_description')"
    empty-icon="🚛"
    @page-change="$emit('page-change', $event)"
    data-testid="purchases-table"
  >
    <template #empty-action>
      <router-link
        to="/purchases/create"
        class="px-5 py-2.5 bg-theme-primary text-white font-bold rounded-xl text-xs font-black shadow-lg shadow-theme-primary inline-block"
      >
        {{ $t('purchases.add_first_purchase') }}
      </router-link>
    </template>

    <template #cell-index="{ index }">
      <span class="font-mono text-slate-500">
        {{ index + 1 + (pagination.current_page - 1) * pagination.per_page }}
      </span>
    </template>

    <template #cell-purchase_number="{ row }">
      <span class="font-mono font-bold text-theme-primary">
        {{ row.purchase_number }}
      </span>
    </template>

    <template #cell-supplier="{ row }">
      <div class="font-bold text-slate-900 dark:text-white font-tajawal text-sm">{{ row.supplier_name }}</div>
      <div v-if="row.supplier_company" class="text-[10px] text-slate-500 dark:text-slate-400 font-tajawal mt-0.5">
        {{ row.supplier_company }}
      </div>
    </template>

    <template #cell-date="{ row }">
      <span class="font-mono text-slate-600 dark:text-slate-300">
        {{ row.purchase_date }}
      </span>
    </template>

    <template #cell-net_total="{ row }">
      <span class="font-mono font-black text-slate-900 dark:text-white text-sm">
        {{ formatMoney(row.net_total) }} {{ $t('common.currency') }}
      </span>
    </template>

    <template #cell-paid_amount="{ row }">
      <span class="font-mono font-bold text-emerald-500 dark:text-emerald-400">
        {{ formatMoney(row.paid_amount) }} {{ $t('common.currency') }}
      </span>
    </template>

    <template #cell-remaining_amount="{ row }">
      <span class="font-mono font-bold" :class="row.remaining_amount > 0 ? 'text-rose-500' : 'text-slate-400'">
        {{ formatMoney(row.remaining_amount) }} {{ $t('common.currency') }}
      </span>
    </template>

    <template #cell-status="{ row }">
      <span
        class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border font-tajawal"
        :class="
          row.status === 'confirmed'
            ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400'
            : 'bg-rose-500/10 border-rose-500/30 text-rose-500 dark:text-rose-400'
        "
      >
        {{ row.status === 'confirmed' ? $t('invoices.confirmed_badge') : $t('invoices.cancelled_badge') }}
      </span>
    </template>

    <template #cell-actions="{ row }">
      <div class="flex items-center justify-center gap-1">
        <!-- Preview Button -->
        <button
          type="button"
          @click="$emit('preview', row)"
          data-testid="action-preview"
          class="min-h-[44px] min-w-[44px] lg:min-h-0 lg:min-w-0 p-2 text-slate-500 hover:text-cyan-500 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition cursor-pointer active:scale-90"
          :title="$t('purchases.view_items_hint')"
        >
          <Eye class="w-4 h-4" />
        </button>

        <!-- Cancel Button -->
        <button
          v-if="row.status === 'confirmed'"
          type="button"
          @click="$emit('cancel', row)"
          data-testid="action-cancel"
          class="min-h-[44px] min-w-[44px] lg:min-h-0 lg:min-w-0 p-2 text-slate-500 hover:text-rose-500 hover:bg-rose-500/10 rounded-xl transition cursor-pointer active:scale-90"
          :title="$t('purchases.cancel_invoice_hint')"
        >
          <Ban class="w-4 h-4" />
        </button>
      </div>
    </template>
  </DataTable>
</template>

<script setup>
import { computed } from 'vue';
import { Eye, Ban } from 'lucide-vue-next';
import DataTable from '@/Components/Common/DataTable.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();
const { formatMoney } = useFormatters();

defineProps({
  purchases: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, per_page: 15, total: 0 }) },
  loading: { type: Boolean, default: false },
});

defineEmits(['preview', 'cancel', 'page-change']);

const columns = computed(() => [
  { key: 'index', label: '#', align: 'start' },
  { key: 'purchase_number', label: t('invoices.invoice_number'), align: 'start' },
  { key: 'supplier', label: t('purchases.supplier'), align: 'start' },
  { key: 'date', label: t('common.date'), align: 'start' },
  { key: 'net_total', label: t('common.total'), align: 'end' },
  { key: 'paid_amount', label: t('invoices.paid'), align: 'end' },
  { key: 'remaining_amount', label: t('invoices.remaining_due'), align: 'end' },
  { key: 'status', label: t('common.status'), align: 'center' },
  { key: 'actions', label: t('common.actions'), align: 'center' },
]);
</script>
