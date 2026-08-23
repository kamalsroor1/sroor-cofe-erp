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
      <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 pt-2">
        <label class="flex items-center gap-2 cursor-pointer select-none">
          <input
            v-model="form.is_main"
            type="checkbox"
            class="w-4 h-4 rounded bg-slate-100 dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-theme-primary focus:ring-theme-primary/20"
          />
          <span class="text-xs font-bold text-slate-700 dark:text-slate-300 font-tajawal">{{ $t('inventory.is_main_branch') }}</span>
        </label>

        <label v-if="editingStore" class="flex items-center gap-2 cursor-pointer select-none">
          <input
            v-model="form.is_active"
            type="checkbox"
            class="w-4 h-4 rounded bg-slate-100 dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-theme-primary focus:ring-theme-primary/20"
          />
          <span class="text-xs font-bold text-slate-700 dark:text-slate-300 font-tajawal">{{ $t('inventory.is_active_branch') }}</span>
        </label>
      </div>

      <!-- Form Actions Footer -->
      <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
        <button
          type="button"
          @click="$emit('close')"
          class="min-h-[44px] px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-2xl text-xs font-bold font-tajawal cursor-pointer transition-all active:scale-95"
        >
          {{ $t('common.cancel') }}
        </button>

        <button
          type="submit"
          :disabled="isSubmitting"
          class="min-h-[44px] px-5 py-2 bg-theme-gradient text-white font-black rounded-2xl text-xs font-tajawal shadow-lg shadow-theme-primary/20 disabled:opacity-50 cursor-pointer flex items-center gap-2 transition-all active:scale-95"
        >
          <span v-if="isSubmitting" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          <span>{{ $t('common.save') }}</span>
        </button>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import { computed } from 'vue';
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  editingStore: {
    type: Object,
    default: null,
  },
  form: {
    type: Object,
    required: true,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['close', 'submit']);

const storeTypeOptions = computed(() => [
  { value: 'retail_shop', label: `🏬 ${t('inventory.retail_shop')}` },
  { value: 'warehouse', label: `🏭 ${t('inventory.warehouse')}` },
  { value: 'van', label: `🚚 ${t('inventory.distribution_van')}` },
]);
</script>
