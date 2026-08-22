<template>
  <div class="w-full flex flex-col gap-1.5" :class="wrapperClass" ref="dropdownRef">
    <!-- Label -->
    <label
      v-if="label"
      :for="selectId"
      class="block text-xs font-black text-slate-700 dark:text-slate-200 cursor-pointer select-none transition-colors"
      :class="{ 'text-rose-500 dark:text-rose-400': hasError }"
    >
      {{ label }}
      <span v-if="required" class="text-rose-500 font-black mr-0.5">*</span>
    </label>

    <!-- Custom Searchable Dropdown or Native Select -->
    <div class="relative w-full">
      <!-- Interactive Trigger Button -->
      <button
        :id="selectId"
        type="button"
        @click="toggleDropdown"
        :disabled="disabled"
        :aria-expanded="isOpen"
        :aria-invalid="hasError ? 'true' : 'false'"
        class="w-full min-h-[44px] px-3.5 py-2.5 text-base sm:text-sm font-bold rounded-xl border bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 transition-all duration-200 flex items-center justify-between gap-2 text-right outline-hidden cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-slate-100 dark:disabled:bg-slate-900/50"
        :class="[
          hasError
            ? 'border-rose-500 dark:border-rose-500/80 focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20 text-rose-900 dark:text-rose-100'
            : 'border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20',
          selectClass
        ]"
      >
        <span class="truncate" :class="{ 'text-slate-400 dark:text-slate-500 font-medium': !selectedLabel }">
          {{ selectedLabel || placeholder || 'اختر من القائمة...' }}
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
          <div v-if="searchable" class="p-2 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 shrink-0">
            <div class="relative flex items-center">
              <Search class="w-3.5 h-3.5 absolute right-3 text-slate-400" />
              <input
                ref="searchInputRef"
                v-model="searchQuery"
                type="text"
                :placeholder="searchPlaceholder || 'بحث...'"
                class="w-full pr-8 pl-3 py-1.5 text-xs font-bold rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-400 outline-hidden focus:border-theme-primary"
                @input="handleSearchInput"
              />
              <button
                v-if="searchQuery"
                type="button"
                @click="clearSearch"
                class="absolute left-2 text-slate-400 hover:text-slate-600 p-0.5"
              >
                <X class="w-3 h-3" />
              </button>
            </div>
          </div>

          <!-- Options List -->
          <div class="flex-1 overflow-y-auto p-1.5 space-y-1 custom-scrollbar">
            <!-- Loading Indicator -->
            <div v-if="isLoading" class="py-4 text-center text-xs font-bold text-slate-400 flex items-center justify-center gap-2">
              <Loader2 class="w-4 h-4 animate-spin text-theme-primary" />
              <span>جاري التحميل...</span>
            </div>

            <!-- Empty State -->
            <div v-else-if="filteredOptions.length === 0" class="py-4 text-center text-xs font-bold text-slate-400 dark:text-slate-500">
              {{ emptyText || 'لا توجد نتائج مطابقة' }}
            </div>

            <!-- Options Items -->
            <button
              v-for="opt in filteredOptions"
              :key="getOptionValue(opt)"
              type="button"
              @click="selectOption(opt)"
              class="w-full px-3 py-2 text-xs font-bold rounded-xl flex items-center justify-between text-right transition cursor-pointer"
              :class="[
                isSelected(opt)
                  ? 'bg-theme-light text-theme-primary font-black'
                  : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
              ]"
            >
              <div class="flex items-center gap-2 truncate">
                <span v-if="opt.icon" class="shrink-0">{{ opt.icon }}</span>
                <span class="truncate">{{ getOptionLabel(opt) }}</span>
                <span v-if="opt.subtext" class="text-[10px] text-slate-400 font-mono">({{ opt.subtext }})</span>
              </div>
              <Check v-if="isSelected(opt)" class="w-3.5 h-3.5 text-theme-primary shrink-0 mr-2" />
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

defineOptions({
  inheritAttrs: false
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
  searchFn: { type: Function, default: null }, // Async dynamic remote search function
  emptyText: { type: String, default: '' },
  error: { type: [String, Array], default: null },
  hint: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  wrapperClass: { type: String, default: '' },
  selectClass: { type: String, default: '' }
});

const emit = defineEmits(['change', 'select']);

const model = defineModel({
  type: [String, Number, Object, Boolean],
  default: null
});

const isOpen = ref(false);
const searchQuery = ref('');
const dynamicOptions = ref([]);
const isLoading = ref(false);
const dropdownRef = ref(null);
const searchInputRef = ref(null);
let abortController = null;
let debounceTimeout = null;

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
    return opt[props.labelKey] !== undefined ? opt[props.labelKey] : (opt.name || opt.title || opt.id);
  }
  return opt;
};

const isSelected = (opt) => {
  const val = getOptionValue(opt);
  return model.value === val;
};

const selectedLabel = computed(() => {
  if (model.value === null || model.value === undefined || model.value === '') return '';
  const allOpts = props.searchFn ? dynamicOptions.value : props.options;
  const found = allOpts.find(o => getOptionValue(o) === model.value);
  if (found) return getOptionLabel(found);
  return model.value;
});

const filteredOptions = computed(() => {
  if (props.searchFn) return dynamicOptions.value;
  if (!searchQuery.value) return props.options;
  const q = searchQuery.value.toLowerCase().trim();
  return props.options.filter(opt => {
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
  isOpen.value = !isOpen.value;
  if (isOpen.value) {
    if (props.searchFn && dynamicOptions.value.length === 0) {
      executeRemoteSearch('');
    }
    nextTick(() => {
      searchInputRef.value?.focus();
    });
  }
};

const selectOption = (opt) => {
  const val = getOptionValue(opt);
  model.value = val;
  emit('change', val);
  emit('select', opt);
  isOpen.value = false;
  searchQuery.value = '';
};

const handleSearchInput = () => {
  if (!props.searchFn) return;
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(() => {
    executeRemoteSearch(searchQuery.value);
  }, 350);
};

const executeRemoteSearch = async (query) => {
  if (!props.searchFn) return;
  if (abortController) abortController.abort();
  abortController = new AbortController();

  isLoading.value = true;
  try {
    const res = await props.searchFn(query, { signal: abortController.signal });
    dynamicOptions.value = Array.isArray(res) ? res : (res?.data || []);
  } catch (err) {
    if (err.name !== 'AbortError') {
      console.error('Remote search error:', err);
    }
  } finally {
    isLoading.value = false;
  }
};

const clearSearch = () => {
  searchQuery.value = '';
  if (props.searchFn) executeRemoteSearch('');
};

const handleClickOutside = (e) => {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
    isOpen.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  if (abortController) abortController.abort();
  clearTimeout(debounceTimeout);
});
</script>