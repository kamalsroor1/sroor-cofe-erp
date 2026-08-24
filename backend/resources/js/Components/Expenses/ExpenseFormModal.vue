<template>
  <AppModal
    :show="show"
    :title="editingExpense ? $t('expenses.edit_expense') : $t('expenses.add_expense')"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('save')" class="space-y-4 font-tajawal">
      <!-- Title -->
      <BaseInput
        :model-value="form.title"
        @update:model-value="$emit('update:field', 'title', $event)"
        :label="$t('expenses.expense_item')"
        :required="true"
        :placeholder="$t('expenses.title_placeholder')"
      />

      <!-- Cost Center & Category Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <BaseSelect
          :model-value="form.cost_center"
          @update:model-value="$emit('update:field', 'cost_center', $event)"
          :label="$t('expenses.cost_center')"
          :required="true"
          :options="costCenterModalOptions"
          :searchable="false"
        />

        <BaseInput
          :model-value="form.category"
          @update:model-value="$emit('update:field', 'category', $event)"
          :label="$t('expenses.category')"
          :required="true"
          :placeholder="$t('expenses.category_placeholder')"
        />
      </div>

      <!-- Quick Category Suggestions in Modal -->
      <div v-if="quickCategories.length > 0" class="flex items-center gap-1.5 flex-wrap">
        <span class="text-[11px] text-slate-500 font-bold">{{ $t('expenses.suggestions_label') }}</span>
        <button
          v-for="cat in quickCategories"
          :key="cat"
          type="button"
          @click="$emit('update:field', 'category', cat)"
          class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 cursor-pointer"
        >
          {{ cat }}
        </button>
      </div>

      <!-- Amount & Date Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <BaseNumberInput
            :model-value="form.amount"
            @update:model-value="$emit('update:field', 'amount', $event)"
            :label="$t('common.amount')"
            :decimals="3"
            class="font-mono text-rose-600 dark:text-rose-400 font-bold"
            required
          />
        </div>

        <div>
          <BaseDatePicker
            :model-value="form.expense_date"
            @update:model-value="$emit('update:field', 'expense_date', $event)"
            :label="$t('common.date')"
            required
          />
        </div>
      </div>

      <!-- Payment Method -->
      <div>
        <BaseSelect
          :model-value="form.payment_method"
          @update:model-value="$emit('update:field', 'payment_method', $event)"
          :options="paymentMethodOptions"
          :label="$t('invoices.payment_method')"
          :searchable="false"
          required
        />
      </div>

      <!-- Notes -->
      <BaseTextarea
        :model-value="form.notes"
        @update:model-value="$emit('update:field', 'notes', $event)"
        :label="$t('common.notes')"
        :rows="2"
        :placeholder="$t('expenses.notes_placeholder')"
      />

      <!-- Modal Actions -->
      <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
        <BaseButton
          type="button"
          variant="secondary"
          size="md"
          @click="$emit('close')"
        >
          {{ $t('common.cancel') }}
        </BaseButton>

        <BaseButton
          type="submit"
          variant="primary"
          size="md"
          :loading="saving"
          class="font-bold shadow-theme-primary shadow-md"
        >
          {{ editingExpense ? $t('common.save') : $t('expenses.add_expense') }}
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseNumberInput from '../Form/BaseNumberInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseDatePicker from '../Form/BaseDatePicker.vue';
import BaseTextarea from '../Form/BaseTextarea.vue';
import BaseButton from '../Common/BaseButton.vue';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();

defineProps({
  show: { type: Boolean, default: false },
  editingExpense: { type: Object, default: null },
  form: { type: Object, default: () => ({}) },
  costCenterModalOptions: { type: Array, default: () => [] },
  quickCategories: { type: Array, default: () => [] },
  saving: { type: Boolean, default: false },
});

defineEmits(['close', 'save', 'update:field']);

const paymentMethodOptions = [
  { value: 'cash', label: '💵 ' + t('treasury.method_cash_drawer') },
  { value: 'instapay', label: '⚡ ' + t('contacts.instapay') },
  { value: 'e_wallet', label: '📱 ' + t('contacts.wallet') },
  { value: 'visa', label: '💳 ' + t('treasury.method_visa') },
  { value: 'bank_transfer', label: '🏦 ' + t('contacts.bank_transfer') },
  { value: 'check', label: '📄 ' + t('invoices.check') },
];
</script>
