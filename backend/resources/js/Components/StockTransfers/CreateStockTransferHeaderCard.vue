<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
    <h2 class="text-xs font-bold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 pb-2 flex items-center gap-2">
      <span>🏢</span>
      <span>{{ $t('inventory.branches_and_date_section') }}</span>
    </h2>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <!-- From Store -->
      <div>
        <BaseSelect
          :model-value="form.from_store_id"
          @update:model-value="form.from_store_id = Number($event)"
          :label="$t('inventory.from_store_label')"
          :options="fromStoreOptions"
          :placeholder="$t('inventory.choose_source_store')"
          required
        />
      </div>

      <!-- To Store -->
      <div>
        <BaseSelect
          :model-value="form.to_store_id"
          @update:model-value="form.to_store_id = Number($event)"
          :label="$t('inventory.to_store_label')"
          :options="toStoreOptions"
          :placeholder="$t('inventory.choose_dest_store')"
          required
        />
      </div>

      <!-- Transfer Date -->
      <div>
        <BaseInput
          v-model="form.transfer_date"
          type="date"
          :label="$t('inventory.transfer_date_label')"
          required
        />
      </div>

      <!-- Notes -->
      <div class="sm:col-span-3">
        <BaseInput
          v-model="form.notes"
          type="text"
          :label="$t('inventory.blend_notes')"
          :placeholder="$t('inventory.transfer_notes_placeholder')"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import BaseSelect from '../Form/BaseSelect.vue';
import BaseInput from '../Form/BaseInput.vue';

defineProps({
  form: { type: Object, required: true },
  fromStoreOptions: { type: Array, default: () => [] },
  toStoreOptions: { type: Array, default: () => [] },
});
</script>
