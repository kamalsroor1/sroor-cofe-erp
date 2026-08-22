<script setup>
defineProps({
    target: {
        type: String,
        default: 'paid_amount'
    }
});

defineEmits(['press', 'set-target', 'quick-exact']);
</script>

<template>
    <div class="p-3 bg-slate-50 dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 shrink-0 space-y-2 font-tajawal">
        <div class="flex items-center justify-between text-xs">
            <span class="text-slate-600 dark:text-slate-400 font-bold">{{ $t('pos.numpad_title') }}</span>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="px-2 py-0.5 rounded text-[10px] font-bold cursor-pointer"
                    :class="target === 'paid_amount' ? 'bg-theme-primary text-white shadow-theme-primary font-black' : 'text-slate-600 dark:text-slate-400'"
                    @click="$emit('set-target', 'paid_amount')"
                >
                    {{ $t('invoices.paid') }}
                </button>
                <button
                    type="button"
                    class="px-2 py-0.5 rounded text-[10px] font-bold cursor-pointer"
                    :class="target === 'discount_value' ? 'bg-theme-primary text-white shadow-theme-primary font-black' : 'text-slate-600 dark:text-slate-400'"
                    @click="$emit('set-target', 'discount_value')"
                >
                    {{ $t('invoices.discount') }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-4 gap-1.5 font-mono font-bold text-xs">
            <button
                v-for="num in ['7','8','9','C']"
                :key="num"
                type="button"
                class="h-11 sm:h-9 rounded-xl bg-white hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-900 dark:text-white border border-slate-200 dark:border-transparent flex items-center justify-center cursor-pointer shadow-xs active:scale-95 text-sm font-black"
                @click="$emit('press', num)"
            >
                {{ num }}
            </button>
            <button
                v-for="num in ['4','5','6','backspace']"
                :key="num"
                type="button"
                class="h-11 sm:h-9 rounded-xl bg-white hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-900 dark:text-white border border-slate-200 dark:border-transparent flex items-center justify-center cursor-pointer shadow-xs active:scale-95 text-sm font-black"
                @click="$emit('press', num)"
            >
                {{ num === 'backspace' ? '⌫' : num }}
            </button>
            <button
                v-for="num in ['1','2','3','0']"
                :key="num"
                type="button"
                class="h-11 sm:h-9 rounded-xl bg-white hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-900 dark:text-white border border-slate-200 dark:border-transparent flex items-center justify-center cursor-pointer shadow-xs active:scale-95 text-sm font-black"
                @click="$emit('press', num)"
            >
                {{ num }}
            </button>
            <button
                type="button"
                class="h-11 sm:h-9 rounded-xl bg-white hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-900 dark:text-white border border-slate-200 dark:border-transparent flex items-center justify-center cursor-pointer shadow-xs active:scale-95 text-sm font-black"
                @click="$emit('press', '.')"
            >
                .
            </button>
            <button
                type="button"
                class="h-11 sm:h-9 rounded-xl bg-white hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-900 dark:text-white border border-slate-200 dark:border-transparent flex items-center justify-center cursor-pointer shadow-xs active:scale-95 text-sm font-black"
                @click="$emit('press', '00')"
            >
                00
            </button>
            <button
                type="button"
                class="col-span-2 h-11 sm:h-9 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-tajawal font-black flex items-center justify-center cursor-pointer active:scale-95 text-xs shadow-xs"
                @click="$emit('quick-exact')"
            >
                {{ $t('pos.quick_amount_full') }}
            </button>
        </div>
    </div>
</template>
