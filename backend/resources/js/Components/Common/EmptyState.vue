<script setup>
import { computed } from 'vue';
import { PackageOpen } from 'lucide-vue-next';

const props = defineProps({
    icon: {
        type: [Object, Function],
        default: () => PackageOpen
    },
    title: {
        type: String,
        default: ''
    },
    description: {
        type: String,
        default: ''
    },
    actionLabel: {
        type: String,
        default: ''
    },
    actionHref: {
        type: String,
        default: ''
    },
    actionIcon: {
        type: [Object, Function],
        default: null
    }
});

const emit = defineEmits(['action']);

const handleAction = () => {
    emit('action');
};
</script>

<template>
    <div class="py-14 sm:py-16 text-center space-y-3 font-tajawal select-none">
        <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto rounded-3xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/60 flex items-center justify-center text-slate-400 dark:text-slate-500 shadow-inner">
            <component :is="icon" class="w-8 h-8 sm:w-10 sm:h-10 stroke-[1.5]" />
        </div>

        <div class="space-y-1 max-w-sm mx-auto px-4">
            <p class="text-xs sm:text-sm font-black text-slate-700 dark:text-slate-300">
                {{ title || $t('common.no_data_available') || 'لا توجد بيانات متاحة' }}
            </p>
            <p v-if="description" class="text-[11px] sm:text-xs text-slate-400 dark:text-slate-500 font-medium leading-relaxed">
                {{ description }}
            </p>
        </div>

        <!-- Custom or Pre-defined Action Button -->
        <div v-if="$slots.default || (actionLabel && (actionHref || $attrs.onAction))" class="pt-2 flex items-center justify-center gap-2">
            <slot>
                <router-link
                    v-if="actionHref"
                    :to="actionHref"
                    class="h-10 px-5 rounded-2xl btn-primary-theme text-xs font-black flex items-center gap-1.5 transition active:scale-95 shadow-theme-sm cursor-pointer"
                >
                    <component v-if="actionIcon" :is="actionIcon" class="w-4 h-4" />
                    <span>{{ actionLabel }}</span>
                </router-link>
                <button
                    v-else-if="actionLabel"
                    @click="handleAction"
                    type="button"
                    class="h-10 px-5 rounded-2xl btn-primary-theme text-xs font-black flex items-center gap-1.5 transition active:scale-95 shadow-theme-sm cursor-pointer"
                >
                    <component v-if="actionIcon" :is="actionIcon" class="w-4 h-4" />
                    <span>{{ actionLabel }}</span>
                </button>
            </slot>
        </div>
    </div>
</template>
