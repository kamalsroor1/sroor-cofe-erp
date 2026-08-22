<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import flatpickr from 'flatpickr';
import { Arabic } from 'flatpickr/dist/l10n/ar.js';
import 'flatpickr/dist/flatpickr.min.css';

const props = defineProps({
    modelValue: { type: [String, Array, Date, null], default: null },
    mode: { type: String, default: 'single' }, // 'single' | 'range'
    placeholder: { type: String, default: '' },
    enableTime: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
    disabled: { type: Boolean, default: false },
    icon: { type: String, default: '📅' },
});

const emit = defineEmits(['update:modelValue', 'change', 'clear']);

const inputRef = ref(null);
let fpInstance = null;

onMounted(() => {
    if (inputRef.value) {
        fpInstance = flatpickr(inputRef.value, {
            locale: Arabic,
            mode: props.mode,
            enableTime: props.enableTime,
            dateFormat: props.enableTime ? 'Y-m-d H:i' : 'Y-m-d',
            defaultDate: props.modelValue || undefined,
            disableMobile: true,
            onChange: (selectedDates, dateStr) => {
                emit('update:modelValue', dateStr);
                emit('change', dateStr, selectedDates);
            },
        });
    }
});

watch(() => props.modelValue, (newVal) => {
    if (fpInstance && newVal !== fpInstance.input.value) {
        fpInstance.setDate(newVal || '', false);
    }
});

onUnmounted(() => {
    if (fpInstance) {
        fpInstance.destroy();
    }
});

const clearDate = (e) => {
    e?.stopPropagation();
    if (fpInstance) {
        fpInstance.clear();
    }
    emit('update:modelValue', null);
    emit('clear');
    emit('change', null, []);
};
</script>

<template>
    <div class="relative w-full text-xs sm:text-sm font-tajawal select-none" :class="{ 'opacity-60 pointer-events-none': disabled }">
        <div class="relative flex items-center">
            <span v-if="icon" class="absolute right-3.5 text-sm text-slate-400 pointer-events-none z-10">
                {{ icon }}
            </span>

            <input
                ref="inputRef"
                type="text"
                :placeholder="placeholder || $t('common.select_date')"
                :disabled="disabled"
                readonly
                class="w-full h-11 pr-10 pl-9 rounded-2xl bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 font-mono text-xs sm:text-sm cursor-pointer focus:ring-2 focus:ring-theme-primary/50 focus:border-theme-primary focus:outline-none transition shadow-inner font-tajawal"
                :class="modelValue ? 'font-bold text-amber-600 dark:text-amber-400' : ''"
            >

            <!-- Clear Button -->
            <button
                v-if="clearable && modelValue && !disabled"
                @click="clearDate"
                type="button"
                class="absolute left-2.5 w-6 h-6 rounded-full hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-400 hover:text-rose-500 flex items-center justify-center text-xs transition z-10 active:scale-90"
                :title="$t('common.cancel')"
            >
                ✕
            </button>
        </div>
    </div>
</template>

<style>
/* Custom Dark Theme Flatpickr Styling */
.flatpickr-calendar {
    font-family: 'Cairo', 'Tajawal', sans-serif !important;
    border-radius: 1.5rem !important;
    direction: rtl !important;
    padding: 14px !important;
    width: 320px !important;
    background: #0f172a !important;
    border: 1px solid #334155 !important;
    color: #f8fafc !important;
    box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(255, 255, 255, 0.1) !important;
    z-index: 99999 !important;
}

/* Mobile Specific Floating Modal (Centered on Mobile Screen with Backdrop) */
@media (max-width: 767px) {
    .flatpickr-calendar {
        position: fixed !important;
        top: 50% !important;
        left: 50% !important;
        right: auto !important;
        bottom: auto !important;
        transform: translate(-50%, -50%) !important;
        width: min(340px, calc(100vw - 28px)) !important;
        box-shadow: 0 0 0 100vmax rgba(2, 6, 23, 0.8), 0 25px 50px -12px rgba(0, 0, 0, 0.9) !important;
        border-radius: 1.75rem !important;
        padding: 16px !important;
        z-index: 999999 !important;
    }
    .flatpickr-calendar::before,
    .flatpickr-calendar::after {
        display: none !important;
    }
    .flatpickr-day {
        height: 38px !important;
        line-height: 38px !important;
        font-size: 13.5px !important;
        border-radius: 0.75rem !important;
    }
}

.flatpickr-months {
    padding: 4px 0 8px 0 !important;
}
.flatpickr-months .flatpickr-month {
    height: 40px !important;
}
.flatpickr-month,
.flatpickr-weekdays,
span.flatpickr-weekday {
    background: #0f172a !important;
    color: #94a3b8 !important;
    fill: #f8fafc !important;
    font-weight: 800 !important;
    font-size: 12px !important;
}
.flatpickr-current-month {
    font-size: 110% !important;
    padding-top: 4px !important;
}
.flatpickr-current-month .cur-month {
    font-weight: 900 !important;
    margin: 0 4px !important;
    color: #f8fafc !important;
}
.flatpickr-current-month input.cur-year {
    color: #f8fafc !important;
    font-weight: 900 !important;
}
.flatpickr-monthDropdown-months {
    background: #1e293b !important;
    color: #f8fafc !important;
    border-radius: 0.75rem !important;
    padding: 3px 8px !important;
    font-weight: 800 !important;
    font-size: 12px !important;
}
.flatpickr-monthDropdown-months option {
    background-color: #0f172a !important;
    color: #f8fafc !important;
}
.flatpickr-prev-month, .flatpickr-next-month {
    padding: 8px !important;
    border-radius: 0.75rem !important;
    color: #f8fafc !important;
    fill: #f8fafc !important;
    background: #1e293b !important;
    width: 32px !important;
    height: 32px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: all 0.15s ease !important;
}
.flatpickr-prev-month:hover, .flatpickr-next-month:hover {
    background: #334155 !important;
    transform: scale(1.05) !important;
}
.flatpickr-prev-month:hover svg, .flatpickr-next-month:hover svg {
    fill: #f59e0b !important;
}
.flatpickr-day {
    color: #e2e8f0 !important;
    border-radius: 0.75rem !important;
    font-weight: 700 !important;
    height: 36px !important;
    line-height: 36px !important;
    margin: 2px 0 !important;
    transition: all 0.15s ease !important;
}
.flatpickr-day:hover,
.flatpickr-day:focus {
    background: #1e293b !important;
    border-color: #475569 !important;
    color: #ffffff !important;
}
.flatpickr-day.selected,
.flatpickr-day.startRange,
.flatpickr-day.endRange {
    background: #d97706 !important;
    border-color: #d97706 !important;
    color: #ffffff !important;
    font-weight: 900 !important;
    border-radius: 0.75rem !important;
    box-shadow: 0 4px 12px rgba(217, 119, 6, 0.4) !important;
}
.flatpickr-day.inRange {
    background: rgba(217, 119, 6, 0.2) !important;
    border-color: rgba(217, 119, 6, 0.3) !important;
    color: #fbbf24 !important;
}
.flatpickr-day.today {
    border-color: #f59e0b !important;
    font-weight: 900 !important;
}
.flatpickr-day.flatpickr-disabled,
.flatpickr-day.prevMonthDay,
.flatpickr-day.nextMonthDay {
    color: #475569 !important;
    opacity: 0.35 !important;
}
</style>
