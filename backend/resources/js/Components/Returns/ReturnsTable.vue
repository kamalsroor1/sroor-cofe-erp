<template>
  <div
    class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm dark:shadow-xl font-tajawal"
  >
    <DataTable
      data-testid="returns-table"
      :rows="returnsList"
      :columns="columns"
      :loading="loading"
      :pagination="pagination"
      :empty-title="$t('returns.no_returns_found')"
      :empty-message="$t('returns.no_returns_description')"
      @page-change="$emit('page-change', $event)"
    >
      <template #empty>
        <EmptyState
          :title="$t('returns.no_returns_found')"
          :description="$t('returns.no_returns_description')"
          :icon="RotateCcw"
        >
          <template #action>
            <router-link
              to="/returns/create"
              class="px-5 py-2.5 bg-theme-primary text-white font-bold rounded-xl text-xs font-black font-tajawal shadow-lg shadow-theme-primary inline-block"
            >
              {{ $t('returns.add_first_return') }}
            </router-link>
          </template>
        </EmptyState>
      </template>

      <template #cell-return_number="{ row }">
        <span class="font-mono font-bold text-theme-primary">{{ row.return_number }}</span>
      </template>

      <template #cell-return_type="{ row }">
        <span
          class="px-2.5 py-1 rounded-full text-xs font-bold border font-tajawal inline-block"
          :class="
            row.return_type === 'sales_return'
              ? 'bg-cyan-500/10 border-cyan-500/30 text-cyan-600 dark:text-cyan-400'
              : 'bg-theme-light border-theme-border text-theme-primary'
          "
        >
          {{
            row.return_type === 'sales_return'
              ? $t('returns.sales_return_option')
              : $t('returns.purchase_return_option')
          }}
        </span>
      </template>

      <template #cell-party_name="{ row }">
        <div>
          <div class="font-bold text-slate-900 dark:text-white font-tajawal">{{ row.party_name }}</div>
          <div v-if="row.party_phone" class="text-xs text-slate-500 font-mono mt-0.5">
            {{ row.party_phone }}
          </div>
        </div>
      </template>

      <template #cell-return_date="{ row }">
        <span class="font-mono text-slate-500 dark:text-slate-400">{{ row.return_date }}</span>
      </template>

      <template #cell-total_amount="{ row }">
        <span class="font-mono font-black text-rose-600 dark:text-rose-400 text-sm">
          {{ formatMoney(row.total_amount) }} {{ currencyLabel }}
        </span>
        <div
          v-if="row.refund_method"
          class="text-xs text-slate-500 dark:text-slate-400 font-tajawal mt-0.5"
          data-testid="return-refund-line"
        >
          {{ $t(`returns.refund_method_${row.refund_method}`) }}
          <span v-if="row.refund_method === 'cash'" class="font-mono"
            >· {{ formatMoney(row.refund_amount) }} {{ currencyLabel }}</span
          >
        </div>
      </template>

      <template #cell-reason="{ row }">
        <span class="text-slate-500 dark:text-slate-400 font-tajawal max-w-xs truncate block">
          {{ row.reason || '—' }}
        </span>
      </template>

      <template #cell-actions="{ row }">
        <div class="flex items-center justify-center gap-1.5 flex-wrap">
          <RowAction
            :icon="Eye"
            :label="$t('common.preview')"
            :title="$t('returns.view_return_details_hint')"
            data-testid="action-view"
            @click="$emit('open-details', row)"
          />
          <template v-if="canDelete">
            <span class="hidden sm:block w-px h-6 bg-slate-200 dark:bg-slate-700 mx-0.5" aria-hidden="true"></span>
            <RowAction
              :icon="Archive"
              :label="$t('returns.archive_short')"
              :title="$t('returns.archive_return_hint')"
              variant="danger"
              data-testid="action-delete"
              @click="$emit('delete-return', row)"
            />
          </template>
        </div>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Eye, Archive, RotateCcw } from 'lucide-vue-next';
import RowAction from '../Common/RowAction.vue';
import DataTable from '@/Components/Common/DataTable.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import { useTrans } from '@/Composables/useTrans';
import { useFormatters } from '@/Composables/useFormatters';

const { t } = useTrans();
const { formatMoney, currencyLabel } = useFormatters();

defineProps({
  returnsList: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0, per_page: 15 }) },
  loading: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
});

defineEmits(['open-details', 'delete-return', 'page-change']);

const columns = computed(() => [
  { key: 'return_number', label: t('returns.doc_number'), mono: true },
  { key: 'return_type', label: t('returns.return_type') },
  { key: 'party_name', label: t('returns.party_col') },
  { key: 'return_date', label: t('common.date'), mono: true },
  { key: 'total_amount', label: t('returns.return_value'), align: 'end' },
  { key: 'reason', label: t('returns.reason') },
  { key: 'actions', label: t('common.actions'), align: 'center' },
]);
</script>
