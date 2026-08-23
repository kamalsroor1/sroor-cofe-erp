<template>
  <AppModal
    :show="show"
    :title="editingCustomer ? $t('contacts.edit_customer') : $t('contacts.add_customer')"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('save')" class="space-y-4 font-tajawal">
      <!-- Name & Phone Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
            {{ $t('contacts.customer_name') }} <span class="text-rose-500">*</span>
          </label>
          <input
            :value="form.name"
            @input="$emit('update:field', 'name', $event.target.value)"
            type="text"
            required
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
            :placeholder="$t('contacts.customer_name_placeholder')"
          >
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
            {{ $t('contacts.phone') }}
          </label>
          <input
            :value="form.phone"
            @input="$emit('update:field', 'phone', $event.target.value)"
            type="text"
            dir="ltr"
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
            :placeholder="$t('contacts.phone_placeholder')"
          >
        </div>
      </div>

      <!-- Address & Tax Number Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
            {{ $t('contacts.address') }}
          </label>
          <input
            :value="form.address"
            @input="$emit('update:field', 'address', $event.target.value)"
            type="text"
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
            :placeholder="$t('contacts.address_placeholder')"
          >
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
            {{ $t('contacts.tax_number') }}
          </label>
          <input
            :value="form.tax_number"
            @input="$emit('update:field', 'tax_number', $event.target.value)"
            type="text"
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
            :placeholder="$t('contacts.tax_number_placeholder')"
          >
        </div>
      </div>

      <!-- Opening Balance (Create Only) -->
      <div v-if="!editingCustomer">
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
          {{ $t('contacts.opening_balance') }}
        </label>
        <input
          :value="form.opening_balance"
          @input="$emit('update:field', 'opening_balance', $event.target.value)"
          type="number"
          step="0.001"
          class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
          placeholder="0.000"
        >
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
          {{ $t('common.save') }}
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
  editingCustomer: { type: Object, default: null },
  form: { type: Object, default: () => ({}) },
  submitting: { type: Boolean, default: false },
});

defineEmits(['close', 'save', 'update:field']);
</script>
