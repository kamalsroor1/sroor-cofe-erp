<template>
  <div class="space-y-3" data-testid="user-stores-field">
    <fieldset class="space-y-2">
      <legend class="text-xs font-bold text-slate-700 dark:text-slate-300">
        {{ $t('users.branches_label') }}
        <span v-if="required" class="text-rose-500">*</span>
      </legend>
      <p class="text-[11px] text-slate-500 dark:text-slate-400">
        {{ required ? $t('users.branches_hint_required') : $t('users.branches_hint_optional') }}
      </p>

      <p v-if="stores.length === 0" class="text-xs font-bold text-slate-500 dark:text-slate-400">
        {{ $t('users.branches_empty') }}
      </p>

      <div
        v-else
        class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-52 overflow-y-auto rounded-2xl p-1"
        :class="storeIdsError ? 'ring-1 ring-rose-500' : ''"
      >
        <label
          v-for="store in stores"
          :key="store.id"
          class="min-h-[44px] px-3 py-2 rounded-xl border flex items-center gap-3 cursor-pointer select-none transition active:scale-[0.99]"
          :class="
            isSelected(store.id)
              ? 'bg-theme-light border-theme-border text-theme-primary'
              : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300'
          "
        >
          <input
            type="checkbox"
            class="w-5 h-5 shrink-0 cursor-pointer accent-[var(--color-primary)]"
            :checked="isSelected(store.id)"
            :data-testid="`user-store-${store.id}`"
            @change="toggle(store.id)"
          />
          <span class="text-sm font-bold truncate">{{ store.name }}</span>
        </label>
      </div>

      <p v-if="storeIdsError" class="text-xs font-bold text-rose-500" data-testid="user-store-ids-error">
        {{ storeIdsError }}
      </p>
    </fieldset>

    <div v-if="storeIds.length > 0">
      <BaseSelect
        :model-value="defaultStoreId"
        :options="selectedOptions"
        :label="$t('users.default_branch_among_selected')"
        :searchable="false"
        :error="defaultStoreError"
        @update:model-value="$emit('update:defaultStoreId', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import BaseSelect from '../Form/BaseSelect.vue';
import { toggleStoreId } from '../../helpers/userStores';

const props = defineProps({
  stores: { type: Array, default: () => [] },
  storeIds: { type: Array, default: () => [] },
  defaultStoreId: { type: [Number, String], default: null },
  required: { type: Boolean, default: false },
  storeIdsError: { type: String, default: '' },
  defaultStoreError: { type: String, default: '' },
});

const emit = defineEmits(['update:storeIds', 'update:defaultStoreId']);

const isSelected = (id) => props.storeIds.includes(id);
const toggle = (id) => emit('update:storeIds', toggleStoreId(props.storeIds, id));

const selectedOptions = computed(() =>
  props.stores.filter((store) => isSelected(store.id)).map((store) => ({ value: store.id, label: store.name }))
);
</script>
