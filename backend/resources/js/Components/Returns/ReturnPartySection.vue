<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm dark:shadow-lg space-y-4 font-tajawal">
    <h2 class="text-xs font-bold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 pb-2 flex items-center gap-2">
      <span>🔄</span>
      <span>{{ $t('returns.type_and_party_section') }}</span>
    </h2>

    <!-- Return Type Switcher -->
    <div class="grid grid-cols-2 gap-3">
      <button
        type="button"
        @click="$emit('type-change', 'sales_return')"
        class="min-h-[44px] py-3 px-4 rounded-xl text-xs font-black transition border text-center cursor-pointer active:scale-95 select-none"
        :class="form.return_type === 'sales_return' ? 'bg-cyan-600 text-white font-bold border-cyan-400 shadow-md shadow-cyan-500/20' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700'"
      >
        <span>{{ $t('returns.sales_return_option') }}</span>
      </button>

      <button
        type="button"
        @click="$emit('type-change', 'purchase_return')"
        class="min-h-[44px] py-3 px-4 rounded-xl text-xs font-black transition border text-center cursor-pointer active:scale-95 select-none"
        :class="form.return_type === 'purchase_return' ? 'bg-theme-primary text-white font-bold border-theme-primary shadow-md shadow-theme-primary' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700'"
      >
        <span>{{ $t('returns.purchase_return_option') }}</span>
      </button>
    </div>

    <!-- Fields -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
      <!-- Customer Field -->
      <div v-if="form.return_type === 'sales_return'" class="space-y-1">
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
          {{ $t('returns.customer_from') }}
        </label>
        <select
          :value="form.customer_id"
          @change="$emit('update:field', 'customer_id', Number($event.target.value))"
          required
          class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
        >
          <option :value="null" disabled>{{ $t('pos.choose_invoice_customer') }}</option>
          <option v-for="c in customers" :key="c.id" :value="c.id">
            {{ c.name }} {{ c.phone ? `(${c.phone})` : '' }}
          </option>
        </select>
      </div>

      <!-- Supplier Field -->
      <div v-else class="space-y-1">
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
          {{ $t('returns.supplier_to') }}
        </label>
        <select
          :value="form.supplier_id"
          @change="$emit('update:field', 'supplier_id', Number($event.target.value))"
          required
          class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
        >
          <option :value="null" disabled>{{ $t('purchases.select_supplier') }}</option>
          <option v-for="s in suppliers" :key="s.id" :value="s.id">
            {{ s.name }} {{ s.company_name ? `(${s.company_name})` : '' }}
          </option>
        </select>
      </div>

      <!-- Return Date -->
      <div class="space-y-1">
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
          {{ $t('returns.return_date') }} <span class="text-rose-500">*</span>
        </label>
        <input
          :value="form.return_date"
          @input="$emit('update:field', 'return_date', $event.target.value)"
          type="date"
          required
          class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
        >
      </div>

      <!-- Reason -->
      <div class="sm:col-span-2 space-y-1">
        <BaseInput
          :model-value="form.reason"
          @update:model-value="$emit('update:field', 'reason', $event)"
          :label="$t('returns.reason')"
          :placeholder="$t('returns.reason_input_placeholder')"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import BaseInput from '../Form/BaseInput.vue';

defineProps({
  form: { type: Object, default: () => ({}) },
  customers: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
});

defineEmits(['type-change', 'update:field']);
</script>
