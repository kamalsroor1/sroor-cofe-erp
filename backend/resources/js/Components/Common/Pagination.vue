<script setup>
import { computed } from 'vue';

const props = defineProps({
    links: {
        type: Array,
        default: () => []
    },
    from: {
        type: [Number, String],
        default: 0
    },
    to: {
        type: [Number, String],
        default: 0
    },
    total: {
        type: [Number, String],
        default: 0
    }
});

const emit = defineEmits(['page-change']);

const shouldRender = computed(() => {
    return props.links && props.links.length > 3;
});

const extractPage = (url) => {
    if (!url) return null;
    try {
        const parsed = new URL(url, window.location.origin);
        return parsed.searchParams.get('page');
    } catch {
        const match = url.match(/[?&]page=(\d+)/);
        return match ? match[1] : null;
    }
};

const handlePageClick = (link) => {
    if (!link.url) return;
    const page = extractPage(link.url);
    if (page) {
        emit('page-change', parseInt(page, 10));
    }
};
</script>

<template>
    <div
        v-if="shouldRender"
        class="pt-4 border-t border-slate-200 dark:border-slate-800/80 flex flex-col sm:flex-row items-center justify-between gap-3 font-sans select-none"
    >
        <span class="text-xs text-slate-500 dark:text-slate-400 font-tajawal font-bold">
            {{ $t('common.showing_results', { from: from || 0, to: to || 0, total: total || 0 }) || `عرض ${from || 0} إلى ${to || 0} من إجمالي ${total || 0}` }}
        </span>

        <div class="flex items-center gap-1 flex-wrap justify-center font-tajawal">
            <template v-for="(link, lIdx) in links" :key="lIdx">
                <button
                    v-if="link.url"
                    type="button"
                    @click="handlePageClick(link)"
                    class="h-9 min-w-[36px] px-3 rounded-xl text-xs font-bold transition flex items-center justify-center cursor-pointer active:scale-95 shadow-xs"
                    :class="[
                        link.active
                            ? 'bg-theme-primary text-slate-950 font-black shadow-theme-sm'
                            : 'bg-white dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/60 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700'
                    ]"
                    v-html="link.label"
                />
                <span
                    v-else
                    class="h-9 min-w-[36px] px-3 rounded-xl text-xs text-slate-400 dark:text-slate-600 font-bold flex items-center justify-center opacity-60"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>
