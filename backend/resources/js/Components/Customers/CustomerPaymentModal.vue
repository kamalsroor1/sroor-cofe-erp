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
      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
          {{ $t('contacts.amount') }} <span class="text-rose-500">*</span>
        </label>
        <input
          :value="paymentForm.amount"
          @input="$emit('update:field', 'amount', $event.target.value)"
          type="number"
          step="0.001"
          required
          autofocus
          class="w-full h-11 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-base font-bold text-theme-primary font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
          placeholder="0.00"
        >
      </div>

      <!-- Payment Method & Date Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
            {{ $t('contacts.payment_method') }} <span class="text-rose-500">*</span>
          </label>
          <select
            :value="paymentForm.payment_method"
            @change="$emit('update:field', 'payment_method', $event.target.value)"
            required
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
          >
            <option value="cash">💵 {{ $t('contacts.cash') }}</option>
            <option value="instapay">⚡ {{ $t('contacts.instapay') }}</option>
            <option value="wallet">📱 {{ $t('contacts.wallet') }}</option>
            <option value="bank">🏦 {{ $t('contacts.bank_transfer') }}</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
            {{ $t('contacts.payment_date') }} <span class="text-rose-500">*</span>
          </label>
          <input
            :value="paymentForm.payment_date"
            @input="$emit('update:field', 'payment_date', $event.target.value)"
            type="date"
            required
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
          >
        </div>
      </div>

      <!-- Notes -->
      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
          {{ $t('common.notes') }}
        </label>
        <textarea
          :value="paymentForm.notes"
          @input="$emit('update:field', 'notes', $event.target.value)"
          rows="2"
          class="w-full p-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
          :placeholder="$t('contacts.notes_placeholder')"
        ></textarea>
      </div>

      <!-- Modal Actions -->
      <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
        <button
          type="button"
          @click="$emit('close')"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold cursor-pointer"
        >
          {{ $t('common.cancel') }}
        </button>

        <BaseButton
          type="submit"
          variant="primary"
          size="md"
          :loading="submitting"
          class="font-black shadow-theme-primary shadow-lg"
        >
          {{ $t('contacts.confirm_collection') }}
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import AppModal from '../Common/AppModal.vue';
import BaseButton from '../Common/BaseButton.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  show: { type: Boolean, default: false },
  targetCustomer: { type: Object, default: null },
  paymentForm: { type: Object, default: () => ({}) },
  submitting: { type: Boolean, default: false },
});

defineEmits(['close', 'save', 'update:field']);
</script>
