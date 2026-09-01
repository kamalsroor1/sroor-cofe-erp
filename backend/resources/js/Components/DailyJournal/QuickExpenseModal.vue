<template>
  <AppModal
    :show="show"
    :title="$t('treasury.record_journal_expense_title')"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('submit')" class="space-y-4 font-tajawal">
      <div>
        <BaseInput
          :model-value="form.title"
          @update:model-value="$emit('update:field', 'title', $event)"
          :label="$t('expenses.expense_item')"
          :placeholder="$t('treasury.expense_title_placeholder')"
          required
        />
      </div>

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
          <BaseSelect
            :model-value="form.cost_center"
            @update:model-value="$emit('update:field', 'cost_center', $event)"
            :options="costCenterOptions"
            :label="$t('treasury.cost_center')"
            :searchable="false"
            required
          />
        </div>
      </div>

      <div>
        <BaseSelect
          :model-value="form.payment_method"
          @update:model-value="$emit('update:field', 'payment_method', $event)"
          :options="paymentMethodOptions"
          :label="$t('treasury.payment_method')"
          :searchable="false"
          required
        />
      </div>

      <div>
        <BaseInput
          :model-value="form.category"
          @update:model-value="$emit('update:field', 'category', $event)"
          :label="$t('expenses.category')"
          :placeholder="$t('expenses.category_placeholder')"
          required
        />
      </div>

      <div>
        <BaseInput
          :model-value="form.notes"
          @update:model-value="$emit('update:field', 'notes', $event)"
          :label="$t('common.notes')"
          :placeholder="$t('expenses.notes_placeholder')"
        />
      </div>

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
          :loading="submitting"
          class="font-black shadow-theme-primary shadow-lg"
        >
          {{ $t('treasury.record_expense_btn') }}
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
import BaseButton from '../Common/BaseButton.vue';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();

defineProps({
  show: { type: Boolean, default: false },
  form: { type: Object, default: () => ({}) },
  submitting: { type: Boolean, default: false },
});

defineEmits(['close', 'submit', 'update:field']);

const costCenterOptions = [
  { value: 'operational', label: t('treasury.cost_center_operational') },
  { value: 'hospitality', label: t('treasury.cost_center_hospitality') },
  { value: 'packaging', label: t('treasury.cost_center_packaging') },
  { value: 'utilities', label: t('treasury.cost_center_utilities') },
  { value: 'salaries', label: t('treasury.cost_center_salaries') },
  { value: 'maintenance', label: t('treasury.cost_center_maintenance') },
];

const paymentMethodOptions = [
  { value: 'cash', label: t('treasury.method_cash_drawer') },
  { value: 'instapay', label: t('contacts.instapay') },
  { value: 'e_wallet', label: t('contacts.wallet') },
  { value: 'visa', label: t('treasury.method_visa') },
  { value: 'bank_transfer', label: t('contacts.bank_transfer') },
];
</script>
