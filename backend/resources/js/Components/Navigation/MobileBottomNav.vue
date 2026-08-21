<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import FeatureGate from '@/Components/FeatureGate.vue';
import { useNativeBridge } from '@/Composables/useNativeBridge';
import {
    LayoutDashboard,
    Receipt,
    Zap,
    Package,
    Wallet,
    Menu,
} from 'lucide-vue-next';

const props = defineProps({
    activeShift: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['open-drawer']);

const page = usePage();
const { triggerHaptic } = useNativeBridge();

const isCurrentUrl = (path, exact = false) => {
    const current = page.url || '';
    if (exact) {
        return current === path || current === `${path}/`;
    }
    return current.startsWith(path);
};

const isDashboardActive = computed(() => isCurrentUrl('/', true) || isCurrentUrl('/dashboard', true));
const isInvoicesActive = computed(() => isCurrentUrl('/invoices') && !isCurrentUrl('/invoices/create'));
const isPosActive = computed(() => isCurrentUrl('/pos'));
const isItemsActive = computed(() => isCurrentUrl('/items'));
const isShiftActive = computed(() => isCurrentUrl('/daily-journal'));

const onTabPress = (type = 'light') => {
    triggerHaptic(type);
};
</script>

<template>
    <!-- Fixed Mobile Bottom Navigation Bar (Hidden on desktop screens >= lg) -->
    <nav
        aria-label="Mobile Bottom Navigation"
        class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-white/95 dark:bg-slate-900/95 backdrop-blur-2xl border-t border-slate-200/90 dark:border-slate-800/90 px-2 pt-1.5 pb-[max(0.6rem,env(safe-area-inset-bottom,0.6rem))] flex items-center justify-around font-tajawal shadow-2xl select-none"
    >
        <!-- 1. Home / Dashboard -->
        <Link
            href="/"
            @click="onTabPress('light')"
            class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 group active:scale-90 relative"
            :class="isDashboardActive ? 'text-theme-primary font-black' : 'text-slate-400 dark:text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
        >
            <div class="relative flex items-center justify-center">
                <span
                    v-if="isDashboardActive"
                    class="absolute -top-1 w-6 h-0.5 rounded-full bg-theme-primary animate-pulse"
                />
                <LayoutDashboard class="w-5 h-5 mb-0.5 transition-transform duration-200" :class="isDashboardActive ? 'scale-110' : 'group-hover:scale-105'" />
            </div>
            <span class="text-[10px] tracking-tight truncate">{{ $t('nav.dashboard_short') }}</span>
        </Link>

        <!-- 2. Invoices / Sales -->
        <FeatureGate feature="invoices.create">
            <Link
                href="/invoices"
                @click="onTabPress('light')"
                class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 group active:scale-90 relative"
                :class="isInvoicesActive ? 'text-theme-primary font-black' : 'text-slate-400 dark:text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
            >
                <div class="relative flex items-center justify-center">
                    <span
                        v-if="isInvoicesActive"
                        class="absolute -top-1 w-6 h-0.5 rounded-full bg-theme-primary animate-pulse"
                    />
                    <Receipt class="w-5 h-5 mb-0.5 transition-transform duration-200" :class="isInvoicesActive ? 'scale-110' : 'group-hover:scale-105'" />
                </div>
                <span class="text-[10px] tracking-tight truncate">{{ $t('nav.invoices_short') }}</span>
            </Link>
        </FeatureGate>

        <!-- 3. Primary Center Action: Raised Fast POS -->
        <FeatureGate feature="pos.access">
            <div class="flex-1 flex items-center justify-center">
                <Link
                    href="/pos"
                    @click="onTabPress('medium')"
                    class="relative -top-4.5 w-13 h-13 rounded-2xl btn-primary-theme flex items-center justify-center shadow-theme-primary transition-all duration-200 active:scale-90 cursor-pointer ring-4 ring-white dark:ring-slate-900 group"
                    :class="isPosActive ? 'scale-105 ring-theme-primary/30' : ''"
                    :title="$t('nav.pos_fast')"
                >
                    <Zap class="w-6 h-6 fill-current text-white transition-transform group-hover:rotate-12 duration-300" />
                </Link>
            </div>
        </FeatureGate>

        <!-- 4. Items & Stock -->
        <Link
            href="/items"
            @click="onTabPress('light')"
            class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 group active:scale-90 relative"
            :class="isItemsActive ? 'text-theme-primary font-black' : 'text-slate-400 dark:text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
        >
            <div class="relative flex items-center justify-center">
                <span
                    v-if="isItemsActive"
                    class="absolute -top-1 w-6 h-0.5 rounded-full bg-theme-primary animate-pulse"
                />
                <Package class="w-5 h-5 mb-0.5 transition-transform duration-200" :class="isItemsActive ? 'scale-110' : 'group-hover:scale-105'" />
            </div>
            <span class="text-[10px] tracking-tight truncate">{{ $t('nav.items_short') }}</span>
        </Link>

        <!-- 5. Shift & Cash Drawer -->
        <Link
            href="/daily-journal"
            @click="onTabPress('light')"
            class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 group active:scale-90 relative"
            :class="isShiftActive ? 'text-theme-primary font-black' : 'text-slate-400 dark:text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
        >
            <div class="relative flex items-center justify-center">
                <span
                    v-if="isShiftActive"
                    class="absolute -top-1 w-6 h-0.5 rounded-full bg-theme-primary animate-pulse"
                />
                <Wallet class="w-5 h-5 mb-0.5 transition-transform duration-200" :class="isShiftActive ? 'scale-110' : 'group-hover:scale-105'" />
                <!-- Active Shift Indicator Dot -->
                <span
                    v-if="activeShift"
                    class="absolute -top-0.5 -right-1 w-2 h-2 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-slate-900 animate-pulse"
                />
            </div>
            <span class="text-[10px] tracking-tight truncate">{{ $t('nav.shift_short') }}</span>
        </Link>

        <!-- 6. More / Drawer Menu -->
        <button
            @click="onTabPress('light'); emit('open-drawer');"
            type="button"
            class="flex-1 flex flex-col items-center justify-center py-1 px-1 rounded-2xl transition-all duration-200 active:scale-90 text-slate-400 dark:text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 cursor-pointer group"
            :title="$t('nav.more_short')"
        >
            <div class="relative flex items-center justify-center">
                <Menu class="w-5 h-5 mb-0.5 transition-transform duration-200 group-hover:scale-105" />
            </div>
            <span class="text-[10px] tracking-tight truncate">{{ $t('nav.more_short') }}</span>
        </button>
    </nav>
</template>
