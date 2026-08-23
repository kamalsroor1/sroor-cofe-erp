<template>
  <AppModal
    :show="show"
    :title="$t('treasury.record_journal_expense_title')"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('submit')" class="space-y-4 font-tajawal">
      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
          {{ $t('expenses.expense_item') }} <span class="text-rose-500">*</span>
        </label>
        <input
          :value="form.title"
          @input="$emit('update:field', 'title', $event.target.value)"
          type="text"
          required
          class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
          :placeholder="$t('treasury.expense_title_placeholder')"
        >
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
            {{ $t('common.amount') }} <span class="text-rose-500">*</span>
          </label>
          <input
            :value="form.amount"
            @input="$emit('update:field', 'amount', $event.target.value)"
            type="number"
            step="0.001"
            required
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-sm font-bold text-rose-500 dark:text-rose-400 font-mono focus:ring-2 focus:ring-rose-500 focus:outline-none"
            placeholder="0.00"
          >
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
            {{ $t('treasury.cost_center') }} <span class="text-rose-500">*</span>
          </label>
          <select
            :value="form.cost_center"
            @change="$emit('update:field', 'cost_center', $event.target.value)"
            required
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
          >
            <option value="operational">{{ $t('treasury.cost_center_operational') }}</option>
            <option value="hospitality">{{ $t('treasury.cost_center_hospitality') }}</option>
            <option value="packaging">{{ $t('treasury.cost_center_packaging') }}</option>
            <option value="utilities">{{ $t('treasury.cost_center_utilities') }}</option>
            <option value="salaries">{{ $t('treasury.cost_center_salaries') }}</option>
            <option value="maintenance">{{ $t('treasury.cost_center_maintenance') }}</option>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
          {{ $t('treasury.payment_method') }} <span class="text-rose-500">*</span>
        </label>
        <select
          :value="form.payment_method"
          @change="$emit('update:field', 'payment_method', $event.target.value)"
          required
          class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
        >
          <option value="cash">{{ $t('treasury.method_cash_drawer') }}</option>
          <option value="instapay">{{ $t('treasury.method_instapay') }}</option>
          <option value="e_wallet">{{ $t('treasury.method_wallet') }}</option>
          <option value="visa">{{ $t('treasury.method_visa') }}</option>
        </select>
      </div>

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
          {{ $t('treasury.submit_expense_btn') }}
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import AppModal from '../Common/AppModal.vue';
import BaseButton from '../Common/BaseButton.vue';

defineProps({
  show: { type: Boolean, default: false },
  form: { type: Object, default: () => ({}) },
  submitting: { type: Boolean, default: false },
});

defineEmits(['close', 'submit', 'update:field']);
</script>
