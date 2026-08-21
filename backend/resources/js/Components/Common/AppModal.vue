<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { useNativeBridge } from '@/Composables/useNativeBridge';

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    title: {
        type: String,
        default: ''
    },
    subtitle: {
        type: String,
        default: ''
    },
    icon: {
        type: [String, Object, Function],
        default: null
    },
    maxWidth: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl', 'full'].includes(v)
    },
    closeable: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['close']);
const { triggerHaptic } = useNativeBridge();

// Touch Drag-to-Close State for Mobile Bottom Sheet
const touchStartY = ref(0);
const touchCurrentY = ref(0);
const dragOffset = ref(0);
const isDragging = ref(false);

const maxWidthClass = {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-md',
    lg: 'sm:max-w-lg',
    xl: 'sm:max-w-xl',
    '2xl': 'sm:max-w-2xl',
    '3xl': 'sm:max-w-3xl',
    '4xl': 'sm:max-w-4xl',
    '5xl': 'sm:max-w-5xl',
    full: 'sm:max-w-full sm:m-4',
}[props.maxWidth];

const close = () => {
    if (props.closeable) {
        dragOffset.value = 0;
        emit('close');
    }
};

const handleKeyDown = (e) => {
    if (e.key === 'Escape' && props.show && props.closeable) {
        close();
    }
};

// Drag gesture handlers (Mobile only)
const onTouchStart = (e) => {
    if (!props.closeable) return;
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
    if (dragOffset.value > 75) {
        triggerHaptic('medium');
        close();
    } else {
        dragOffset.value = 0;
    }
    isDragging.value = false;
};

onMounted(() => {
    document.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeyDown);
});

watch(() => props.show, (val) => {
    dragOffset.value = 0;
    if (val) {
        document.body.classList.add('overflow-hidden');
    } else {
        document.body.classList.remove('overflow-hidden');
    }
});
</script>

<template>
    <Teleport to="body">
        <Transition name="sheet-slide">
            <div
                v-if="show"
                class="fixed inset-0 z-50 bg-black/75 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4 font-tajawal select-none"
                dir="rtl"
                @click="close"
            >
                <!-- Sheet Panel Container -->
                <div
                    class="w-full bg-white dark:bg-slate-900 border-t sm:border border-slate-200 dark:border-slate-800 rounded-t-3xl sm:rounded-3xl p-5 sm:p-6 shadow-2xl space-y-4 text-slate-900 dark:text-white max-h-[90vh] sm:max-h-[85vh] flex flex-col transition-transform duration-150 ease-out pb-[max(1.25rem,env(safe-area-inset-bottom,1.25rem))] sm:pb-6"
                    :class="maxWidthClass"
                    :style="dragOffset > 0 ? { transform: `translateY(${dragOffset}px)` } : {}"
                    @click.stop
                >
                    <!-- Native Mobile Drag Handle (Touch Target for Drag-to-Close) -->
                    <div
                        class="sm:hidden flex flex-col items-center justify-center -mt-2 -mb-1 py-1 cursor-grab active:cursor-grabbing shrink-0"
                        @touchstart="onTouchStart"
                        @touchmove="onTouchMove"
                        @touchend="onTouchEnd"
                    >
                        <div class="w-12 h-1.5 rounded-full bg-slate-300 dark:bg-slate-700 transition-colors" />
                    </div>

                    <!-- Header -->
                    <div
                        v-if="title || $slots.header"
                        class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3 shrink-0"
                        @touchstart="onTouchStart"
                        @touchmove="onTouchMove"
                        @touchend="onTouchEnd"
                    >
                        <slot name="header">
                            <div class="flex items-center gap-2.5">
                                <span v-if="typeof icon === 'string'" class="text-xl shrink-0">{{ icon }}</span>
                                <component :is="icon" v-else-if="icon" class="w-5 h-5 text-theme-primary shrink-0" />
                                <div>
                                    <h3 class="font-black text-sm sm:text-base text-slate-900 dark:text-white leading-tight">
                                        {{ title }}
                                    </h3>
                                    <p v-if="subtitle" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 leading-snug">
                                        {{ subtitle }}
                                    </p>
                                </div>
                            </div>
                        </slot>

                        <button
                            v-if="closeable"
                            type="button"
                            class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-900 dark:hover:text-white text-xs font-bold flex items-center justify-center transition active:scale-90 cursor-pointer shrink-0 shadow-xs"
                            @click="close"
                        >
                            ✕
                        </button>
                    </div>

                    <!-- Body / Content (Scrollable) -->
                    <div class="flex-1 overflow-y-auto pr-0.5">
                        <slot />
                    </div>

                    <!-- Footer -->
                    <div v-if="$slots.footer" class="border-t border-slate-200 dark:border-slate-800 pt-3 shrink-0">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
