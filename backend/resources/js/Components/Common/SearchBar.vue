<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    placeholder: {
        type: String,
        default: ''
    },
    debounce: {
        type: Number,
        default: 300
    },
    icon: {
        type: String,
        default: '🔍'
    },
    inputClass: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['update:modelValue', 'search', 'clear']);

const localValue = ref(props.modelValue);
let timer = null;

watch(() => props.modelValue, (newVal) => {
    localValue.value = newVal;
});

const onInput = (e) => {
    const val = e.target.value;
    localValue.value = val;
    emit('update:modelValue', val);

    clearTimeout(timer);
    timer = setTimeout(() => {
        emit('search', val);
    }, props.debounce);
};

const clear = () => {
    localValue.value = '';
    emit('update:modelValue', '');
    emit('search', '');
    emit('clear');
};
</script>

<template>
    <div class="relative flex items-center w-full font-tajawal">
        <input
            :value="localValue"
            type="text"
            :placeholder="placeholder"
            class="w-full pr-10 pl-10 py-2.5 bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-theme-primary focus:outline-none transition shadow-inner"
            :class="inputClass"
            @input="onInput"
        />

        <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 text-xs pointer-events-none">
            {{ icon }}
        </span>

        <button
            v-if="localValue"
            type="button"
            class="absolute inset-y-0 left-0 pl-3.5 pr-2 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-900 dark:text-slate-200 text-xs cursor-pointer transition"
            @click="clear"
        >
            ✕
        </button>
    </div>
</template>
