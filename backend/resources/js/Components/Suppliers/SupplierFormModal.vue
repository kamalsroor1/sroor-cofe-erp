<template>
  <AppModal
    :show="show"
    :title="editingSupplier ? $t('contacts.edit_supplier') : $t('contacts.add_supplier')"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('save')" class="space-y-4 font-tajawal">
      <!-- Name & Company Name Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <BaseInput
          :model-value="form.name"
          @update:model-value="$emit('update:field', 'name', $event)"
          :label="$t('contacts.supplier_name')"
          :placeholder="$t('contacts.supplier_name_placeholder')"
          required
        />

        <BaseInput
          :model-value="form.company_name"
          @update:model-value="$emit('update:field', 'company_name', $event)"
          :label="$t('contacts.company_name')"
          :placeholder="$t('contacts.company_name_placeholder')"
        />
      </div>

      <!-- Phone & Address Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <BaseInput
          :model-value="form.phone"
          @update:model-value="$emit('update:field', 'phone', $event)"
          :label="$t('contacts.phone')"
          :placeholder="$t('contacts.phone_placeholder')"
          type="tel"
          dir="ltr"
          class="font-mono"
        />

        <BaseInput
          :model-value="form.address"
          @update:model-value="$emit('update:field', 'address', $event)"
          :label="$t('contacts.address')"
          :placeholder="$t('contacts.address_placeholder')"
        />
      </div>

      <!-- Initial Balance (Only for new suppliers) -->
      <div v-if="!editingSupplier">
        <BaseNumberInput
          :model-value="form.initial_balance"
          @update:model-value="$emit('update:field', 'initial_balance', Number($event))"
          :label="$t('contacts.initial_balance')"
          :hint="$t('contacts.initial_balance_hint')"
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
          {{ editingSupplier ? $t('common.save_changes') : $t('contacts.save_supplier') }}
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
  editingSupplier: { type: Object, default: null },
  form: { type: Object, default: () => ({}) },
  saving: { type: Boolean, default: false },
});

defineEmits(['close', 'save', 'update:field']);
</script>
