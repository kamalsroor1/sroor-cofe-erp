<template>
  <div class="p-5 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-lg space-y-4 font-tajawal">
    <h2 class="text-xs font-black text-theme-primary flex items-center gap-2">
      <Factory class="w-4 h-4" />
      <span>{{ $t('purchases.supplier_po_section') }}</span>
    </h2>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <!-- Supplier Select -->
      <div>
        <BaseSelect
          :model-value="supplierId"
          @update:model-value="$emit('update:supplierId', $event)"
          :options="supplierOptions"
          :label="$t('purchases.supplier')"
          :placeholder="$t('purchases.select_supplier')"
          required
        />
      </div>

      <!-- Purchase Date -->
      <div>
        <BaseDatePicker
          :model-value="purchaseDate"
          @update:model-value="$emit('update:purchaseDate', $event)"
          :label="$t('purchases.purchase_date')"
          required
        />
      </div>

      <!-- Supplier Invoice Ref -->
      <div>
        <BaseInput
          :model-value="supplierInvoiceRef"
          @update:model-value="$emit('update:supplierInvoiceRef', $event)"
          :label="$t('purchases.supplier_invoice_ref_label')"
          placeholder="INV-SUP-1234..."
          class="font-mono"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { Factory } from 'lucide-vue-next';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseDatePicker from '../Form/BaseDatePicker.vue';

defineProps({
  supplierId: { type: [String, Number], default: '' },
  purchaseDate: { type: String, default: '' },
  supplierInvoiceRef: { type: String, default: '' },
  supplierOptions: { type: Array, default: () => [] },
});

defineEmits(['update:supplierId', 'update:purchaseDate', 'update:supplierInvoiceRef']);
</script>
