<script setup>
import { computed, watch } from 'vue';
import { Save } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';
import BaseCheckbox from '../Form/BaseCheckbox.vue';
import BaseRadioGroup from '../Form/BaseRadioGroup.vue';
import { CARDS_PER_ROW_OPTIONS, useStorePosCatalogSettings } from '../../Composables/useStorePosCatalogSettings';
import { useTrans } from '../../Composables/useTrans';

const props = defineProps({
  storeId: { type: [Number, String], required: true },
});

const { t } = useTrans();
const { cardsPerRow, showImages, isLoading, isSaving, loadFailed, load, save } = useStorePosCatalogSettings();

const options = computed(() =>
  CARDS_PER_ROW_OPTIONS.map((value) => ({ value, label: t(`pos.cards_per_row_${value}`) }))
);

watch(
  () => props.storeId,
  (id) => id && load(id),
  { immediate: true }
);
</script>

<template>
  <section
    class="space-y-3 p-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950"
    data-testid="store-pos-catalog-settings"
  >
    <div>
      <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('pos.catalog_display_title') }}</h3>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $t('pos.catalog_display_hint') }}</p>
    </div>

    <div v-if="isLoading" class="space-y-2" aria-hidden="true">
      <div class="h-12 rounded-2xl bg-slate-200 dark:bg-slate-800 animate-pulse"></div>
      <div class="h-11 rounded-xl bg-slate-200 dark:bg-slate-800 animate-pulse"></div>
    </div>

    <p v-else-if="loadFailed" role="alert" class="text-xs font-bold text-rose-600 dark:text-rose-400">
      {{ $t('pos.catalog_display_load_failed') }}
      <button type="button" class="underline min-h-11 px-2 cursor-pointer" @click="load(storeId)">
        {{ $t('common.retry') }}
      </button>
    </p>

    <template v-else>
      <BaseRadioGroup
        v-model="cardsPerRow"
        :label="$t('pos.cards_per_row_label')"
        :options="options"
        :columns="3"
        name="pos-cards-per-row"
        data-testid="store-cards-per-row"
      />

      <div>
        <BaseCheckbox v-model="showImages" :label="$t('pos.show_item_images_label')" data-testid="store-show-images" />
        <p class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('pos.show_item_images_hint') }}</p>
      </div>

      <div class="flex justify-end">
        <BaseButton
          type="button"
          variant="secondary"
          size="md"
          :loading="isSaving"
          class="min-h-11"
          data-testid="store-catalog-save"
          @click="save(storeId)"
        >
          <Save class="w-4 h-4" />
          <span>{{ $t('common.save') }}</span>
        </BaseButton>
      </div>
    </template>
  </section>
</template>
