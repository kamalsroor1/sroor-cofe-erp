<template>
  <div class="w-full flex flex-col gap-1.5" :class="wrapperClass" ref="dropdownRef" @focusout="onFocusOut">
    <!-- Label -->
    <label
      v-if="label"
      :for="selectId"
      class="block text-xs font-black text-slate-700 dark:text-slate-200 cursor-pointer select-none transition-colors"
      :class="{ 'text-rose-500 dark:text-rose-400': hasError }"
    >
      {{ label }}
      <span v-if="required" class="text-rose-500 font-black ms-0.5">*</span>
    </label>

    <!-- Custom Searchable Dropdown or Native Select -->
    <div class="relative w-full">
      <!-- Interactive Trigger Button -->
      <button
        :id="selectId"
        ref="triggerRef"
        type="button"
        @click="toggleDropdown"
        @keydown="onTriggerKeydown"
        :disabled="disabled"
        :aria-expanded="isOpen"
        aria-haspopup="listbox"
        :aria-invalid="hasError ? 'true' : 'false'"
        class="w-full min-h-[44px] px-3.5 py-2.5 text-base sm:text-sm font-bold rounded-xl border bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 transition-all duration-200 flex items-center justify-between gap-2 text-start outline-hidden cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-slate-100 dark:disabled:bg-slate-900/50"
        :class="[
          hasError
            ? 'border-rose-500 dark:border-rose-500/80 focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20 text-rose-900 dark:text-rose-100'
            : 'border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20',
          selectClass,
        ]"
      >
        <span class="truncate" :class="{ 'text-slate-400 dark:text-slate-500 font-medium': !selectedLabel }">
          {{ selectedLabel || placeholder || t('common.select_placeholder') }}
        </span>

        <div class="flex items-center gap-1.5 shrink-0 text-slate-400 dark:text-slate-500">
          <Loader2 v-if="isLoading" class="w-4 h-4 animate-spin text-theme-primary" />
          <ChevronDown
            v-else
            class="w-4 h-4 transition-transform duration-200"
            :class="{ 'rotate-180 text-theme-primary': isOpen }"
          />
        </div>
      </button>

      <!-- Dropdown Menu Overlay -->
      <Transition name="modal-zoom">
        <div
          v-if="isOpen"
          class="absolute top-full mt-1.5 inset-x-0 z-50 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-64 font-tajawal animate-in fade-in zoom-in-95 duration-150"
        >
          <!-- Search Input inside Dropdown -->
          <div
            v-if="searchable"
            class="p-2 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 shrink-0"
          >
            <div class="relative flex items-center">
              <Search class="w-3.5 h-3.5 absolute start-3 text-slate-400" />
              <input
                ref="searchInputRef"
                v-model="searchQuery"
                type="text"
                :placeholder="searchPlaceholder || t('common.search_in_list')"
                :aria-label="searchPlaceholder || t('common.search_in_list')"
                autocomplete="off"
                @keydown="onSearchKeydown"
                class="w-full min-h-[44px] ps-9 pe-11 py-2 text-base sm:text-sm font-bold rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-400 outline-hidden focus:border-theme-primary"
                @input="handleSearchInput"
              />
              <button
                v-if="searchQuery"
                type="button"
                @click="clearSearch"
                :aria-label="t('common.clear_search')"
                class="absolute end-0 min-w-[44px] min-h-[44px] flex items-center justify-center text-slate-400 hover:text-slate-600 cursor-pointer"
              >
                <X class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Options List -->
          <div
            class="flex-1 overflow-y-auto p-1.5 space-y-1 custom-scrollbar"
            role="listbox"
            :aria-labelledby="selectId"
          >
            <!-- Loading Indicator -->
            <div
              v-if="isLoading"
              class="py-4 text-center text-xs font-bold text-slate-400 flex items-center justify-center gap-2"
            >
              <Loader2 class="w-4 h-4 animate-spin text-theme-primary" />
              <span>{{ t('common.loading') }}</span>
            </div>

            <!-- Load failed -->
            <div
              v-else-if="searchFailed"
              class="py-4 flex flex-col items-center gap-2 text-center text-xs font-bold text-rose-500 dark:text-rose-400"
            >
              <span>{{ t('common.search_failed') }}</span>
              <button
                type="button"
                class="min-h-[44px] px-4 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 cursor-pointer"
                @click="executeRemoteSearch(searchQuery)"
              >
                {{ t('common.retry') }}
              </button>
            </div>

            <!-- Empty State -->
            <div
              v-else-if="filteredOptions.length === 0"
              class="py-4 text-center text-xs font-bold text-slate-400 dark:text-slate-500"
            >
              {{ emptyText || t('common.no_results') }}
            </div>

            <!-- Options Items -->
            <button
              v-for="(opt, idx) in filteredOptions"
              :key="getOptionValue(opt)"
              type="button"
              role="option"
              :aria-selected="isSelected(opt)"
              :disabled="opt.disabled === true"
              @click="selectOption(opt)"
              @mouseenter="activeIndex = idx"
              :data-option-index="idx"
              class="w-full min-h-[44px] px-3 py-2 text-sm sm:text-xs font-bold rounded-xl flex items-center justify-between text-start transition cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
              :class="[
                isSelected(opt) ? 'bg-theme-light text-theme-primary font-black' : 'text-slate-700 dark:text-slate-300',
                idx === activeIndex && !isSelected(opt) ? 'bg-slate-100 dark:bg-slate-800' : '',
              ]"
            >
              <div class="flex items-center gap-2 truncate">
                <span v-if="opt.icon" class="shrink-0">{{ opt.icon }}</span>
                <span class="truncate">{{ getOptionLabel(opt) }}</span>
                <span v-if="opt.subtext" class="text-[10px] text-slate-400 font-mono">({{ opt.subtext }})</span>
              </div>
              <Check v-if="isSelected(opt)" class="w-3.5 h-3.5 text-theme-primary shrink-0 ms-2" />
            </button>
          </div>
        </div>
      </Transition>
    </div>

    <!-- Error Message -->
    <p
      v-if="hasError"
      :id="`${selectId}-error`"
      class="text-xs font-bold text-rose-500 dark:text-rose-400 flex items-center gap-1 mt-0.5 animate-in fade-in"
    >
      <AlertCircle class="w-3.5 h-3.5 shrink-0" />
      <span>{{ errorMessage }}</span>
    </p>

    <!-- Helper / Hint Text -->
    <p
      v-else-if="hint"
      :id="`${selectId}-hint`"
      class="text-[11px] font-medium text-slate-500 dark:text-slate-400 mt-0.5"
    >
      {{ hint }}
    </p>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { ChevronDown, Check, Search, X, Loader2, AlertCircle } from 'lucide-vue-next';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();

const SEARCH_DEBOUNCE_MS = 300;

defineOptions({
  inheritAttrs: false,
});

const props = defineProps({
  id: { type: String, default: null },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  options: { type: Array, default: () => [] },
  valueKey: { type: String, default: 'value' },
  labelKey: { type: String, default: 'label' },
  searchable: { type: Boolean, default: true },
  searchPlaceholder: { type: String, default: '' },
  // Remote mode: searchFn(query, { signal }) => Promise<option[] | { data: option[] }>
  searchFn: { type: Function, default: null },
  // Remote mode: the currently selected option, so its label shows even when it is not in the result page.
  selectedOption: { type: Object, default: null },
  emptyText: { type: String, default: '' },
  error: { type: [String, Array], default: null },
  hint: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  wrapperClass: { type: String, default: '' },
  selectClass: { type: String, default: '' },
});

const emit = defineEmits(['change', 'select']);

const model = defineModel({
  type: [String, Number, Object, Boolean],
  default: null,
});

const isOpen = ref(false);
const searchQuery = ref('');
const dynamicOptions = ref([]);
const isLoading = ref(false);
const dropdownRef = ref(null);
const triggerRef = ref(null);
const searchFailed = ref(false);
const searchInputRef = ref(null);
const activeIndex = ref(-1);
const selectedCache = ref(null);
let abortController = null;
let debounceTimeout = null;
let requestSeq = 0;

const autoId = 'sel-' + Math.random().toString(36).substring(2, 9);
const selectId = computed(() => props.id || autoId);

const getOptionValue = (opt) => {
  if (typeof opt === 'object' && opt !== null) {
    return opt[props.valueKey] !== undefined ? opt[props.valueKey] : opt.id;
  }
  return opt;
};

const getOptionLabel = (opt) => {
  if (typeof opt === 'object' && opt !== null) {
    return opt[props.labelKey] !== undefined ? opt[props.labelKey] : opt.name || opt.title || opt.id;
  }
  return opt;
};

const isSelected = (opt) => {
  const val = getOptionValue(opt);
  return model.value === val;
};

const selectedLabel = computed(() => {
  if (model.value === null || model.value === undefined || model.value === '') return '';
  if (props.searchFn) {
    const candidates = [...dynamicOptions.value, selectedCache.value, props.selectedOption];
    const found = candidates.find((o) => o && getOptionValue(o) === model.value);
    return found ? getOptionLabel(found) : '';
  }
  const found = props.options.find((o) => getOptionValue(o) === model.value);
  if (found) return getOptionLabel(found);
  return model.value;
});

const filteredOptions = computed(() => {
  if (props.searchFn) return dynamicOptions.value;
  if (!searchQuery.value) return props.options;
  const q = searchQuery.value.toLowerCase().trim();
  return props.options.filter((opt) => {
    const lbl = String(getOptionLabel(opt)).toLowerCase();
    const sub = opt.subtext ? String(opt.subtext).toLowerCase() : '';
    return lbl.includes(q) || sub.includes(q);
  });
});

const hasError = computed(() => {
  if (Array.isArray(props.error)) return props.error.length > 0;
  return !!props.error;
});

const errorMessage = computed(() => {
  if (Array.isArray(props.error)) return props.error[0];
  return props.error;
});

const toggleDropdown = () => {
  if (props.disabled) return;
  if (isOpen.value) closeDropdown();
  else openDropdown();
};

const openDropdown = () => {
  if (props.disabled) return;
  isOpen.value = true;
  if (props.searchFn && dynamicOptions.value.length === 0) {
    executeRemoteSearch('');
  }
  resetActive();
  nextTick(() => {
    searchInputRef.value?.focus();
  });
};

const closeDropdown = ({ refocus = false } = {}) => {
  isOpen.value = false;
  activeIndex.value = -1;
  // Remote results belong to the last query; drop them so the next open starts from the unfiltered list.
  if (props.searchFn) {
    dynamicOptions.value = [];
    searchFailed.value = false;
    searchQuery.value = '';
  }
  if (refocus) nextTick(() => triggerRef.value?.focus());
};

const onFocusOut = (e) => {
  if (isOpen.value && e.relatedTarget && !dropdownRef.value?.contains(e.relatedTarget)) closeDropdown();
};

const resetActive = () => {
  const selectedIdx = filteredOptions.value.findIndex(isSelected);
  activeIndex.value = selectedIdx >= 0 ? selectedIdx : filteredOptions.value.length > 0 ? 0 : -1;
};

const moveActive = (step) => {
  const total = filteredOptions.value.length;
  if (total === 0) return;
  activeIndex.value = (activeIndex.value + step + total) % total;
  nextTick(() => {
    dropdownRef.value
      ?.querySelector(`[data-option-index="${activeIndex.value}"]`)
      ?.scrollIntoView({ block: 'nearest' });
  });
};

const onSearchKeydown = (e) => {
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    moveActive(1);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    moveActive(-1);
  } else if (e.key === 'Enter') {
    e.preventDefault();
    const opt = filteredOptions.value[activeIndex.value];
    if (opt && opt.disabled !== true) selectOption(opt);
  } else if (e.key === 'Escape') {
    e.preventDefault();
    closeDropdown({ refocus: true });
  }
};

const onTriggerKeydown = (e) => {
  if (isOpen.value) {
    if (e.key === 'Escape') closeDropdown({ refocus: true });
    return;
  }
  if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
    e.preventDefault();
    openDropdown();
  }
};

const selectOption = (opt) => {
  const val = getOptionValue(opt);
  selectedCache.value = opt;
  model.value = val;
  emit('change', val);
  emit('select', opt);
  closeDropdown({ refocus: true });
  searchQuery.value = '';
};

const handleSearchInput = () => {
  if (!props.searchFn) return;
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(() => {
    executeRemoteSearch(searchQuery.value);
  }, SEARCH_DEBOUNCE_MS);
};

const executeRemoteSearch = async (query) => {
  if (!props.searchFn) return;
  if (abortController) abortController.abort();
  abortController = new AbortController();

  const seq = ++requestSeq;
  isLoading.value = true;
  searchFailed.value = false;
  try {
    const res = await props.searchFn(query, { signal: abortController.signal });
    if (seq !== requestSeq) return;
    dynamicOptions.value = Array.isArray(res) ? res : res?.data || [];
    resetActive();
  } catch {
    // The api interceptor already surfaced the error; a cancelled request is superseded by a newer one.
    if (seq === requestSeq) {
      dynamicOptions.value = [];
      searchFailed.value = true;
    }
  } finally {
    if (seq === requestSeq) isLoading.value = false;
  }
};

const clearSearch = () => {
  searchQuery.value = '';
  if (props.searchFn) executeRemoteSearch('');
};

const handleClickOutside = (e) => {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
    closeDropdown();
  }
};

watch(searchQuery, () => {
  if (!props.searchFn) resetActive();
});

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  if (abortController) abortController.abort();
  clearTimeout(debounceTimeout);
});
</script>
