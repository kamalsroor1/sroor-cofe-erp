<template>
  <FilterSidebar
    :is-open="isOpen"
    :title="$t('invoices.filter_search')"
    @close="$emit('close')"
    @apply="$emit('apply')"
    @reset="$emit('reset')"
  >
    <!-- Filter Section: Branch / Store Filter -->
    <div class="space-y-1.5">
      <label class="text-xs font-bold text-slate-600 dark:text-slate-400 flex items-center gap-1.5">
        <Store class="w-3.5 h-3.5 text-theme-primary" />
        <span>{{ $t('invoices.branch_store') }}</span>
      </label>
      <BaseSelect
        :model-value="storeId"
        :options="storeOptions"
        :searchable="false"
        @update:model-value="$emit('update:storeId', $event); $emit('apply')"
      />
    </div>

    <!-- Filter Section: Payment Type -->
    <div class="space-y-1.5">
      <label class="text-xs font-bold text-slate-600 dark:text-slate-400 flex items-center gap-1.5">
        <CreditCard class="w-3.5 h-3.5 text-cyan-500" />
        <span>{{ $t('invoices.payment_financial_type') }}</span>
      </label>
      <BaseSelect
        :model-value="paymentType"
        :options="paymentTypeOptions"
        :searchable="false"
        @update:model-value="$emit('update:paymentType', $event); $emit('apply')"
      />
    </div>

    <!-- Filter Section: Invoice Status -->
    <div class="space-y-1.5">
      <label class="text-xs font-bold text-slate-600 dark:text-slate-400 flex items-center gap-1.5">
        <CheckCircle2 class="w-3.5 h-3.5 text-emerald-500" />
        <span>{{ $t('invoices.invoice_status') }}</span>
      </label>
      <BaseSelect
        :model-value="status"
        :options="statusOptions"
        :searchable="false"
        @update:model-value="$emit('update:status', $event); $emit('apply')"
      />
    </div>

    <!-- Filter Section: Custom Date Range -->
    <div class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-800">
      <label class="text-xs font-bold text-slate-600 dark:text-slate-400 flex items-center gap-1.5">
        <Calendar class="w-3.5 h-3.5 text-amber-500" />
        <span>{{ $t('invoices.custom_date_range') }}</span>
      </label>
      <div class="space-y-2">
        <BaseInput
          :model-value="dateFrom"
          type="date"
          :label="$t('invoices.from_date')"
          input-class="font-mono text-xs"
          @update:model-value="$emit('update:dateFrom', $event); $emit('apply')"
        />
        <BaseInput
          :model-value="dateTo"
          type="date"
          :label="$t('invoices.to_date')"
          input-class="font-mono text-xs"
          @update:model-value="$emit('update:dateTo', $event); $emit('apply')"
        />
      </div>
    </div>
  </FilterSidebar>
</template>

<script setup>
import { Store, CreditCard, CheckCircle2, Calendar } from 'lucide-vue-next';
import FilterSidebar from '../Common/FilterSidebar.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseInput from '../Form/BaseInput.vue';

defineProps({
  isOpen: { type: Boolean, default: false },
  storeId: { type: [String, Number], default: 'all' },
  paymentType: { type: String, default: 'all' },
  status: { type: String, default: 'all' },
  dateFrom: { type: String, default: '' },
  dateTo: { type: String, default: '' },
  storeOptions: { type: Array, default: () => [] },
  paymentTypeOptions: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
});

defineEmits([
  'close',
  'apply',
  'reset',
  'update:storeId',
  'update:paymentType',
  'update:status',
  'update:dateFrom',
  'update:dateTo',
]);
</script>
