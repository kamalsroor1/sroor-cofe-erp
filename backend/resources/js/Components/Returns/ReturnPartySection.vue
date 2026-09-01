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
      <div v-if="form.return_type === 'sales_return'">
        <BaseSelect
          :model-value="form.customer_id"
          @update:model-value="$emit('update:field', 'customer_id', Number($event))"
          :options="customerOptions"
          :label="$t('returns.customer_from')"
          :placeholder="$t('pos.choose_invoice_customer')"
          required
        />
      </div>

      <!-- Supplier Field -->
      <div v-else>
        <BaseSelect
          :model-value="form.supplier_id"
          @update:model-value="$emit('update:field', 'supplier_id', Number($event))"
          :options="supplierOptions"
          :label="$t('returns.supplier_to')"
          :placeholder="$t('purchases.select_supplier')"
          required
        />
      </div>

      <!-- Return Date -->
      <div>
        <BaseDatePicker
          :model-value="form.return_date"
          @update:model-value="$emit('update:field', 'return_date', $event)"
          :label="$t('returns.return_date')"
          required
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseDatePicker from '../Form/BaseDatePicker.vue';

const props = defineProps({
  form: { type: Object, required: true },
  customers: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
});

defineEmits(['type-change', 'update:field']);

const customerOptions = computed(() =>
  props.customers.map(c => ({
    value: c.id,
    label: `${c.name} ${c.phone ? '(' + c.phone + ')' : ''}`
  }))
);

const supplierOptions = computed(() =>
  props.suppliers.map(s => ({
    value: s.id,
    label: `${s.name} ${s.phone ? '(' + s.phone + ')' : ''}`
  }))
);
</script>
