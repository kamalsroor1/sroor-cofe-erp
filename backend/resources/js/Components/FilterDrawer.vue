<script setup>
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    activeCount: { type: Number, default: 0 },
});

const emit = defineEmits(['close', 'apply', 'reset']);

const handleKeydown = (e) => {
    if (e.key === 'Escape' && props.show) {
        emit('close');
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <teleport to="body">
        <Transition name="drawer">
            <div v-if="show" class="fixed inset-0 z-50 overflow-hidden font-tajawal" dir="rtl">
                <!-- Backdrop -->
                <div
                    @click="emit('close')"
                    class="fixed inset-0 bg-white dark:bg-slate-950/80 backdrop-blur-xs transition-opacity"
                />

                <!-- Slide-Over Drawer Container -->
                <div class="fixed inset-y-0 left-0 max-w-full flex pl-0 sm:pl-10 pointer-events-none">
                    <div
                        class="drawer-panel w-screen max-w-md bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 shadow-2xl flex flex-col justify-between pointer-events-auto"
                    >
                        <!-- Drawer Header -->
                        <div class="p-5 border-b border-slate-200 dark:border-slate-800/80 bg-slate-50/90 dark:bg-slate-900/90 flex items-center justify-between shrink-0">
                            <div class="flex items-center gap-2.5">
                                <div class="w-10 h-10 rounded-2xl bg-amber-500/15 border border-amber-500/30 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-base shadow-xs">
                                    🔍
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-black text-sm sm:text-base text-slate-900 dark:text-white">{{ title || $t('common.filter_drawer_title') }}</h3>
                                        <span v-if="activeCount > 0" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-500 text-slate-950">
                                            {{ activeCount }} {{ $t('common.active_count') }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ subtitle || $t('common.filter_drawer_subtitle') }}</p>
                                </div>
                            </div>

                            <button
                                @click="emit('close')"
                                type="button"
                                class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white flex items-center justify-center text-sm font-bold transition active:scale-90 cursor-pointer shadow-xs"
                            >
                                ✕
                            </button>
                        </div>

                        <!-- Drawer Scrollable Content -->
                        <div class="flex-1 overflow-y-auto p-5 space-y-6">
                            <slot />
                        </div>

                        <!-- Drawer Footer (Sticky Actions) -->
                        <div class="p-4 border-t border-slate-200 dark:border-slate-800/80 bg-slate-50 dark:bg-slate-950/60 flex items-center justify-between gap-3 shrink-0">
                            <button
                                @click="emit('reset')"
                                type="button"
                                class="h-11 px-4 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white text-xs font-bold transition active:scale-95 cursor-pointer flex items-center gap-1.5 border border-slate-200 dark:border-transparent shadow-xs"
                            >
                                <span>🔄</span>
                                <span>{{ $t('common.reset_filters') }}</span>
                            </button>

                            <div class="flex items-center gap-2">
                                <button
                                    @click="emit('close')"
                                    type="button"
                                    class="h-11 px-4 rounded-2xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold transition active:scale-95 cursor-pointer shadow-xs"
                                >
                                    {{ $t('common.cancel') }}
                                </button>

                                <button
                                    @click="emit('apply')"
                                    type="button"
                                    class="h-11 px-5 rounded-2xl btn-primary-theme text-xs font-black shadow-theme-primary transition transform active:scale-95 cursor-pointer flex items-center gap-1.5"
                                >
                                    <span>🚀</span>
                                    <span>{{ $t('common.apply_filters') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </teleport>
</template>

<style scoped>
/* Backdrop Fade Transition */
.drawer-enter-active,
.drawer-leave-active {
    transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.drawer-enter-from,
.drawer-leave-to {
    opacity: 0;
}

/* Panel Slide Transition */
.drawer-enter-active .drawer-panel {
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.35s ease;
}

.drawer-leave-active .drawer-panel {
    transition: transform 0.25s cubic-bezier(0.4, 0, 1, 1), opacity 0.25s ease;
}

.drawer-enter-from .drawer-panel,
.drawer-leave-to .drawer-panel {
    transform: translateX(-100%);
    opacity: 0.7;
}
</style>