<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { MoreHorizontal, MoreVertical, X } from 'lucide-vue-next';
import { useNativeBridge } from '@/Composables/useNativeBridge';

const props = defineProps({
    items: {
        type: Array,
        default: () => []
    },
    title: {
        type: String,
        default: ''
    },
    buttonClass: {
        type: String,
        default: ''
    },
    align: {
        type: String,
        default: 'auto' // 'auto' | 'start' | 'end'
    },
    mode: {
        type: String,
        default: 'auto' // 'auto' (dropdown on desktop, sheet on mobile) | 'dropdown' | 'sheet'
    },
    orientation: {
        type: String,
        default: 'horizontal' // 'horizontal' (•••) | 'vertical' (⋮)
    }
});

const isOpen = ref(false);
const menuRef = ref(null);
const menuPosition = ref({ top: 0, left: 0 });
const { triggerHaptic } = useNativeBridge();

// Touch Drag State for Mobile Bottom Action Sheet
const touchStartY = ref(0);
const touchCurrentY = ref(0);
const dragOffset = ref(0);
const isDragging = ref(false);

const updatePosition = () => {
    if (!menuRef.value) return;
    const rect = menuRef.value.getBoundingClientRect();
    const menuWidth = 240;
    const estimatedHeight = Math.min(props.items.length * 44 + 60, 320);

    let top = rect.bottom + 6;
    // If it overflows bottom of viewport, flip to open above
    if (top + estimatedHeight > window.innerHeight && rect.top > estimatedHeight) {
        top = rect.top - estimatedHeight - 6;
    }

    // Horizontal placement
    let left = rect.left;
    if (props.align === 'end') {
        left = rect.right - menuWidth;
    } else if (props.align === 'auto') {
        // In RTL or when button is near left edge, align left to button left so it expands to the right
        if (rect.left < menuWidth && rect.left < window.innerWidth / 2) {
            left = rect.left;
        } else {
            left = rect.right - menuWidth;
        }
    }

    // Clamp inside viewport
    if (left + menuWidth > window.innerWidth - 12) {
        left = window.innerWidth - menuWidth - 12;
    }
    if (left < 12) {
        left = 12;
    }

    menuPosition.value = { top, left };
};

const toggleMenu = () => {
    triggerHaptic('light');
    isOpen.value = !isOpen.value;
    dragOffset.value = 0;
    if (isOpen.value) {
        nextTick(updatePosition);
    }
};

const closeMenu = () => {
    isOpen.value = false;
    dragOffset.value = 0;
};

const handleItemClick = (item) => {
    triggerHaptic('light');
    closeMenu();
    if (item.onClick) {
        item.onClick();
    }
};

const handleClickOutside = (e) => {
    if (menuRef.value && !menuRef.value.contains(e.target)) {
        closeMenu();
    }
};

const handleScrollOrResize = () => {
    if (isOpen.value) {
        closeMenu();
    }
};

// Touch handlers for drag-to-close on mobile sheet
const onTouchStart = (e) => {
    touchStartY.value = e.touches[0].clientY;
    touchCurrentY.value = e.touches[0].clientY;
    isDragging.value = true;
};

const onTouchMove = (e) => {
    if (!isDragging.value) return;
    touchCurrentY.value = e.touches[0].clientY;
    const diff = touchCurrentY.value - touchStartY.value;
    if (diff > 0) {
        dragOffset.value = diff;
    }
};

const onTouchEnd = () => {
    if (!isDragging.value) return;
    if (dragOffset.value > 65) {
        triggerHaptic('medium');
        closeMenu();
    } else {
        dragOffset.value = 0;
    }
    isDragging.value = false;
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    window.addEventListener('scroll', handleScrollOrResize, true);
    window.addEventListener('resize', handleScrollOrResize);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    window.removeEventListener('scroll', handleScrollOrResize, true);
    window.removeEventListener('resize', handleScrollOrResize);
});
</script>

<template>
    <div ref="menuRef" class="relative inline-block text-right font-tajawal">
        <!-- Trigger Button (•••) -->
        <button
            @click.stop="toggleMenu"
            type="button"
            class="h-9 min-w-[36px] px-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700/70 flex items-center justify-center gap-1 transition active:scale-90 cursor-pointer shadow-xs"
            :class="buttonClass"
            :title="title || $t('common.actions')"
        >
            <MoreHorizontal v-if="orientation === 'horizontal'" class="w-4.5 h-4.5 text-current" />
            <MoreVertical v-else class="w-4 h-4 text-current" />
            <slot name="trigger" />
        </button>

        <!-- 🌟 Desktop Teleported Floating Dropdown (Immune to Table Overflow Clipping) -->
        <Teleport to="body">
            <Transition name="dropdown-pop">
                <div
                    v-if="isOpen && (mode === 'dropdown' || mode === 'auto')"
                    class="hidden sm:block fixed z-[99999] w-60 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-1.5 space-y-1 select-none font-tajawal"
                    :style="{ top: `${menuPosition.top}px`, left: `${menuPosition.left}px` }"
                    dir="rtl"
                    @click.stop
                >
                    <div v-if="title" class="px-3 py-1.5 text-[11px] font-black text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-800/80 truncate">
                        {{ title }}
                    </div>

                    <template v-for="(item, idx) in items" :key="idx">
                        <template v-if="item.show !== false">
                            <router-link
                                v-if="item.href"
                                :to="item.href"
                                @click="closeMenu"
                                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-bold transition text-right cursor-pointer"
                                :class="[
                                    item.variant === 'danger'
                                        ? 'text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40'
                                        : (item.variant === 'warning'
                                            ? 'text-theme-primary hover:bg-theme-light hover:bg-theme-light/40'
                                            : (item.variant === 'success'
                                                ? 'text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40'
                                                : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'))
                                ]"
                            >
                                <component v-if="item.icon" :is="item.icon" class="w-4 h-4 shrink-0" />
                                <span v-else-if="item.emoji" class="text-sm shrink-0">{{ item.emoji }}</span>
                                <div class="flex-1 min-w-0">
                                    <span class="block truncate">{{ item.label }}</span>
                                    <span v-if="item.description" class="block text-[10px] text-slate-400 font-normal truncate">{{ item.description }}</span>
                                </div>
                            </router-link>

                            <button
                                v-else
                                @click="handleItemClick(item)"
                                type="button"
                                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-bold transition text-right cursor-pointer"
                                :class="[
                                    item.variant === 'danger'
                                        ? 'text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40'
                                        : (item.variant === 'warning'
                                            ? 'text-theme-primary hover:bg-theme-light hover:bg-theme-light/40'
                                            : (item.variant === 'success'
                                                ? 'text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40'
                                                : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'))
                                ]"
                            >
                                <component v-if="item.icon" :is="item.icon" class="w-4 h-4 shrink-0" />
                                <span v-else-if="item.emoji" class="text-sm shrink-0">{{ item.emoji }}</span>
                                <div class="flex-1 min-w-0">
                                    <span class="block truncate">{{ item.label }}</span>
                                    <span v-if="item.description" class="block text-[10px] text-slate-400 font-normal truncate">{{ item.description }}</span>
                                </div>
                            </button>
                        </template>
                    </template>
                </div>
            </Transition>
        </Teleport>

        <!-- 📱 Mobile Native Bottom Action Sheet (Auto on mobile or when mode === 'sheet') -->
        <Teleport to="body">
            <Transition name="sheet-slide">
                <div
                    v-if="isOpen && (mode === 'sheet' || mode === 'auto')"
                    class="sm:hidden fixed inset-0 z-[99999] bg-slate-950/75 backdrop-blur-xs flex items-end justify-center font-tajawal select-none"
                    dir="rtl"
                    @click="closeMenu"
                >
                    <div
                        @click.stop
                        class="w-full bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 rounded-t-3xl p-5 shadow-2xl space-y-4 max-h-[85vh] flex flex-col text-slate-900 dark:text-white transition-transform duration-150 ease-out pb-[max(1.25rem,env(safe-area-inset-bottom,1.25rem))]"
                        :style="dragOffset > 0 ? { transform: `translateY(${dragOffset}px)` } : {}"
                    >
                        <!-- Native Drag Handle (Drag-to-Close) -->
                        <div
                            class="flex flex-col items-center justify-center -mt-2 -mb-1 py-1 cursor-grab active:cursor-grabbing shrink-0"
                            @touchstart="onTouchStart"
                            @touchmove="onTouchMove"
                            @touchend="onTouchEnd"
                        >
                            <div class="w-12 h-1.5 rounded-full bg-slate-300 dark:bg-slate-700" />
                        </div>

                        <!-- Sheet Header -->
                        <div
                            class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5 shrink-0"
                            @touchstart="onTouchStart"
                            @touchmove="onTouchMove"
                            @touchend="onTouchEnd"
                        >
                            <div>
                                <h3 class="font-black text-sm text-slate-900 dark:text-white">{{ title || $t('common.action_options') }}</h3>
                                <p class="text-[11px] text-slate-400 font-bold">{{ $t('common.select_action') }}</p>
                            </div>
                            <button
                                @click="closeMenu"
                                type="button"
                                class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-400 flex items-center justify-center active:scale-90 cursor-pointer shadow-xs"
                            >
                                <X class="w-4 h-4" />
                            </button>
                        </div>

                        <!-- Action List -->
                        <div class="overflow-y-auto space-y-2 py-1">
                            <template v-for="(item, idx) in items" :key="idx">
                                <template v-if="item.show !== false">
                                    <router-link
                                        v-if="item.href"
                                        :to="item.href"
                                        @click="closeMenu"
                                        class="w-full min-h-[48px] px-4 py-3 rounded-2xl flex items-center gap-3.5 text-xs font-black transition text-right active:scale-98 border shadow-xs"
                                        :class="[
                                            item.variant === 'danger'
                                                ? 'bg-rose-500/10 border-rose-500/25 text-rose-600 dark:text-rose-400 hover:bg-rose-500/20'
                                                : (item.variant === 'warning'
                                                    ? 'bg-theme-light border-theme-primary/25 text-theme-primary text-theme-primary hover:bg-theme-hover/20'
                                                    : (item.variant === 'success'
                                                        ? 'bg-emerald-500/10 border-emerald-500/25 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20'
                                                        : 'bg-slate-50 dark:bg-slate-800/60 border-slate-200 dark:border-slate-700/60 text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800'))
                                        ]"
                                    >
                                        <component v-if="item.icon" :is="item.icon" class="w-5 h-5 shrink-0" />
                                        <span v-else-if="item.emoji" class="text-base shrink-0">{{ item.emoji }}</span>
                                        <div class="flex-1 min-w-0">
                                            <span class="block text-xs font-black truncate">{{ item.label }}</span>
                                            <span v-if="item.description" class="block text-[10px] text-slate-400 font-bold truncate">{{ item.description }}</span>
                                        </div>
                                        <span class="text-slate-400 text-xs">←</span>
                                    </router-link>

                                    <button
                                        v-else
                                        @click="handleItemClick(item)"
                                        type="button"
                                        class="w-full min-h-[48px] px-4 py-3 rounded-2xl flex items-center gap-3.5 text-xs font-black transition text-right active:scale-98 border shadow-xs cursor-pointer"
                                        :class="[
                                            item.variant === 'danger'
                                                ? 'bg-rose-500/10 border-rose-500/25 text-rose-600 dark:text-rose-400 hover:bg-rose-500/20'
                                                : (item.variant === 'warning'
                                                    ? 'bg-theme-light border-theme-primary/25 text-theme-primary text-theme-primary hover:bg-theme-hover/20'
                                                    : (item.variant === 'success'
                                                        ? 'bg-emerald-500/10 border-emerald-500/25 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20'
                                                        : 'bg-slate-50 dark:bg-slate-800/60 border-slate-200 dark:border-slate-700/60 text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800'))
                                        ]"
                                    >
                                        <component v-if="item.icon" :is="item.icon" class="w-5 h-5 shrink-0" />
                                        <span v-else-if="item.emoji" class="text-base shrink-0">{{ item.emoji }}</span>
                                        <div class="flex-1 min-w-0">
                                            <span class="block text-xs font-black truncate">{{ item.label }}</span>
                                            <span v-if="item.description" class="block text-[10px] text-slate-400 font-bold truncate">{{ item.description }}</span>
                                        </div>
                                        <span class="text-slate-400 text-xs">←</span>
                                    </button>
                                </template>
                            </template>
                        </div>

                        <!-- Close Button -->
                        <button
                            @click="closeMenu"
                            type="button"
                            class="w-full h-12 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs flex items-center justify-center transition active:scale-95 cursor-pointer shadow-xs shrink-0"
                        >
                            {{ $t('common.cancel') }}
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.dropdown-pop-enter-active,
.dropdown-pop-leave-active {
    transition: opacity 0.15s cubic-bezier(0.16, 1, 0.3, 1), transform 0.15s cubic-bezier(0.16, 1, 0.3, 1);
}
.dropdown-pop-enter-from,
.dropdown-pop-leave-to {
    opacity: 0;
    transform: scale(0.95) translateY(-4px);
}

.sheet-slide-enter-active,
.sheet-slide-leave-active {
    transition: opacity 0.2s ease, transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.sheet-slide-enter-from,
.sheet-slide-leave-to {
    opacity: 0;
    transform: translateY(100%);
}
</style>
