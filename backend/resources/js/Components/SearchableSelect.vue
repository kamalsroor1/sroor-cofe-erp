<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, Object, null], default: null },
    options: { type: Array, default: () => [] },
    labelKey: { type: String, default: 'name' },
    valueKey: { type: String, default: 'id' },
    placeholder: { type: String, default: '' },
    searchPlaceholder: { type: String, default: '' },
    clearable: { type: Boolean, default: true },
    searchable: { type: Boolean, default: true },
    remote: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    icon: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'change', 'search', 'clear']);

const isOpen = ref(false);
const searchQuery = ref('');
const selectRef = ref(null);
const searchInputRef = ref(null);

// Get Option Label
const getOptionLabel = (opt) => {
    if (opt === null || opt === undefined) return '';
    if (typeof opt === 'object') {
        return opt[props.labelKey] ?? opt.label ?? opt.name ?? opt.title ?? String(opt);
    }
    return String(opt);
};

// Get Option Value
const getOptionValue = (opt) => {
    if (opt === null || opt === undefined) return null;
    if (typeof opt === 'object') {
        return opt[props.valueKey] ?? opt.id ?? opt.value ?? opt;
    }
    return opt;
};

// Selected Option Object
const selectedOption = computed(() => {
    if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') {
        return null;
    }
    return props.options.find(opt => getOptionValue(opt) == props.modelValue) || null;
});

// Selected Label Display
const displayLabel = computed(() => {
    if (selectedOption.value) {
        return getOptionLabel(selectedOption.value);
    }
    if (props.modelValue && !props.remote) {
        return String(props.modelValue);
    }
    return '';
});

// Filtered Options (when not remote)
const filteredOptions = computed(() => {
    if (props.remote || !props.searchable || !searchQuery.value) {
        return props.options;
    }
    const q = searchQuery.value.trim().toLowerCase();
    return props.options.filter(opt => {
        const label = getOptionLabel(opt).toLowerCase();
        return label.includes(q);
    });
});

// Toggle Dropdown
const toggleDropdown = () => {
    if (props.disabled) return;
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        searchQuery.value = '';
        setTimeout(() => {
            searchInputRef.value?.focus();
        }, 50);
    }
};

// Select an option
const selectOption = (opt) => {
    const val = getOptionValue(opt);
    emit('update:modelValue', val);
    emit('change', opt);
    isOpen.value = false;
};

// Clear selection
const clearSelection = (e) => {
    e?.stopPropagation();
    emit('update:modelValue', null);
    emit('clear');
    emit('change', null);
};

// Watch search query for remote mode
watch(searchQuery, (q) => {
    if (props.remote) {
        emit('search', q);
    }
});

// Click Outside listener
const handleClickOutside = (e) => {
    if (selectRef.value && !selectRef.value.contains(e.target)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div ref="selectRef" class="relative select-none text-xs sm:text-sm font-tajawal w-full" :class="{ 'opacity-60 pointer-events-none': disabled }">
        <!-- Trigger Button -->
        <div
            @click="toggleDropdown"
            class="w-full h-11 px-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-900 dark:text-white flex items-center justify-between gap-2 cursor-pointer transition focus:ring-2 focus:ring-theme-primary/50 shadow-inner"
            :class="[
                isOpen ? 'border-theme-primary ring-2 ring-theme-primary/20' : '',
                selectedOption ? 'font-bold' : 'text-slate-500 dark:text-slate-400'
            ]"
        >
            <div class="flex items-center gap-2 min-w-0 truncate">
                <span v-if="icon" class="text-sm shrink-0">{{ icon }}</span>
                <slot name="selected" :option="selectedOption">
                    <span v-if="displayLabel" class="truncate text-slate-900 dark:text-white font-bold">{{ displayLabel }}</span>
                    <span v-else class="text-slate-400 dark:text-slate-500 truncate">{{ placeholder || $t('common.select_placeholder') }}</span>
                </slot>
            </div>

            <div class="flex items-center gap-1.5 shrink-0">
                <!-- Clear Button -->
                <button
                    v-if="clearable && modelValue !== null && modelValue !== undefined && modelValue !== '' && !disabled"
                    @click="clearSelection"
                    type="button"
                    class="w-6 h-6 rounded-full hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-400 hover:text-rose-500 flex items-center justify-center text-xs transition active:scale-90"
                    :title="$t('common.cancel')"
                >
                    ✕
                </button>

                <!-- Arrow Indicator -->
                <span class="text-xs text-slate-400 dark:text-slate-500 transition-transform duration-200" :class="isOpen ? 'rotate-180 text-theme-primary' : ''">
                    ▼
                </span>
            </div>
        </div>

        <!-- Dropdown Menu -->
        <div
            v-if="isOpen"
            class="absolute z-50 right-0 left-0 mt-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150"
        >
            <!-- Search Bar in Dropdown -->
            <div v-if="searchable" class="p-2 border-b border-slate-200 dark:border-slate-800/80 bg-slate-50 dark:bg-slate-800/50">
                <div class="relative">
                    <input
                        ref="searchInputRef"
                        v-model="searchQuery"
                        type="text"
                        :placeholder="searchPlaceholder || $t('common.search_in_list')"
                        class="w-full pr-8 pl-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700/80 rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:border-theme-primary transition shadow-inner font-tajawal"
                        @keydown.stop
                    >
                    <span class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 text-xs pointer-events-none">
                        🔍
                    </span>
                </div>
            </div>

            <!-- Options List (Scrollable) -->
            <div class="max-h-60 overflow-y-auto p-1.5 space-y-1">
                <div
                    v-for="opt in filteredOptions"
                    :key="getOptionValue(opt)"
                    @click="selectOption(opt)"
                    class="px-3 py-2.5 rounded-xl flex items-center justify-between gap-2 cursor-pointer transition text-xs sm:text-sm active:scale-98 min-h-[40px]"
                    :class="[
                        getOptionValue(opt) == modelValue
                            ? 'bg-theme-primary/15 text-theme-primary text-theme-primary font-black border border-theme-border'
                            : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'
                    ]"
                >
                    <slot name="option" :option="opt">
                        <div class="flex items-center gap-2 truncate">
                            <span v-if="opt.type === 'van'" class="text-sm">🚐</span>
                            <span v-else-if="opt.type === 'main'" class="text-sm">🏬</span>
                            <span class="truncate">{{ getOptionLabel(opt) }}</span>
                        </div>
                    </slot>

                    <span v-if="getOptionValue(opt) == modelValue" class="text-theme-primary font-bold text-xs">
                        ✓
                    </span>
                </div>

                <!-- Empty State -->
                <div v-if="filteredOptions.length === 0" class="py-6 text-center text-slate-400 text-xs font-bold font-tajawal">
                    {{ $t('common.no_data') }}
                </div>
            </div>
        </div>
    </div>
</template>
