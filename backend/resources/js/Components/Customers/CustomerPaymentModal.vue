<template>
  <AppModal
    :show="show"
    :title="`${$t('contacts.collect_payment_from')}: ${targetCustomer?.name || ''}`"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('save')" class="space-y-4 font-tajawal">
      <!-- Current Debt Alert -->
      <div class="p-3.5 bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl flex items-center justify-between">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('contacts.current_balance') }}:</span>
        <span
          class="text-base font-black font-mono"
          :class="targetCustomer?.current_balance > 0 ? 'text-rose-500' : 'text-emerald-500'"
        >
          {{ formatMoney(targetCustomer?.current_balance || 0) }} {{ $t('common.currency') }}
        </span>
      </div>

      <!-- Payment Amount -->
      <BaseNumberInput
        :model-value="paymentForm.amount"
        @update:model-value="$emit('update:field', 'amount', $event)"
        :label="$t('contacts.amount')"
        :decimals="3"
        required
        class="font-mono text-theme-primary font-bold"
      />

      <!-- Payment Method & Date Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <BaseSelect
          :model-value="paymentForm.payment_method"
          @update:model-value="$emit('update:field', 'payment_method', $event)"
          :options="paymentMethodOptions"
          :label="$t('contacts.payment_method')"
          :searchable="false"
          required
        />

        <BaseDatePicker
          :model-value="paymentForm.payment_date"
          @update:model-value="$emit('update:field', 'payment_date', $event)"
          :label="$t('contacts.payment_date')"
          required
        />
      </div>

      <!-- Notes Textarea -->
      <BaseTextarea
        :model-value="paymentForm.notes"
        @update:model-value="$emit('update:field', 'notes', $event)"
        :label="$t('common.notes')"
        :rows="2"
        :placeholder="$t('contacts.payment_notes_placeholder')"
      />

      <!-- Action Buttons -->
      <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
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
          :loading="savingPayment"
          class="font-black shadow-lg shadow-theme-primary/20"
        >
          {{ $t('contacts.confirm_collection') }}
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import AppModal from '../Common/AppModal.vue';
import BaseNumberInput from '../Form/BaseNumberInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseDatePicker from '../Form/BaseDatePicker.vue';
import BaseTextarea from '../Form/BaseTextarea.vue';
import BaseButton from '../Common/BaseButton.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useTrans } from '../../Composables/useTrans';

const { formatMoney } = useFormatters();
const { t } = useTrans();

defineProps({
  show: { type: Boolean, default: false },
  targetCustomer: { type: Object, default: null },
  paymentForm: { type: Object, default: () => ({}) },
  savingPayment: { type: Boolean, default: false },
});

defineEmits(['close', 'save', 'update:field']);

const paymentMethodOptions = [
  { value: 'cash', label: '💵 ' + t('contacts.cash') },
  { value: 'instapay', label: '⚡ ' + t('contacts.instapay') },
  { value: 'wallet', label: '📱 ' + t('contacts.wallet') },
  { value: 'bank', label: '🏦 ' + t('contacts.bank_transfer') },
];
</script>
