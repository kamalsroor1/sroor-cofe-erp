<template>
  <Transition name="slide-fade">
    <aside
      v-if="isOpen"
      class="w-full lg:w-80 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl sm:rounded-3xl p-4 sm:p-5 shadow-xl space-y-5 shrink-0"
    >
      <!-- Sidebar Header -->
      <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-2">
          <SlidersHorizontal class="w-4 h-4 text-theme-primary" />
          <h3 class="font-black text-sm text-slate-900 dark:text-white">{{ $t('invoices.advanced_filters') }}</h3>
        </div>
        <button
          type="button"
          @click="$emit('close')"
          class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
        >
          ✕
        </button>
      </div>

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
          <div class="flex items-center gap-2">
            <span class="text-xs text-slate-400 font-bold w-6">{{ $t('invoices.from_date') }}:</span>
            <input
              :value="dateFrom"
              type="date"
              @input="$emit('update:dateFrom', $event.target.value)"
              @change="$emit('apply')"
              class="flex-1 min-h-[40px] px-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-mono text-slate-900 dark:text-white"
            />
          </div>
          <div class="flex items-center gap-2">
            <span class="text-xs text-slate-400 font-bold w-6">{{ $t('invoices.to_date') }}:</span>
            <input
              :value="dateTo"
              type="date"
              @input="$emit('update:dateTo', $event.target.value)"
              @change="$emit('apply')"
              class="flex-1 min-h-[40px] px-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-mono text-slate-900 dark:text-white"
            />
          </div>
        </div>
      </div>

      <!-- Sidebar Action Buttons -->
      <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center gap-2">
        <button
          type="button"
          @click="$emit('apply')"
          class="flex-1 min-h-[44px] py-2.5 bg-theme-primary hover:bg-theme-primary-hover text-white rounded-xl text-xs font-black transition cursor-pointer shadow-md text-center active:scale-95 select-none"
        >
          {{ $t('invoices.apply_filters') }}
        </button>
        <button
          type="button"
          @click="$emit('reset')"
          class="min-h-[44px] px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-xs font-bold transition cursor-pointer text-center active:scale-95 select-none"
        >
          {{ $t('invoices.reset_filters') }}
        </button>
      </div>
    </aside>
  </Transition>
</template>

<script setup>
import { SlidersHorizontal, Store, CreditCard, CheckCircle2, Calendar } from 'lucide-vue-next';
import BaseSelect from '../Form/BaseSelect.vue';

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

<style scoped>
.slide-fade-enter-active,
.slide-fade-leave-active {
  transition: all 0.25s ease-out;
}
.slide-fade-enter-from,
.slide-fade-leave-to {
  transform: translateX(20px);
  opacity: 0;
}
</style>
