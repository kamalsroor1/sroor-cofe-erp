<template>
    <div class="pt-4 pb-2">
        <!-- Bars Container -->
        <div
            class="flex items-end justify-between border-b border-slate-200 dark:border-slate-800 pb-2"
            :style="{ height: `${height}px`, gap: barGap }"
        >
            <div
                v-for="item in items"
                :key="item[keyField]"
                class="flex-1 flex flex-col items-center h-full justify-end group cursor-pointer relative"
            >
                <!-- Tooltip on Hover -->
                <div
                    class="opacity-0 group-hover:opacity-100 transition-opacity absolute z-20 bg-slate-900 text-white text-[10px] font-mono py-1 px-2.5 rounded-xl shadow-xl pointer-events-none whitespace-nowrap border border-slate-700"
                    :style="{ top: `-${tooltipOffset}px` }"
                >
                    <div class="font-bold">{{ item[tooltipPrimaryField] }}</div>
                    <div v-if="tooltipSecondaryField" class="text-slate-400 font-sans">
                        {{ item[tooltipSecondaryField] }}
                    </div>
                </div>

                <!-- Bar -->
                <div
                    class="rounded-xl relative overflow-hidden flex flex-col justify-end transition-all duration-300 h-full"
                    :class="barMaxWidthClass"
                    style="background: var(--bar-track, rgba(241,245,249,1))"
                    :style="{ '--bar-track': trackColor }"
                >
                    <div
                        class="w-full rounded-xl transition-all duration-500 relative group-hover:brightness-110"
                        :style="{
                            height: `${computeHeight(item[valueField])}%`,
                            backgroundColor: highlightFn(item)
                                ? highlightColor
                                : defaultColor
                        }"
                    >
                        <!-- Pulse effect for highlighted bar -->
                        <div
                            v-if="highlightFn(item)"
                            class="absolute inset-0 bg-white/20 animate-pulse rounded-xl"
                        />
                    </div>
                </div>

                <!-- Label below bar -->
                <div
                    class="text-[10px] font-bold text-center truncate w-full text-slate-500 dark:text-slate-400 group-hover:text-theme-primary transition-colors font-tajawal"
                >
                    {{ item[labelField] }}
                </div>
            </div>
        </div>

        <!-- Optional Footer Slot -->
        <slot name="footer" />
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    // Array of data items
    items: { type: Array, required: true },

    // Field mappings
    keyField: { type: String, default: 'date' },
    valueField: { type: String, default: 'sales' },
    labelField: { type: String, default: 'label' },
    tooltipPrimaryField: { type: String, default: 'sales_formatted' },
    tooltipSecondaryField: { type: String, default: '' },

    // Highlight logic: function(item) => boolean
    highlightFn: { type: Function, default: () => false },

    // Colors
    defaultColor: { type: String, default: '#0ea5e9' },
    highlightColor: { type: String, default: 'var(--color-primary, #10b981)' },
    trackColor: { type: String, default: 'rgba(241,245,249,0.8)' },

    // Dimensions
    height: { type: Number, default: 192 },   // h-48 = 192px
    barGap: { type: String, default: '0.75rem' },
    barMaxWidthClass: { type: String, default: 'w-full max-w-[42px]' },

    // Tooltip offset from top (px)
    tooltipOffset: { type: Number, default: 48 },
});

const maxValue = computed(() => {
    const vals = props.items.map(i => parseFloat(i[props.valueField]) || 0);
    return Math.max(...vals, 1);
});

const computeHeight = (val) => {
    const v = parseFloat(val) || 0;
    return Math.min(100, Math.max(8, Math.round((v / maxValue.value) * 100)));
};
</script>