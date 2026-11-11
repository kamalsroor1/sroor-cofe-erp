<template>
  <AppModal
    :show="show"
    :title="editingStore ? $t('inventory.edit_store') : $t('inventory.add_new_store')"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('submit')" class="space-y-4 font-tajawal">
      <!-- Store Name -->
      <BaseInput
        :model-value="form.name"
        @update:model-value="updateForm('name', $event)"
        :label="$t('inventory.store_name')"
        :placeholder="$t('inventory.store_name_placeholder')"
        required
      />

      <!-- Code & Type Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <BaseInput
          :model-value="form.code"
          @update:model-value="updateForm('code', $event)"
          :label="$t('inventory.store_code')"
          :placeholder="$t('inventory.store_code_placeholder')"
          class="font-mono uppercase"
        />

        <BaseSelect
          :model-value="form.type"
          @update:model-value="updateForm('type', $event)"
          :label="$t('inventory.store_type')"
          :options="storeTypeOptions"
          :searchable="false"
          required
        />
      </div>

      <!-- Address & Phone Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <BaseInput
          :model-value="form.address"
          @update:model-value="updateForm('address', $event)"
          :label="$t('inventory.address')"
          :placeholder="$t('inventory.address_placeholder')"
        />

        <BaseInput
          :model-value="form.phone"
          @update:model-value="updateForm('phone', $event)"
          :label="$t('inventory.phone')"
          :placeholder="$t('inventory.phone_placeholder')"
          dir="ltr"
        />
      </div>

      <!-- Checkboxes (Is Main / Is Active) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
        <BaseCheckbox
          :model-value="form.is_main"
          @update:model-value="updateForm('is_main', $event)"
          :label="$t('inventory.is_main_branch')"
        />

        <BaseCheckbox
          v-if="editingStore"
          :model-value="form.is_active"
          @update:model-value="updateForm('is_active', $event)"
          :label="$t('inventory.is_active_branch')"
        />
      </div>

      <!-- POS-07: the branch's discount cap (own save; only for users who may manage POS settings) -->
      <StorePosDiscountSettings v-if="editingStore && canManagePosSettings" :store-id="editingStore.id" />

      <!-- POS catalog display: cards per row + item images (own save; settings.manage only) -->
      <StorePosCatalogSettings v-if="editingStore && canManagePosSettings" :store-id="editingStore.id" />

      <!-- POS-10: per-branch rule - sales need an open cash shift -->
      <div v-if="editingStore && showShiftSetting" class="pt-1">
        <BaseCheckbox
          :model-value="requireShift"
          @update:model-value="emit('update:requireShift', $event)"
          :label="$t('pos.shift_setting_label')"
        />
        <p class="mt-1 text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('pos.shift_setting_hint') }}</p>
      </div>

      <!-- Form Actions Footer -->
      <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
        <BaseButton type="button" variant="secondary" size="md" @click="$emit('close')">
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
import { computed } from 'vue';
import { Plus, Save } from 'lucide-vue-next';
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseCheckbox from '../Form/BaseCheckbox.vue';
import BaseButton from '../Common/BaseButton.vue';
import StorePosDiscountSettings from './StorePosDiscountSettings.vue';
import StorePosCatalogSettings from './StorePosCatalogSettings.vue';
import { useAuthStore } from '../../stores/auth';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();
const authStore = useAuthStore();
// Discount cap / scale / quick keys are settings.manage-only on the server (the shift rule has its own switch).
const canManagePosSettings = computed(() => authStore.can('settings.manage'));

const props = defineProps({
  show: { type: Boolean, default: false },
  editingStore: { type: Object, default: null },
  form: { type: Object, default: () => ({}) },
  submitting: { type: Boolean, default: false },
  showShiftSetting: { type: Boolean, default: false },
  requireShift: { type: Boolean, default: true },
});

const emit = defineEmits(['close', 'submit', 'update:form', 'update:requireShift']);

// Never mutate the prop: emit a patched shallow copy and let the parent apply it.
const updateForm = (key, value) => emit('update:form', { ...props.form, [key]: value });

const storeTypeOptions = computed(() => [
  { value: 'retail_shop', label: t('inventory.retail_shop') },
  { value: 'warehouse', label: t('inventory.warehouse') },
  { value: 'van', label: t('inventory.distribution_van') },
]);
</script>
