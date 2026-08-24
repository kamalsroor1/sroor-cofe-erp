<template>
  <AppModal
    :show="show"
    :title="editingCustomer ? $t('contacts.edit_customer') : $t('contacts.add_customer')"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('save')" class="space-y-4 font-tajawal">
      <!-- Name & Phone Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <BaseInput
          :model-value="form.name"
          @update:model-value="$emit('update:field', 'name', $event)"
          :label="$t('contacts.customer_name')"
          :placeholder="$t('contacts.customer_name_placeholder')"
          required
        />

        <BaseInput
          :model-value="form.phone"
          @update:model-value="$emit('update:field', 'phone', $event)"
          :label="$t('contacts.phone')"
          :placeholder="$t('contacts.phone_placeholder')"
          type="tel"
          dir="ltr"
          class="font-mono"
        />
      </div>

      <!-- Address & Tax Number Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <BaseInput
          :model-value="form.address"
          @update:model-value="$emit('update:field', 'address', $event)"
          :label="$t('contacts.address')"
          :placeholder="$t('contacts.address_placeholder')"
        />

        <BaseInput
          :model-value="form.tax_number"
          @update:model-value="$emit('update:field', 'tax_number', $event)"
          :label="$t('contacts.tax_number')"
          :placeholder="$t('contacts.tax_number_placeholder')"
          class="font-mono"
        />
      </div>

      <!-- Initial Balance (Only for new customers) -->
      <div v-if="!editingCustomer">
        <BaseNumberInput
          :model-value="form.initial_balance"
          @update:model-value="$emit('update:field', 'initial_balance', Number($event))"
          :label="$t('contacts.initial_balance')"
          :hint="$t('contacts.customer_initial_balance_hint')"
          :decimals="3"
        />
      </div>

      <!-- Notes Textarea -->
      <BaseTextarea
        :model-value="form.notes"
        @update:model-value="$emit('update:field', 'notes', $event)"
        :label="$t('common.notes')"
        :rows="2"
        :placeholder="$t('contacts.notes_placeholder')"
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
          :loading="saving"
          class="font-black shadow-lg shadow-theme-primary/20"
        >
          {{ editingCustomer ? $t('common.save_changes') : $t('contacts.save_customer') }}
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseNumberInput from '../Form/BaseNumberInput.vue';
import BaseTextarea from '../Form/BaseTextarea.vue';
import BaseButton from '../Common/BaseButton.vue';

defineProps({
  show: { type: Boolean, default: false },
  editingCustomer: { type: Object, default: null },
  form: { type: Object, default: () => ({}) },
  saving: { type: Boolean, default: false },
});

defineEmits(['close', 'save', 'update:field']);
</script>
