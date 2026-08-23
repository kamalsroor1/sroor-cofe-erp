<template>
    <div class="space-y-3.5">
        <div
            v-for="item in items"
            :key="item[keyField]"
            class="space-y-1.5"
        >
            <!-- Row: Label + Amount + Percentage -->
            <div class="flex items-center justify-between text-xs font-bold">
                <div class="flex items-center gap-2">
                    <span v-if="iconFn" class="text-sm">{{ iconFn(item[keyField]) }}</span>
                    <span class="text-slate-800 dark:text-slate-200">{{ item[labelField] }}</span>
                </div>
                <div class="flex items-center gap-2 font-mono">
                    <span class="text-slate-900 dark:text-white font-black">
                        {{ formatFn(item[amountField]) }}
                        <span class="text-[10px] font-sans text-slate-400"> {{ currency }}</span>
                    </span>
                    <span class="text-[10px] text-slate-400 font-bold">({{ item[percentageField] }}%)</span>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="w-full h-2.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                <div
                    class="h-full rounded-full transition-all duration-500"
                    :class="colorFn ? colorFn(item[keyField]) : 'bg-emerald-500'"
                    :style="{ width: `${Math.max(parseFloat(item[percentageField]), 2)}%` }"
                />
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="!items.length" class="py-8 text-center text-xs text-slate-400 font-bold">
            {{ emptyMessage }}
        </div>
    </div>
</template>

<script setup>
defineProps({
    items: { type: Array, required: true },

    // Field mappings
    keyField: { type: String, default: 'key' },
    labelField: { type: String, default: 'label' },
    amountField: { type: String, default: 'amount' },
    percentageField: { type: String, default: 'percentage' },

    // Optional resolver functions passed from parent
    iconFn: { type: Function, default: null },   // (key) => emoji string
    colorFn: { type: Function, default: null },  // (key) => tailwind class string
    formatFn: { type: Function, default: (v) => v }, // value formatter

    currency: { type: String, default: 'ج.م' },
    emptyMessage: { type: String, default: 'لا توجد بيانات مسجلة' },
});
</script>