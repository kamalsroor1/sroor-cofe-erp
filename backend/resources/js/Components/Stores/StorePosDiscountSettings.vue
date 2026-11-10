<script setup>
import { watch } from 'vue';
import { Save } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';
import BaseInput from '../Form/BaseInput.vue';
import {
  DEFAULT_MAX_DISCOUNT_PERCENT,
  useStorePosDiscountSettings,
} from '../../Composables/useStorePosDiscountSettings';

const props = defineProps({
  storeId: { type: [Number, String], required: true },
});

const { value, isLoading, isSaving, loadFailed, error, load, onInput, save } = useStorePosDiscountSettings();

watch(
  () => props.storeId,
  (id) => id && load(id),
  { immediate: true }
);
</script>

<template>
  <section
    class="space-y-3 p-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950"
    data-testid="store-pos-discount-settings"
  >
    <div>
      <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('inventory.max_discount_percent') }}</h3>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
        {{ $t('inventory.max_discount_percent_hint', { default: DEFAULT_MAX_DISCOUNT_PERCENT }) }}
      </p>
    </div>

    <div v-if="isLoading" class="h-11 rounded-xl bg-slate-200 dark:bg-slate-800 animate-pulse" aria-hidden="true"></div>

    <p v-else-if="loadFailed" role="alert" class="text-xs font-bold text-rose-600 dark:text-rose-400">
      {{ $t('inventory.max_discount_percent_load_failed') }}
      <button type="button" class="underline min-h-11 px-2 cursor-pointer" @click="load(storeId)">
        {{ $t('inventory.max_discount_percent_retry') }}
      </button>
    </p>

    <div v-else class="flex flex-col sm:flex-row sm:items-end gap-2">
      <BaseInput
        :model-value="value"
        :label="$t('inventory.max_discount_percent')"
        :placeholder="DEFAULT_MAX_DISCOUNT_PERCENT"
        :error="error || null"
        inputmode="decimal"
        dir="ltr"
        wrapper-class="flex-1"
        data-testid="store-max-discount-input"
        @update:model-value="onInput"
      />
      <BaseButton
        type="button"
        variant="secondary"
        size="md"
        :loading="isSaving"
        class="min-h-11"
        data-testid="store-max-discount-save"
        @click="save(storeId)"
      >
        <Save class="w-4 h-4" />
        <span>{{ $t('common.save') }}</span>
      </BaseButton>
    </div>
  </section>
</template>
