<template>
  <AppModal
    :show="show"
    :title="editingStore ? $t('inventory.edit_store') : $t('inventory.add_new_store')"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('submit')" class="space-y-4 font-tajawal">
      <!-- Store Name -->
      <BaseInput
        v-model="form.name"
        :label="$t('inventory.store_name')"
        :placeholder="$t('inventory.store_name_placeholder')"
        required
      />

      <!-- Code & Type Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <BaseInput
          v-model="form.code"
          :label="$t('inventory.store_code')"
          :placeholder="$t('inventory.store_code_placeholder')"
          class="font-mono uppercase"
        />

        <BaseSelect
          v-model="form.type"
          :label="$t('inventory.store_type')"
          :options="storeTypeOptions"
          :searchable="false"
          required
        />
      </div>

      <!-- Address & Phone Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <BaseInput
          v-model="form.address"
          :label="$t('inventory.address')"
          :placeholder="$t('inventory.address_placeholder')"
        />

        <BaseInput
          v-model="form.phone"
          :label="$t('inventory.phone')"
          :placeholder="$t('inventory.phone_placeholder')"
          dir="ltr"
        />
      </div>

      <!-- Checkboxes (Is Main / Is Active) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
        <BaseCheckbox
          v-model="form.is_main"
          :label="$t('inventory.is_main_branch')"
        />

        <BaseCheckbox
          v-if="editingStore"
          v-model="form.is_active"
          :label="$t('inventory.is_active_branch')"
        />
      </div>

      <!-- Form Actions Footer -->
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
          :loading="submitting"
          class="font-black shadow-lg shadow-theme-primary/20"
        >
          <Plus v-if="!editingStore && !submitting" class="w-4 h-4" />
          <Save v-else-if="!submitting" class="w-4 h-4" />
          <span>{{ editingStore ? $t('common.save_changes') : $t('inventory.create_store') }}</span>
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import { Plus, Save } from 'lucide-vue-next';
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseCheckbox from '../Form/BaseCheckbox.vue';
import BaseButton from '../Common/BaseButton.vue';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();

defineProps({
  show: { type: Boolean, default: false },
  editingStore: { type: Object, default: null },
  form: { type: Object, default: () => ({}) },
  submitting: { type: Boolean, default: false },
});

defineEmits(['close', 'submit']);

const storeTypeOptions = [
  { value: 'retail_shop', label: '🏬 ' + t('inventory.retail_shop') },
  { value: 'warehouse', label: '🏭 ' + t('inventory.warehouse') },
  { value: 'van', label: '🚚 ' + t('inventory.distribution_van') },
];
</script>
