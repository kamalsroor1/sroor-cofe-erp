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
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
            {{ $t('common.amount') }} <span class="text-rose-500">*</span>
          </label>
          <input
            :value="form.amount"
            @input="$emit('update:field', 'amount', $event.target.value)"
            type="number"
            step="0.001"
            required
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-base font-bold text-rose-500 dark:text-rose-400 font-mono focus:ring-2 focus:ring-rose-500 focus:outline-none"
            placeholder="0.00"
          >
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
            {{ $t('common.date') }} <span class="text-rose-500">*</span>
          </label>
          <input
            :value="form.expense_date"
            @input="$emit('update:field', 'expense_date', $event.target.value)"
            type="date"
            required
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
          >
        </div>
      </div>

      <!-- Payment Method -->
      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
          {{ $t('invoices.payment_method') }} <span class="text-rose-500">*</span>
        </label>
        <select
          :value="form.payment_method"
          @change="$emit('update:field', 'payment_method', $event.target.value)"
          required
          class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
        >
          <option value="cash">💵 {{ $t('treasury.method_cash_drawer') }}</option>
          <option value="instapay">⚡ {{ $t('contacts.instapay') }}</option>
          <option value="e_wallet">📱 {{ $t('contacts.wallet') }}</option>
          <option value="visa">💳 {{ $t('treasury.method_visa') }}</option>
          <option value="bank_transfer">🏦 {{ $t('contacts.bank_transfer') }}</option>
          <option value="check">📄 {{ $t('invoices.check') }}</option>
        </select>
      </div>

      <!-- Notes -->
      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
          {{ $t('common.notes') }}
        </label>
        <textarea
          :value="form.notes"
          @input="$emit('update:field', 'notes', $event.target.value)"
          rows="2"
          class="w-full p-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
          :placeholder="$t('expenses.notes_placeholder')"
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
          {{ $t('common.save') }}
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseButton from '../Common/BaseButton.vue';

defineProps({
  show: { type: Boolean, default: false },
  editingExpense: { type: Object, default: null },
  form: { type: Object, default: () => ({}) },
  costCenterModalOptions: { type: Array, default: () => [] },
  quickCategories: { type: Array, default: () => [] },
  submitting: { type: Boolean, default: false },
});

defineEmits(['close', 'save', 'update:field']);
</script>
