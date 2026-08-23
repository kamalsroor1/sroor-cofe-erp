<template>
  <AppModal
    :show="show"
    :title="editingItem ? ($t('inventory.edit_item') || 'تعديل بيانات الصنف') : ($t('inventory.add_new_item') || 'إضافة صنف جديد')"
    :icon="Package"
    max-width="3xl"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('submit')" class="space-y-4 font-tajawal">
      <!-- Row 1: Name & Code/Barcode -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <BaseInput
          v-model="form.name"
          :label="$t('inventory.item_name') || 'اسم الصنف التجاري'"
          :required="true"
          :placeholder="$t('inventory.item_name_placeholder') || 'اكتب اسم الصنف...'"
          :error="errors?.name"
        />

        <BaseInput
          v-model="form.code"
          :label="`${$t('inventory.code') || 'الكود'} (${$t('inventory.barcode') || 'الباركود'})`"
          :placeholder="$t('inventory.auto_code_placeholder') || 'امسح الباركود أو سيتم توليده تلقائياً...'"
          :error="errors?.code"
          dir="ltr"
          input-class="font-mono text-xs"
        />
      </div>

      <!-- Row 2: Category & Unit -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <BaseSelect
          v-model="form.category"
          :label="$t('inventory.category') || 'القسم / التصنيف'"
          :placeholder="$t('inventory.category_placeholder') || 'اختر التصنيف أو القسم'"
          :options="categoryOptions"
          :error="errors?.category"
        />

        <BaseSelect
          v-model="form.unit"
          :label="$t('inventory.unit') || 'وحدة القياس'"
          :required="true"
          :options="unitOptions"
          :searchable="false"
          :error="errors?.unit"
        />
      </div>

      <!-- Row 3: Pricing Grid (Cost, Retail, Min Selling/Wholesale, Min Stock Level) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <BaseNumberInput
          v-model="form.cost_price"
          :label="$t('inventory.cost_price') || 'سعر الشراء / التكلفة'"
          :required="true"
          :min="0"
          :step="0.001"
          :error="errors?.cost_price"
        />

        <BaseNumberInput
          v-model="form.selling_price"
          :label="`${$t('inventory.selling_price') || 'سعر البيع'} (قطاعي)`"
          :required="true"
          :min="0"
          :step="0.001"
          :error="errors?.selling_price"
        />

        <BaseNumberInput
          v-model="form.min_selling_price"
          :label="$t('inventory.min_selling_price') || 'أقل سعر بيع (جملة)'"
          :min="0"
          :step="0.001"
          :error="errors?.min_selling_price"
        />

        <BaseNumberInput
          v-model="form.min_stock_level"
          :label="$t('inventory.min_stock_level') || 'حد الطلب (تنبيه النواقص)'"
          :min="0"
          :step="0.001"
          :error="errors?.min_stock_level"
        />
      </div>

      <!-- Row 4: Notes -->
      <BaseTextarea
        v-model="form.notes"
        :label="$t('common.notes') || 'ملاحظات وتفاصيل إضافية'"
        :placeholder="$t('inventory.item_notes_placeholder') || 'أي تفاصيل خاصة بالصنف...'"
        :rows="2"
      />

      <!-- Modal Footer Actions -->
      <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
        <BaseButton
          type="button"
          variant="ghost"
          size="md"
          :label="$t('common.cancel')"
          @click="$emit('close')"
        />

        <BaseButton
          type="submit"
          variant="gradient"
          size="md"
          :loading="isSubmitting"
          :label="$t('common.save')"
        />
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import { computed } from 'vue';
import { Package } from 'lucide-vue-next';
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseNumberInput from '../Form/BaseNumberInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseTextarea from '../Form/BaseTextarea.vue';
import BaseButton from '../Common/BaseButton.vue';

const props = defineProps({
  show: { type: Boolean, default: false },
  editingItem: { type: Object, default: null },
  form: { type: Object, required: true },
  categories: { type: Array, default: () => [] },
  units: { type: Array, default: () => ['كجم', 'جرام', 'قطعة', 'علبة', 'كرتونة', 'شيكارة', 'طرد', 'دستة', 'لتر'] },
  errors: { type: Object, default: () => ({}) },
  isSubmitting: { type: Boolean, default: false },
});

defineEmits(['close', 'submit']);

const categoryOptions = computed(() => {
  return props.categories.map((c) => ({
    value: typeof c === 'object' ? c.name : c,
    label: typeof c === 'object' ? `${c.icon || '☕'} ${c.name}` : c,
  }));
});

const unitOptions = computed(() => {
  return props.units.map((u) => ({
    value: u,
    label: u,
  }));
});
</script>