<template>
  <div class="p-5 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-lg space-y-4">
    <h2 class="text-xs font-black text-theme-primary flex items-center gap-2">
      <Factory class="w-4 h-4" />
      <span>{{ $t('purchases.supplier_po_section') }}</span>
    </h2>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <!-- Supplier Select -->
      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
          {{ $t('purchases.supplier') }} <span class="text-rose-500">*</span>
        </label>
        <BaseSelect
          :model-value="supplierId"
          @update:model-value="$emit('update:supplierId', $event)"
          :options="supplierOptions"
          :placeholder="$t('purchases.select_supplier')"
          required
        />
      </div>

      <!-- Purchase Date -->
      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
          {{ $t('purchases.purchase_date') }} <span class="text-rose-500">*</span>
        </label>
        <input
          :value="purchaseDate"
          @input="$emit('update:purchaseDate', $event.target.value)"
          type="date"
          required
          class="w-full h-11 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
        >
      </div>

      <!-- Supplier Invoice Ref -->
      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
          {{ $t('purchases.supplier_invoice_ref_label') }}
        </label>
        <BaseInput
          :model-value="supplierInvoiceRef"
          @update:model-value="$emit('update:supplierInvoiceRef', $event)"
          placeholder="INV-SUP-1234..."
          input-class="font-mono text-xs"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { Factory } from 'lucide-vue-next';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseInput from '../Form/BaseInput.vue';

defineProps({
  supplierId: { type: [String, Number], default: '' },
  purchaseDate: { type: String, default: '' },
  supplierInvoiceRef: { type: String, default: '' },
  supplierOptions: { type: Array, default: () => [] },
});

defineEmits(['update:supplierId', 'update:purchaseDate', 'update:supplierInvoiceRef']);
</script>
