<template>
  <AppModal
    :show="show"
    :title="$t('inventory.labels.modal_title')"
    :subtitle="$t('inventory.labels.modal_subtitle', { count: lines.length })"
    :icon="Tag"
    max-width="3xl"
    @close="$emit('close')"
  >
    <form class="space-y-5 font-tajawal" data-testid="labels-modal" @submit.prevent="submit">
      <div class="grid grid-cols-1 lg:grid-cols-5 gap-5">
        <!-- Settings -->
        <div class="lg:col-span-3 space-y-4">
          <BaseSelect
            v-model="settings.preset"
            :label="$t('inventory.labels.size')"
            :options="presetOptions"
            :searchable="false"
          />
          <BaseSelect
            v-model="settings.symbology"
            :label="$t('inventory.labels.symbology')"
            :hint="$t('inventory.labels.symbology_hint')"
            :options="symbologyOptions"
            :searchable="false"
          />

          <fieldset class="space-y-1">
            <legend class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-1">
              {{ $t('inventory.labels.fields') }}
            </legend>
            <div class="grid grid-cols-2 gap-x-4">
              <BaseCheckbox v-model="settings.show_name" :label="$t('inventory.labels.show_name')" />
              <BaseCheckbox v-model="settings.show_price" :label="$t('inventory.labels.show_price')" />
              <BaseCheckbox v-model="settings.show_barcode" :label="$t('inventory.labels.show_barcode')" />
              <BaseCheckbox v-model="settings.show_code" :label="$t('inventory.labels.show_code')" />
            </div>
          </fieldset>
        </div>

        <!-- Live preview -->
        <div class="lg:col-span-2 space-y-2">
          <div class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $t('inventory.labels.preview') }}</div>
          <div
            class="flex items-center justify-center min-h-[140px] p-4 rounded-2xl bg-slate-100 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 overflow-hidden"
          >
            <div v-if="previewLabel" class="shadow-lg ring-1 ring-slate-300 origin-center" :style="previewScale">
              <ItemLabelCard :label="previewLabel" :settings="settings" />
            </div>
            <p v-else class="text-xs text-slate-500 dark:text-slate-400 text-center">
              {{ $t('inventory.labels.nothing_to_print') }}
            </p>
          </div>
        </div>
      </div>

      <!-- Copies per item -->
      <div class="space-y-2">
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $t('inventory.labels.copies') }}</div>
          <div class="flex items-end gap-2">
            <BaseNumberInput
              v-model="copiesForAll"
              :label="$t('inventory.labels.copies_for_all')"
              :min="MIN_COPIES"
              :max="MAX_COPIES"
              step="1"
              placeholder="1"
              wrapper-class="w-36"
            />
            <BaseButton
              type="button"
              variant="outline"
              size="md"
              :label="$t('inventory.labels.apply')"
              @click="applyToAll"
            />
          </div>
        </div>
        <ul
          class="max-h-64 overflow-y-auto divide-y divide-slate-200 dark:divide-slate-800 rounded-2xl border border-slate-200 dark:border-slate-800"
        >
          <li
            v-for="line in lines"
            :key="line.id"
            class="flex items-center justify-between gap-3 px-3 py-2"
            :data-testid="`labels-line-${line.id}`"
          >
            <div class="min-w-0">
              <div class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ line.name }}</div>
              <div class="text-[11px] font-mono text-slate-500 dark:text-slate-400" dir="ltr">
                {{ line.code || '—' }}
              </div>
              <div v-if="reasonFor(line)" class="text-[11px] font-bold text-amber-600 dark:text-amber-400">
                {{ $t(`inventory.labels.skip_${reasonFor(line)}`) }}
              </div>
            </div>
            <BaseNumberInput
              v-model="line.copies"
              :min="MIN_COPIES"
              :max="MAX_COPIES"
              step="1"
              placeholder="1"
              wrapper-class="w-28 shrink-0"
              :aria-label="$t('inventory.labels.copies_for', { name: line.name })"
              data-testid="labels-copies"
            />
          </li>
        </ul>
      </div>

      <!-- Summary & warnings -->
      <div
        v-if="summary.skipped.length"
        class="p-3 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900 text-xs text-amber-800 dark:text-amber-300"
        role="status"
        data-testid="labels-skipped"
      >
        <AlertTriangle class="inline w-4 h-4 me-1 align-text-bottom" />
        {{ $t('inventory.labels.skipped_summary', { count: summary.skipped.length }) }}
      </div>
      <div v-if="summary.truncated" class="text-xs font-bold text-rose-600 dark:text-rose-400" role="alert">
        {{ $t('inventory.labels.truncated', { max: MAX_LABELS }) }}
      </div>

      <div
        class="flex flex-wrap items-center justify-between gap-2 pt-4 border-t border-slate-200 dark:border-slate-800"
      >
        <span class="text-sm font-bold text-slate-700 dark:text-slate-300" data-testid="labels-total">
          {{ $t('inventory.labels.total_labels', { count: summary.labels.length }) }}
        </span>
        <div class="flex items-center gap-2">
          <BaseButton type="button" variant="ghost" size="md" :label="$t('common.cancel')" @click="$emit('close')" />
          <BaseButton
            type="submit"
            variant="gradient"
            size="md"
            :icon="Printer"
            :disabled="summary.labels.length === 0"
            :label="$t('inventory.labels.continue')"
            data-testid="labels-continue"
          />
        </div>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { AlertTriangle, Printer, Tag } from 'lucide-vue-next';
import AppModal from '../../Common/AppModal.vue';
import BaseButton from '../../Common/BaseButton.vue';
import BaseSelect from '../../Form/BaseSelect.vue';
import BaseCheckbox from '../../Form/BaseCheckbox.vue';
import BaseNumberInput from '../../Form/BaseNumberInput.vue';
import ItemLabelCard from './ItemLabelCard.vue';
import { trans } from '../../../helpers/trans';
import { SYMBOLOGIES } from '../../../helpers/barcode';
import {
  LABEL_PRESET_KEYS,
  LABEL_PRESETS,
  MAX_COPIES,
  MAX_LABELS,
  MIN_COPIES,
  buildLabels,
  clampCopies,
  labelLineFromItem,
  lineSkipReason,
  loadLabelSettings,
  saveLabelSettings,
} from '../../../helpers/labelPrint';

const props = defineProps({
  show: { type: Boolean, default: false },
  items: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'print']);

const settings = reactive(loadLabelSettings());
const lines = ref([]);
const copiesForAll = ref(1);

watch(
  () => props.show,
  (open) => {
    if (!open) return;
    Object.assign(settings, loadLabelSettings());
    lines.value = props.items.map((item) => labelLineFromItem(item, 1));
    copiesForAll.value = 1;
  },
  { immediate: true }
);

const presetOptions = computed(() =>
  LABEL_PRESET_KEYS.map((key) => ({ value: key, label: trans(`inventory.labels.presets.${key}`) }))
);
const symbologyOptions = computed(() =>
  SYMBOLOGIES.map((key) => ({ value: key, label: trans(`inventory.labels.symbologies.${key}`) }))
);

const reasonFor = (line) => (settings.show_barcode ? lineSkipReason(line, settings.symbology) : null);

const summary = computed(() => buildLabels(lines.value, settings));
const previewLabel = computed(() => summary.value.labels[0] || null);

// Shrinks big A4 labels so the preview box keeps one size.
const previewScale = computed(() => {
  const preset = LABEL_PRESETS[settings.preset];
  const factor = Math.min(1.6, 45 / (preset?.width || 45));
  return { transform: `scale(${factor.toFixed(2)})` };
});

const applyToAll = () => {
  const copies = clampCopies(copiesForAll.value);
  copiesForAll.value = copies;
  lines.value.forEach((line) => {
    line.copies = copies;
  });
};

const submit = () => {
  if (summary.value.labels.length === 0) return;
  saveLabelSettings(settings);
  emit('print', {
    settings: { ...settings },
    lines: lines.value.map((line) => ({ ...line, copies: clampCopies(line.copies) })),
  });
};
</script>
