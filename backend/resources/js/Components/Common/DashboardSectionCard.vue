<template>
    <div
        class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xl space-y-4"
        :class="wrapperClass"
    >
        <!-- Card Header -->
        <div
            class="flex items-start sm:items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-800 pb-3"
            :class="headerClass"
        >
            <!-- Left: Icon + Title + Subtitle -->
            <div class="flex items-center gap-2">
                <!-- Icon Box (optional) -->
                <div
                    v-if="icon || iconEmoji"
                    class="w-7 h-7 rounded-xl flex items-center justify-center shrink-0"
                    :class="[iconBg, iconColor]"
                >
                    <component v-if="icon" :is="icon" class="w-4 h-4" />
                    <span v-else class="text-sm">{{ iconEmoji }}</span>
                </div>
                <!-- Dot indicator (alternative to icon) -->
                <span
                    v-else-if="dotColor"
                    class="w-2.5 h-2.5 rounded-full shrink-0"
                    :class="[dotColor, dotPulse ? 'animate-pulse' : '']"
                />

                <div>
                    <h2 class="text-sm font-black text-slate-900 dark:text-white">{{ title }}</h2>
                    <p v-if="subtitle" class="text-[10px] text-slate-400 font-bold">{{ subtitle }}</p>
                </div>
            </div>

            <!-- Right: Slot for badge, link, or custom action -->
            <slot name="action" />
        </div>

        <!-- Card Body -->
        <slot />
    </div>
</template>

<script setup>
defineProps({
    // Title & description
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },

    // Option A: Lucide icon component
    icon: { type: [Object, Function], default: null },
    iconBg: { type: String, default: 'bg-slate-100 dark:bg-slate-800' },
    iconColor: { type: String, default: 'text-slate-500' },

    // Option B: Emoji icon (e.g. '📊')
    iconEmoji: { type: String, default: '' },

    // Option C: Colored dot (e.g. for "آخر الفواتير" style headers)
    dotColor: { type: String, default: '' },       // e.g. 'bg-emerald-500'
    dotPulse: { type: Boolean, default: false },

    // Wrapper & header extra classes
    wrapperClass: { type: String, default: '' },
    headerClass: { type: String, default: '' },
});
</script>