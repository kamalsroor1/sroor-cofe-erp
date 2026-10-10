<template>
  <div
    class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 shadow-xs dark:shadow-xl space-y-6 font-tajawal transition-colors"
  >
    <!-- Section Header -->
    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
      <div class="flex items-center gap-3">
        <div
          class="w-10 h-10 rounded-2xl bg-theme-light border border-theme-border text-theme-primary flex items-center justify-center shrink-0"
        >
          <Package class="w-5 h-5" />
        </div>
        <div>
          <h2 class="text-base font-black text-slate-900 dark:text-white">
            {{ $t('settings.inventory_units_title') }}
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            {{ $t('settings.inventory_units_sub') }}
          </p>
        </div>
      </div>
    </div>

    <!-- Add Custom Unit Input -->
    <div v-if="canManage" class="space-y-2">
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
        <div class="relative flex-1">
          <input
            v-model="newUnitInput"
            type="text"
            :placeholder="$t('settings.unit_name_placeholder')"
            @keydown.enter.prevent="handleAddCustomUnit"
            class="w-full min-h-[44px] px-3.5 py-2.5 text-base sm:text-sm font-bold rounded-xl border bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 border-slate-300 dark:border-slate-700 focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 outline-hidden transition-all duration-200 placeholder-slate-400 dark:placeholder-slate-500"
          />
        </div>

        <BaseButton
          type="button"
          variant="primary"
          size="md"
          @click="handleAddCustomUnit"
          :disabled="!newUnitInput.trim()"
          class="min-h-[44px] px-5 font-bold flex items-center justify-center gap-1.5 shrink-0"
        >
          <Plus class="w-4 h-4" />
          <span>{{ $t('settings.add_custom_unit') }}</span>
        </BaseButton>
      </div>

      <p v-if="localError" class="text-xs font-bold text-rose-500 dark:text-rose-400 flex items-center gap-1">
        <AlertCircle class="w-3.5 h-3.5 shrink-0" />
        <span>{{ localError }}</span>
      </p>
    </div>

    <!-- Active Units List (Reorderable) -->
    <div id="setting-inventory_units" class="space-y-3 pt-2">
      <div class="flex items-center justify-between">
        <h3 class="text-xs font-black text-slate-700 dark:text-slate-200">
          {{ $t('settings.active_units_label') }} ({{ unitsList.length }})
        </h3>
      </div>

      <!-- Empty State -->
      <div
        v-if="unitsList.length === 0"
        class="p-6 rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 text-center text-xs font-bold text-slate-400 dark:text-slate-500"
      >
        {{ $t('settings.no_units_yet') }}
      </div>

      <!-- Units Grid / List -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
        <div
          v-for="(unit, idx) in unitsList"
          :key="unit"
          class="flex items-center justify-between p-2.5 sm:p-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-800/60 transition-all hover:border-slate-300 dark:hover:border-slate-700"
        >
          <!-- Unit Label & Index -->
          <div class="flex items-center gap-2 min-w-0">
            <span
              class="w-6 h-6 rounded-lg bg-slate-200 dark:bg-slate-700/80 text-slate-600 dark:text-slate-300 text-[11px] font-black flex items-center justify-center shrink-0 font-mono"
            >
              {{ idx + 1 }}
            </span>
            <span class="text-sm font-black text-slate-900 dark:text-white truncate">
              {{ unit }}
            </span>
          </div>

          <!-- Unit Actions: Move Up, Move Down, Remove -->
          <div v-if="canManage" class="flex items-center gap-1 shrink-0">
            <button
              type="button"
              @click="moveUp(idx)"
              :disabled="idx === 0"
              :title="$t('settings.move_up')"
              class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-200 dark:hover:bg-slate-700 transition disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
            >
              <ArrowUp class="w-3.5 h-3.5" />
            </button>

            <button
              type="button"
              @click="moveDown(idx)"
              :disabled="idx === unitsList.length - 1"
              :title="$t('settings.move_down')"
              class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-200 dark:hover:bg-slate-700 transition disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
            >
              <ArrowDown class="w-3.5 h-3.5" />
            </button>

            <button
              type="button"
              @click="remove(idx)"
              :title="$t('settings.remove_unit')"
              class="w-8 h-8 rounded-lg flex items-center justify-center text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer"
            >
              <Trash2 class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 422 Backend Error Display (e.g. Unit in use by items) -->
    <div
      v-if="unitBackendError"
      class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs font-bold flex items-center gap-2.5 animate-in fade-in"
    >
      <AlertCircle class="w-5 h-5 text-rose-500 shrink-0" />
      <span>{{ unitBackendError }}</span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Package, Plus, Trash2, ArrowUp, ArrowDown, AlertCircle } from 'lucide-vue-next';
import { useTrans } from '../../../Composables/useTrans';
import BaseButton from '../../Common/BaseButton.vue';

const { t } = useTrans();

const props = defineProps({
  form: { type: Object, default: () => ({ inventory_units: '' }) },
  errors: { type: Object, default: () => ({}) },
  canManage: { type: Boolean, default: true },
});

const emit = defineEmits(['update:field']);

const newUnitInput = ref('');
const localError = ref('');

const unitsList = computed(() => {
  const raw = props.form?.inventory_units || '';
  return raw
    .split(',')
    .map((u) => u.trim())
    .filter(Boolean);
});

const unitBackendError = computed(() => {
  const err = props.errors?.inventory_units || props.errors?.unit;
  if (!err) return null;
  return Array.isArray(err) ? err[0] : err;
});

const updateUnits = (newArr) => {
  localError.value = '';
  emit('update:field', 'inventory_units', newArr.join(','));
};

const handleAddCustomUnit = () => {
  const val = newUnitInput.value.trim();
  if (!val) {
    localError.value = t('settings.unit_required');
    return;
  }
  if (unitsList.value.includes(val)) {
    localError.value = t('settings.unit_already_exists');
    return;
  }
  updateUnits([...unitsList.value, val]);
  newUnitInput.value = '';
};

const remove = (idx) => {
  const current = [...unitsList.value];
  current.splice(idx, 1);
  updateUnits(current);
};

const moveUp = (idx) => {
  if (idx <= 0) return;
  const current = [...unitsList.value];
  const temp = current[idx];
  current[idx] = current[idx - 1];
  current[idx - 1] = temp;
  updateUnits(current);
};

const moveDown = (idx) => {
  if (idx >= unitsList.value.length - 1) return;
  const current = [...unitsList.value];
  const temp = current[idx];
  current[idx] = current[idx + 1];
  current[idx + 1] = temp;
  updateUnits(current);
};
</script>
