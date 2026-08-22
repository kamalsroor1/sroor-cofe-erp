<script setup>
defineProps({
    activeStore: {
        type: Object,
        default: null
    },
    activeShift: {
        type: Object,
        default: null
    },
    showNumpad: {
        type: Boolean,
        default: false
    }
});

defineEmits(['toggle-numpad']);
</script>

<template>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-3 mb-3 flex flex-wrap items-center justify-between gap-3 shrink-0 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-500/15 border border-amber-500/30 text-amber-600 dark:text-amber-400 font-black text-lg flex items-center justify-center">
                ⚡
            </div>
            <div>
                <h1 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>{{ $t('pos.title') }}</span>
                </h1>
                <div class="flex items-center gap-2 mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                    <span>{{ $t('common.store') }}:</span>
                    <span class="text-amber-600 dark:text-amber-400 font-bold font-mono">{{ activeStore?.name || $t('common.main_store_default') }}</span>
                    <span>•</span>
                    <span
                        class="px-2 py-0.2 rounded-md font-mono font-bold"
                        :class="activeShift ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/20 text-rose-600 dark:text-rose-400'"
                    >
                        {{ activeShift ? `${$t('nav.active_shift')} #${activeShift.shift_number || activeShift.id}` : $t('nav.closed_shift') }}
                    </span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                class="h-9 px-3 rounded-xl border text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                :class="showNumpad ? 'bg-theme-primary text-white font-bold font-black border-amber-400 shadow-md' : 'bg-slate-100 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'"
                @click="$emit('toggle-numpad')"
            >
                <span>🔢</span>
                <span>{{ $t('pos.numpad') }}</span>
            </button>

            <router-link
                to="/invoices"
                class="h-9 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 text-xs font-bold flex items-center gap-1.5 transition"
            >
                <span>🧾</span>
                <span>{{ $t('pos.invoices_list') }}</span>
            </router-link>
        </div>
    </div>
</template>
