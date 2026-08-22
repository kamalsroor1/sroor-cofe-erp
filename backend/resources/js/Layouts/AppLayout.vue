<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import FeatureGate from '@/Components/FeatureGate.vue';
import { trans } from '@/helpers/trans';
import { useTheme } from '@/Composables/useTheme';
import { useNativeBridge } from '@/Composables/useNativeBridge';
import { notifySuccess, notifyError } from '@/helpers/alert';
import MobileBottomNav from '@/Components/Navigation/MobileBottomNav.vue';
import {
    LayoutDashboard,
    Receipt,
    Wallet,
    Users,
    Package,
    Boxes,
    Truck,
    Store,
    ShoppingCart,
    Bot,
    Building2,
    Coins,
    RotateCcw,
    BarChart3,
    Coffee,
    ShieldCheck,
    ClipboardList,
    Trash2,
    Settings,
    Crown,
    Menu,
    PanelRightClose,
    PanelRightOpen,
    Clock,
    Zap,
    Bell,
    Sun,
    Moon,
    ChevronDown,
    CheckCircle2,
    AlertTriangle,
    XCircle,
    X,
    User,
    Key,
    LogOut,
    Plus,
    Building
} from 'lucide-vue-next';

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const tenant = computed(() => page.props.tenant);
const activeStore = computed(() => page.props.activeStore);
const stores = computed(() => page.props.stores || []);
const activeShift = computed(() => page.props.activeShift);
const isAdmin = computed(() => user.value.roles?.includes('admin') || user.value.roles?.includes('super_admin'));
const isSuperAdmin = computed(() => user.value.is_super_admin || user.value.roles?.includes('super_admin') || user.value.permissions?.includes('super_admin.access'));

// Branding Dual Mode Logos
const branding = computed(() => page.props.branding || {});
const logoLightSrc = computed(() => branding.value.logo_light || '/logo-light.png');
const logoDarkSrc = computed(() => branding.value.logo_dark || '/logo-dark.png');

// Sidebar collapse & Mobile drawer state
const isSidebarOpen = ref(false);
const isSidebarCollapsed = ref(false);

// Header user menu, Notification dropdown & Store modal state
const showUserMenu = ref(false);
const showNotifications = ref(false);
const showNotificationsSheet = ref(false);
const showStoreModal = ref(false);

const notifications = computed(() => page.props.system_notifications || []);

// Theme Composable
const systemThemeColor = computed(() => page.props.system_theme_color || 'amber');
const { currentTheme, currentColor, toggleTheme, applyColorTheme, initTheme } = useTheme(user.value.theme_preference || 'dark', systemThemeColor.value);

// Native Hardware Bridge Composable
const { isNative, isOnline, triggerHaptic, setStatusBar } = useNativeBridge();

// Live Arabic Clock & Date
const currentTime = ref('');
const currentDate = ref('');
let timerInterval = null;

const updateClock = () => {
    const now = new Date();
    try {
        currentTime.value = now.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        currentDate.value = now.toLocaleDateString('ar-EG', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    } catch (e) {
        currentTime.value = now.toLocaleTimeString();
        currentDate.value = now.toLocaleDateString();
    }
};

// Global Hotkeys Listener (F2 for POS, Escape for modals)
const handleKeydown = (e) => {
    if (e.key === 'F2') {
        e.preventDefault();
        router.visit('/pos');
    }
    if (e.key === 'Escape') {
        showStoreModal.value = false;
        showUserMenu.value = false;
        showNotifications.value = false;
        isSidebarOpen.value = false;
    }
};

const layoutProps = defineProps({
    defaultCollapsed: { type: Boolean, default: false },
});

onMounted(() => {
    initTheme();
    const isPos = window.location.pathname.startsWith('/pos') || window.location.pathname.startsWith('/invoices/create') || layoutProps.defaultCollapsed;
    if (isPos) {
        isSidebarCollapsed.value = true;
    } else {
        try {
            const savedCollapsed = localStorage.getItem('sidebar_collapsed');
            if (savedCollapsed !== null) {
                isSidebarCollapsed.value = savedCollapsed === 'true';
            }
        } catch (e) {}
    }

    updateClock();
    timerInterval = setInterval(updateClock, 1000);
    window.addEventListener('keydown', handleKeydown);

    initTheme(systemThemeColor.value);
    setStatusBar(currentTheme.value === 'dark' ? '#020617' : '#ffffff', currentTheme.value === 'light');

    // Initial Flash Check
    if (page.props.flash?.success) notifySuccess(page.props.flash.success);
    if (page.props.flash?.error) notifyError(page.props.flash.error);
});

watch(currentTheme, (newTheme) => {
    setStatusBar(newTheme === 'dark' ? '#020617' : '#ffffff', newTheme === 'light');
});

watch(systemThemeColor, (newColor) => {
    if (newColor) applyColorTheme(newColor);
});

watch(() => page.props.flash, (newFlash) => {
    if (newFlash?.success) {
        notifySuccess(newFlash.success);
    }
    if (newFlash?.error) {
        notifyError(newFlash.error);
    }
}, { deep: true });

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
    window.removeEventListener('keydown', handleKeydown);
});

const toggleSidebar = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
    try {
        localStorage.setItem('sidebar_collapsed', isSidebarCollapsed.value ? 'true' : 'false');
    } catch (e) {}
};

const switchStore = (storeId) => {
    router.post('/store/switch', { store_id: storeId }, {
        preserveScroll: true,
        onSuccess: () => {
            showStoreModal.value = false;
        }
    });
};

const logout = () => {
    router.post('/logout');
};

// Navigation Groups & Items with Lucide Icons
const navigationGroups = computed(() => [
    {
        title: '',
        items: [
            { name: trans('nav.dashboard'), href: '/', icon: LayoutDashboard, active: page.url === '/' || page.url === '', feature: null },
        ]
    },
    {
        title: trans('nav.group_sales'),
        items: [
            { name: trans('nav.invoices_log'), href: '/invoices', icon: Receipt, active: page.url === '/invoices' || page.url.startsWith('/invoices/'), feature: 'invoices.create' },
            { name: trans('nav.daily_journal'), href: '/daily-journal', icon: Wallet, active: page.url.startsWith('/daily-journal') || page.url.startsWith('/shifts'), feature: 'shifts.manage' },
            { name: trans('nav.customers'), href: '/customers', icon: Users, active: page.url.startsWith('/customers'), feature: null },
        ]
    },
    {
        title: trans('nav.group_inventory'),
        items: [
            { name: trans('nav.items_catalog'), href: '/items', icon: Package, active: page.url.startsWith('/items'), feature: 'items.manage' },
            { name: trans('nav.store_stocks'), href: '/store-stocks', icon: Boxes, active: page.url.startsWith('/store-stocks'), feature: 'items.view' },
            { name: trans('nav.stock_transfers'), href: '/stock-transfers', icon: Truck, active: page.url.startsWith('/stock-transfers'), feature: 'transfers.manage' },
            { name: trans('nav.stores'), href: '/stores', icon: Store, active: page.url.startsWith('/stores'), feature: null },
            { name: trans('nav.purchases'), href: '/purchases', icon: ShoppingCart, active: page.url.startsWith('/purchases'), feature: 'purchases.manage' },
            { name: trans('nav.smart_reorder'), href: '/purchases/smart-reorder', icon: Bot, active: page.url.startsWith('/purchases/smart-reorder'), feature: 'purchases.reorder' },
            { name: trans('nav.suppliers'), href: '/suppliers', icon: Building2, active: page.url.startsWith('/suppliers'), feature: null },
        ]
    },
    {
        title: trans('nav.group_financials'),
        items: [
            { name: trans('nav.expenses'), href: '/expenses', icon: Coins, active: page.url.startsWith('/expenses'), feature: 'expenses.manage' },
            { name: trans('nav.returns_adjustments'), href: '/returns', icon: RotateCcw, active: page.url.startsWith('/returns'), feature: 'returns.manage' },
            { name: trans('nav.reports'), href: '/reports', icon: BarChart3, active: page.url.startsWith('/reports'), feature: 'reports.advanced' },
            { name: trans('nav.coffee_blender'), href: '/coffee-blender', icon: Coffee, active: page.url.startsWith('/coffee-blender'), feature: 'blender.access' },
        ]
    },
    {
        title: trans('nav.group_management'),
        items: [
            { name: trans('nav.users'), href: '/users', icon: Users, active: page.url.startsWith('/users'), feature: 'roles.manage' },
            { name: trans('nav.roles'), href: '/roles', icon: ShieldCheck, active: page.url.startsWith('/roles'), feature: 'roles.manage' },
            { name: trans('nav.audit_logs'), href: '/activity-logs', icon: ClipboardList, active: page.url.startsWith('/activity-logs'), feature: 'audit.logs' },
            { name: trans('nav.trash'), href: '/trash', icon: Trash2, active: page.url.startsWith('/trash'), feature: 'trash.access' },
            { name: trans('nav.settings'), href: '/settings', icon: Settings, active: page.url.startsWith('/settings'), feature: null },
        ]
    }
]);

const getUserRoleLabel = computed(() => {
    if (user.value.roles?.includes('admin')) return trans('nav.admin_role');
    if (user.value.roles?.includes('cashier')) return trans('nav.cashier_role');
    if (user.value.roles?.includes('accountant')) return trans('nav.accountant_role');
    if (user.value.roles?.includes('storekeeper')) return trans('nav.storekeeper_role');
    return trans('nav.user_role');
});
</script>

<template>
    <div class="h-screen flex bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 overflow-hidden font-sans selection:bg-amber-500 selection:text-white transition-colors duration-200" dir="rtl">
        <!-- Desktop Sidebar Navigation (Static in Flow on lg) -->
        <aside
            id="main-sidebar"
            class="hidden lg:flex h-full bg-white dark:bg-slate-900 border-l border-slate-200 dark:border-slate-800 flex-col shadow-2xl z-40 shrink-0 transition-[width] duration-300 ease-out select-none"
            :class="isSidebarCollapsed ? 'w-20' : 'w-72'"
        >
            <!-- Brand Header -->
            <div class="h-20 px-3.5 flex items-center justify-between border-b border-slate-200 dark:border-slate-800 bg-slate-50/90 dark:bg-slate-900/95 shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <Link
                        href="/"
                        class="rounded-2xl bg-white dark:bg-slate-800 p-1 flex items-center justify-center shadow-xs border border-slate-200 dark:border-slate-700/80 shrink-0 transition-transform duration-200 hover:scale-105 group"
                        :class="isSidebarCollapsed ? 'w-12 h-12' : 'w-14 h-14'"
                        :title="tenant?.name || 'سرور كوفي'"
                    >
                        <!-- Light Mode Logo -->
                        <img :src="logoLightSrc" alt="Logo" class="w-full h-full object-contain filter drop-shadow-xs group-hover:brightness-105 dark:hidden">
                        <!-- Dark Mode Logo -->
                        <img :src="logoDarkSrc" alt="Logo" class="w-full h-full object-contain filter drop-shadow-xs group-hover:brightness-105 hidden dark:block">
                    </Link>
                    <div
                        class="truncate min-w-0"
                        :class="{ 'lg:hidden': isSidebarCollapsed }"
                    >
                        <h1 class="font-black text-sm sm:text-base tracking-tight text-slate-900 dark:text-white font-tajawal line-clamp-1 leading-snug">
                            {{ tenant?.name || 'سرور كوفي' }}
                        </h1>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 font-bold truncate">{{ $t('nav.cloud_erp_subtitle') }}</p>
                    </div>
                </div>
            </div>

            <!-- Primary Action: + New Sale Invoice (F2) -->
            <FeatureGate feature="pos.access">
                <div class="p-3 border-b border-slate-200 dark:border-slate-800/80 shrink-0">
                    <Link
                        href="/pos"
                        class="w-full flex items-center justify-center gap-2 py-3 px-3.5 btn-primary-theme font-black rounded-2xl shadow-theme-primary transition-all duration-200 active:scale-95 font-tajawal cursor-pointer group"
                        :title="$t('nav.new_sale_invoice_btn')"
                    >
                        <Plus class="w-4.5 h-4.5 shrink-0 transition-transform group-hover:rotate-90 duration-300" />
                        <span :class="{ 'lg:hidden': isSidebarCollapsed }" class="truncate text-xs font-black">{{ $t('nav.new_sale_invoice_btn') }}</span>
                    </Link>
                </div>
            </FeatureGate>

            <!-- Nav Items (Scrollable) -->
            <nav class="flex-1 px-3 py-3 space-y-4 overflow-y-auto font-tajawal">
                <div v-for="(group, gIdx) in navigationGroups" :key="gIdx" class="space-y-1">
                    <!-- Group Header -->
                    <div
                        v-if="group.title"
                        class="pt-2 pb-1 px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 truncate"
                        :class="{ 'lg:hidden': isSidebarCollapsed }"
                    >
                        {{ group.title }}
                    </div>
                    <div v-if="group.title && isSidebarCollapsed" class="hidden lg:block my-1.5 border-t border-slate-200 dark:border-slate-800 mx-1"></div>

                    <!-- Group Items -->
                    <div class="space-y-1">
                        <template v-for="item in group.items" :key="item.href">
                            <FeatureGate :feature="item.feature">
                                <Link
                                    :href="item.href"
                                    class="flex items-center gap-3 px-3.5 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-150 group min-h-[44px] active:scale-98"
                                    :class="[
                                        item.active
                                            ? 'bg-theme-light text-theme-primary border border-theme-light shadow-xs font-black'
                                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800/80 dark:hover:text-white',
                                        isSidebarCollapsed ? 'lg:justify-center lg:px-2' : ''
                                    ]"
                                    :title="item.name"
                                >
                                    <component :is="item.icon" class="w-4.5 h-4.5 shrink-0 transition-transform group-hover:scale-110" />
                                    <span :class="{ 'lg:hidden': isSidebarCollapsed }" class="truncate flex-1">{{ item.name }}</span>
                                </Link>
                            </FeatureGate>
                        </template>
                    </div>
                </div>
            </nav>

            <!-- Sidebar Footer (Super Admin Button) -->
            <div v-if="isSuperAdmin" class="p-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/90 shrink-0">
                <a
                    href="/admin/super"
                    class="w-full flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl bg-purple-100 hover:bg-purple-200 dark:bg-purple-950/60 dark:hover:bg-purple-900 border border-purple-200 dark:border-purple-800/80 text-purple-700 dark:text-purple-300 text-xs font-bold transition font-tajawal shadow-xs"
                    :class="isSidebarCollapsed ? 'lg:justify-center lg:px-2' : ''"
                    :title="$t('nav.super_admin')"
                >
                    <Crown class="w-4 h-4 shrink-0 text-purple-600 dark:text-purple-400" />
                    <span :class="{ 'lg:hidden': isSidebarCollapsed }" class="truncate">{{ $t('nav.super_admin') }}</span>
                </a>
            </div>
        </aside>

        <!-- Mobile Drawer Backdrop Overlay -->
        <Transition name="fade">
            <div
                v-if="isSidebarOpen"
                @click="isSidebarOpen = false"
                class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs lg:hidden select-none"
            />
        </Transition>

        <!-- Mobile Drawer Sidebar (Smooth Spring Glide from Right) -->
        <Transition name="sidebar-drawer">
            <aside
                v-if="isSidebarOpen"
                class="fixed inset-y-0 right-0 w-72 max-w-[85vw] h-full bg-white dark:bg-slate-900 border-l border-slate-200 dark:border-slate-800 flex flex-col shadow-2xl z-50 select-none lg:hidden font-tajawal"
            >
                <!-- Brand Header -->
                <div class="h-20 px-3.5 flex items-center justify-between border-b border-slate-200 dark:border-slate-800 bg-slate-50/90 dark:bg-slate-900/95 shrink-0">
                    <div class="flex items-center gap-3 min-w-0">
                        <Link
                            href="/"
                            @click="isSidebarOpen = false"
                            class="w-12 h-12 rounded-2xl bg-white dark:bg-slate-800 p-1 flex items-center justify-center shadow-xs border border-slate-200 dark:border-slate-700/80 shrink-0"
                            :title="tenant?.name || 'سرور كوفي'"
                        >
                            <img :src="logoLightSrc" alt="Logo" class="w-full h-full object-contain filter drop-shadow-xs dark:hidden">
                            <img :src="logoDarkSrc" alt="Logo" class="w-full h-full object-contain filter drop-shadow-xs hidden dark:block">
                        </Link>
                        <div class="truncate min-w-0">
                            <h1 class="font-black text-sm tracking-tight text-slate-900 dark:text-white font-tajawal line-clamp-1 leading-snug">
                                {{ tenant?.name || 'سرور كوفي' }}
                            </h1>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 font-bold truncate">{{ $t('nav.cloud_erp_subtitle') }}</p>
                        </div>
                    </div>

                    <button
                        @click="isSidebarOpen = false"
                        type="button"
                        class="w-10 h-10 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center font-black text-sm transition active:scale-90 cursor-pointer shadow-xs"
                    >
                        ✕
                    </button>
                </div>

                <!-- Primary Action: + New Sale Invoice (F2) -->
                <FeatureGate feature="pos.access">
                    <div class="p-3 border-b border-slate-200 dark:border-slate-800/80 shrink-0">
                        <Link
                            href="/pos"
                            @click="isSidebarOpen = false"
                            class="w-full flex items-center justify-center gap-2 py-3 px-3.5 btn-primary-theme font-black rounded-2xl shadow-theme-primary transition-all duration-200 active:scale-95 font-tajawal cursor-pointer group"
                            :title="$t('nav.new_sale_invoice_btn')"
                        >
                            <Plus class="w-4.5 h-4.5 shrink-0" />
                            <span class="truncate text-xs font-black">{{ $t('nav.new_sale_invoice_btn') }}</span>
                        </Link>
                    </div>
                </FeatureGate>

                <!-- Nav Items (Scrollable) -->
                <nav class="flex-1 px-3 py-3 space-y-4 overflow-y-auto font-tajawal">
                    <div v-for="(group, gIdx) in navigationGroups" :key="gIdx" class="space-y-1">
                        <div
                            v-if="group.title"
                            class="pt-2 pb-1 px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 truncate"
                        >
                            {{ group.title }}
                        </div>

                        <div class="space-y-1">
                            <template v-for="item in group.items" :key="item.href">
                                <FeatureGate :feature="item.feature">
                                    <Link
                                        :href="item.href"
                                        @click="isSidebarOpen = false"
                                        class="flex items-center gap-3 px-3.5 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-150 group min-h-[44px] active:scale-98"
                                        :class="[
                                            item.active
                                                ? 'bg-theme-light text-theme-primary border border-theme-light shadow-xs font-black'
                                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800/80 dark:hover:text-white'
                                        ]"
                                        :title="item.name"
                                    >
                                        <component :is="item.icon" class="w-4.5 h-4.5 shrink-0" />
                                        <span class="truncate flex-1">{{ item.name }}</span>
                                    </Link>
                                </FeatureGate>
                            </template>
                        </div>
                    </div>
                </nav>

                <!-- Sidebar Footer (Super Admin Button) -->
                <div v-if="isSuperAdmin" class="p-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/90 shrink-0 pb-safe">
                    <a
                        href="/admin/super"
                        class="w-full flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl bg-purple-100 hover:bg-purple-200 dark:bg-purple-950/60 dark:hover:bg-purple-900 border border-purple-200 dark:border-purple-800/80 text-purple-700 dark:text-purple-300 text-xs font-bold transition font-tajawal shadow-xs"
                        :title="$t('nav.super_admin')"
                    >
                        <Crown class="w-4 h-4 shrink-0 text-purple-600 dark:text-purple-400" />
                        <span class="truncate">{{ $t('nav.super_admin') }}</span>
                    </a>
                </div>
            </aside>
        </Transition>

        <!-- Main Wrapper: Impersonation Banner + Header + Page Content -->
        <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden bg-slate-50 dark:bg-slate-950 transition-colors duration-200">
            <!-- Super Admin Impersonation Alert Banner -->
            <div v-if="$page.props.auth?.is_impersonating" class="bg-gradient-to-r from-purple-900 via-indigo-950 to-purple-900 text-white px-4 py-2 flex flex-wrap items-center justify-between gap-2 text-xs font-bold font-tajawal z-40 border-b border-purple-500/30 shadow-md shrink-0">
                <div class="flex items-center gap-2">
                    <Crown class="w-4 h-4 text-amber-400 animate-pulse shrink-0" />
                    <span>أنت تتصفح متجر <strong class="text-amber-400 font-black">({{ tenant?.name }})</strong> حالياً كمسؤول من لوحة السوبر أدمن المركزية</span>
                </div>
                <button
                    @click="router.post('/impersonate/leave')"
                    type="button"
                    class="h-7 px-3 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs transition transform active:scale-95 cursor-pointer shadow-xs flex items-center gap-1.5"
                >
                    <X class="w-3.5 h-3.5" />
                    <span>العودة للوحة السوبر أدمن المركزية</span>
                </button>
            </div>

            <!-- Top Header (h-16) -->
            <header class="h-16 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-4 sm:px-6 sticky top-0 z-30 shrink-0 shadow-xs">
                <!-- Start Section (in RTL: Right) -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <!-- Mobile Hamburger -->
                    <button
                        @click="isSidebarOpen = true"
                        type="button"
                        class="lg:hidden p-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800 transition cursor-pointer"
                    >
                        <Menu class="w-5 h-5" />
                    </button>

                    <!-- Mobile Mini Brand Logo -->
                    <div class="flex lg:hidden items-center gap-2">
                        <Link href="/" class="w-8 h-8 rounded-xl bg-white dark:bg-slate-800 p-0.5 border border-slate-200 dark:border-slate-700 shadow-xs flex items-center justify-center">
                            <img :src="logoLightSrc" alt="Logo" class="w-full h-full object-contain dark:hidden">
                            <img :src="logoDarkSrc" alt="Logo" class="w-full h-full object-contain hidden dark:block">
                        </Link>
                    </div>

                    <!-- Desktop Collapse Button -->
                    <button
                        @click="toggleSidebar()"
                        type="button"
                        class="hidden lg:flex p-2 rounded-xl text-slate-500 hover:text-theme-primary hover:bg-slate-100 dark:text-slate-400 dark:hover:text-theme-primary dark:hover:bg-slate-800 transition cursor-pointer"
                        :title="$t('nav.toggle_sidebar')"
                    >
                        <PanelRightClose v-if="!isSidebarCollapsed" class="w-5 h-5 transition-transform" />
                        <PanelRightOpen v-else class="w-5 h-5 transition-transform" />
                    </button>

                    <!-- Live Clock & Date -->
                    <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/60 text-xs font-bold font-tajawal text-slate-700 dark:text-slate-300">
                        <Clock class="w-3.5 h-3.5 text-theme-primary shrink-0" />
                        <span class="text-slate-500 dark:text-slate-400 hidden xl:inline">{{ currentDate }}</span>
                        <span class="text-slate-300 dark:text-slate-600 hidden xl:inline">|</span>
                        <span class="text-theme-primary font-mono font-bold">{{ currentTime }}</span>
                    </div>
                </div>

                <!-- End Section (in RTL: Left) -->
                <div class="flex items-center gap-1.5 sm:gap-2.5">
                    <!-- Single Interactive Store Switcher Button -->
                    <button
                        @click="showStoreModal = true"
                        type="button"
                        class="h-8.5 sm:h-9 px-2 sm:px-3 rounded-xl bg-slate-100 hover:bg-slate-200/80 dark:bg-slate-800/90 dark:hover:bg-slate-700/80 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5 sm:gap-2 transition cursor-pointer font-tajawal shadow-xs max-w-[120px] sm:max-w-[170px]"
                        :title="$t('nav.switch_store') || 'تبديل الفرع'"
                    >
                        <span class="w-2 h-2 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-pulse shrink-0"></span>
                        <Store class="w-3.5 h-3.5 text-slate-600 dark:text-slate-300 shrink-0" />
                        <span class="truncate text-start text-[11px] sm:text-xs">{{ activeStore?.name || $t('common.main_store_default') }}</span>
                        <ChevronDown class="w-3 h-3 text-slate-400 shrink-0" />
                    </button>

                    <!-- Shift Status Indicator (Compact on Mobile) -->
                    <Link
                        href="/daily-journal"
                        class="h-8.5 sm:h-9 px-2 sm:px-3 rounded-xl border text-xs font-bold flex items-center gap-1.5 transition font-tajawal shadow-xs shrink-0"
                        :class="activeShift ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20' : 'bg-rose-500/10 border-rose-500/30 text-rose-600 dark:text-rose-400 hover:bg-rose-500/20'"
                        :title="activeShift ? 'الوردية مفتوحة ونشطة' : 'لا توجد وردية مفتوحة حالياً'"
                    >
                        <span class="w-2 h-2 rounded-full shrink-0" :class="activeShift ? 'bg-emerald-500 dark:bg-emerald-400 animate-pulse' : 'bg-rose-500 dark:bg-rose-400'"></span>
                        <Clock class="w-3.5 h-3.5 shrink-0" />
                        <span class="hidden md:inline">
                            {{ activeShift ? $t('nav.active_shift') : $t('nav.closed_shift') }}
                        </span>
                    </Link>

                    <!-- Quick POS Fast Action Button (Desktop Only) -->
                    <FeatureGate feature="pos.access">
                        <Link
                            href="/pos"
                            class="hidden md:flex h-9 px-3 rounded-xl btn-primary-theme font-black text-xs items-center gap-1.5 transition transform active:scale-95 cursor-pointer font-tajawal shrink-0 shadow-theme-primary"
                        >
                            <Zap class="w-3.5 h-3.5 fill-current" />
                            <span class="hidden sm:inline">{{ $t('nav.pos_fast') }}</span>
                            <span class="px-1.5 py-0.5 rounded bg-black/25 text-white text-[10px] font-mono">F2</span>
                        </Link>
                    </FeatureGate>

                    <!-- Notification Center Dropdown (Desktop Only) -->
                    <div class="hidden md:block relative" @click.stop>
                        <button
                            @click="showNotifications = !showNotifications; showUserMenu = false"
                            type="button"
                            class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-transparent flex items-center justify-center transition relative cursor-pointer"
                            :title="$t('nav.notifications_title')"
                        >
                            <Bell class="w-4 h-4" />
                            <span
                                v-if="notifications.length > 0"
                                class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-rose-500 text-white font-black text-[9px] flex items-center justify-center animate-pulse"
                            >
                                {{ notifications.length }}
                            </span>
                        </button>

                        <!-- Notifications Dropdown Panel (Smooth Pop Animation) -->
                        <Transition name="dropdown-pop">
                            <div
                                v-if="showNotifications"
                                class="absolute left-0 mt-2 w-80 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl p-3 z-50 space-y-2 font-tajawal text-slate-900 dark:text-slate-100"
                            >
                                <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-slate-800 px-1">
                                    <span class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-1.5">
                                        <Bell class="w-3.5 h-3.5 text-theme-primary" />
                                        <span>{{ $t('nav.live_notifications_center') }}</span>
                                    </span>
                                    <span class="text-[10px] text-theme-primary font-bold">
                                        {{ notifications.length }} {{ $t('nav.notifications_count') }}
                                    </span>
                                </div>

                                <div class="space-y-2 max-h-72 overflow-y-auto">
                                    <div
                                        v-for="(n, nIdx) in notifications"
                                        :key="nIdx"
                                        class="p-2.5 rounded-2xl bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800/80 space-y-1 hover:border-slate-300 dark:hover:border-slate-700 transition"
                                    >
                                        <div class="flex items-center gap-2">
                                            <Bell class="w-3.5 h-3.5 text-theme-primary shrink-0" />
                                            <span class="text-xs font-black text-slate-900 dark:text-white">{{ n.title }}</span>
                                        </div>
                                        <p class="text-[11px] text-slate-600 dark:text-slate-400 leading-snug">{{ n.description }}</p>
                                        <div class="pt-1 flex justify-end">
                                            <Link
                                                :href="n.link"
                                                @click="showNotifications = false"
                                                class="text-[10px] font-bold text-theme-primary hover:underline transition"
                                            >
                                                {{ n.link_label }} ←
                                            </Link>
                                        </div>
                                    </div>

                                    <div v-if="notifications.length === 0" class="py-6 text-center text-xs text-slate-400 font-bold">
                                        {{ $t('nav.no_urgent_notifications') }}
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <!-- Theme Toggle Switch Button -->
                    <button
                        @click="toggleTheme"
                        type="button"
                        class="w-8.5 h-8.5 sm:w-9 sm:h-9 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800/90 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 flex items-center justify-center transition cursor-pointer shrink-0 shadow-xs hover:text-theme-primary"
                        :title="currentTheme === 'dark' ? 'التحويل للوضع النهاري (Light)' : 'التحويل للوضع الليلي (Dark)'"
                    >
                        <Sun v-if="currentTheme === 'dark'" class="w-4 h-4 text-amber-400" />
                        <Moon v-else class="w-4 h-4 text-slate-700" />
                    </button>

                    <!-- User Profile & Dropdown -->
                    <div class="relative" @click.stop>
                        <button
                            @click="showUserMenu = !showUserMenu"
                            type="button"
                            class="flex items-center gap-2 p-1.5 pr-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800/80 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 transition cursor-pointer text-slate-800 dark:text-slate-200"
                        >
                            <div class="w-7 h-7 rounded-lg bg-theme-primary text-white flex items-center justify-center text-xs font-black shadow-theme-primary shrink-0">
                                {{ user.name?.charAt(0) || 'U' }}
                            </div>
                            <div class="hidden xl:block text-right">
                                <p class="text-xs font-black truncate max-w-[100px] text-slate-900 dark:text-white leading-tight">{{ user.name }}</p>
                                <p class="text-[10px] text-slate-500 dark:text-slate-400 truncate">{{ user.roles?.[0] || $t('common.user') }}</p>
                            </div>
                            <ChevronDown class="w-3.5 h-3.5 text-slate-400 hidden sm:inline shrink-0" />
                        </button>

                        <!-- User Profile Dropdown Menu (Smooth Pop Animation) -->
                        <Transition name="dropdown-pop">
                            <div
                                v-if="showUserMenu"
                                class="absolute left-0 mt-2 w-52 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-2 z-50 space-y-1 font-tajawal text-slate-800 dark:text-slate-200"
                            >
                                <div class="p-2 border-b border-slate-100 dark:border-slate-800">
                                    <p class="text-xs font-black text-slate-900 dark:text-white truncate">{{ user.name }}</p>
                                    <p class="text-[10px] text-slate-400 truncate">{{ user.email || user.phone }}</p>
                                </div>

                                <Link
                                    href="/settings"
                                    class="flex items-center gap-2 px-3 py-2 text-xs font-bold rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                >
                                    <Settings class="w-3.5 h-3.5" />
                                    <span>{{ $t('nav.settings') }}</span>
                                </Link>

                                <button
                                    @click="logout"
                                    type="button"
                                    class="w-full flex items-center gap-2 px-3 py-2 text-xs font-bold rounded-xl text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer"
                                >
                                    <LogOut class="w-3.5 h-3.5" />
                                    <span>{{ $t('nav.logout') }}</span>
                                </button>
                            </div>
                        </Transition>
                    </div>
                </div>
            </header>

            <!-- Main Dynamic Page Content Container -->
            <main class="flex-1 p-3.5 sm:p-5 lg:p-8 space-y-4 sm:space-y-6 overflow-y-auto pb-28 lg:pb-8">
                <!-- Global Flash Messages Notification -->
                <Transition name="fade">
                    <div v-if="$page.props.flash?.success" class="p-3.5 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs font-black flex items-center gap-2.5 font-tajawal shadow-lg shadow-emerald-500/10">
                        <CheckCircle2 class="w-4 h-4 shrink-0" />
                        <span>{{ $page.props.flash.success }}</span>
                    </div>
                </Transition>
                <Transition name="fade">
                    <div v-if="$page.props.flash?.error" class="p-3.5 rounded-2xl bg-rose-500/15 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-xs font-black flex items-center gap-2.5 font-tajawal shadow-lg shadow-rose-500/10">
                        <AlertTriangle class="w-4 h-4 shrink-0" />
                        <span>{{ $page.props.flash.error }}</span>
                    </div>
                </Transition>

                <!-- Native Page Slide-In Animated Wrapper -->
                <div class="page-content-enter">
                    <slot />
                </div>
            </main>

            <!-- Fixed Mobile Bottom Navigation Bar (Visible only on screens < lg) -->
            <MobileBottomNav :active-shift="activeShift" @open-drawer="isSidebarOpen = true" />
        </div>

        <!-- Mobile Notifications Bottom Sheet Modal (Smooth Slide Up) -->
        <Transition name="sheet-slide">
            <div
                v-if="showNotificationsSheet"
                @click="showNotificationsSheet = false"
                class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4 font-tajawal lg:hidden"
            >
                <div
                    @click.stop
                    class="w-full max-w-lg bg-white dark:bg-slate-900 border-t sm:border border-slate-200 dark:border-slate-800 rounded-t-3xl sm:rounded-3xl p-5 pb-safe shadow-2xl space-y-4 max-h-[85vh] flex flex-col text-slate-900 dark:text-white"
                >
                    <!-- Native Drag Handle -->
                    <div class="w-12 h-1.5 rounded-full bg-slate-300 dark:bg-slate-700 mx-auto -mt-1"></div>

                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                        <div class="flex items-center gap-2">
                            <Bell class="w-5 h-5 text-theme-primary" />
                            <h3 class="font-black text-sm">{{ $t('nav.live_notifications_center') }}</h3>
                            <span v-if="notifications.length > 0" class="px-2 py-0.5 rounded-full bg-rose-500 text-white font-mono text-[10px] font-black">
                                {{ notifications.length }}
                            </span>
                        </div>
                        <button @click="showNotificationsSheet = false" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-500 dark:text-slate-400 text-xs flex items-center justify-center cursor-pointer">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto space-y-2.5 pr-0.5">
                        <div
                            v-for="(n, nIdx) in notifications"
                            :key="nIdx"
                            class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800/80 space-y-2 hover:border-slate-300 dark:hover:border-slate-700 transition"
                        >
                            <div class="flex items-center gap-2.5">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="n.type === 'danger' ? 'bg-rose-500 animate-pulse' : (n.type === 'warning' ? 'bg-amber-500' : 'bg-blue-500')"></span>
                                <span class="text-xs font-black text-slate-900 dark:text-white">{{ n.title }}</span>
                            </div>
                            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{{ n.description }}</p>
                            <div class="pt-1 flex justify-end">
                                <Link
                                    :href="n.link"
                                    @click="showNotificationsSheet = false"
                                    class="px-3 py-1.5 rounded-xl bg-theme-light border border-theme-light text-theme-primary text-xs font-bold transition flex items-center gap-1 hover:brightness-110"
                                >
                                    <span>{{ n.link_label }}</span>
                                    <span>←</span>
                                </Link>
                            </div>
                        </div>

                        <div v-if="notifications.length === 0" class="py-12 text-center space-y-2">
                            <Bell class="w-10 h-10 mx-auto text-slate-300 dark:text-slate-700" />
                            <p class="text-xs font-bold text-slate-400">{{ $t('nav.no_urgent_notifications') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Store / Van Switcher Modal (Smooth Center Pop) -->
        <Transition name="modal-zoom">
            <div
                v-if="showStoreModal"
                @click="showStoreModal = false"
                class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4"
            >
                <div @click.stop class="w-full max-w-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-2xl space-y-3 font-tajawal text-slate-900 dark:text-white">
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2.5">
                        <h3 class="font-black text-sm">{{ $t('nav.select_store_modal_title') }}</h3>
                        <button @click="showStoreModal = false" class="w-7 h-7 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-500 dark:text-slate-400 text-xs dark:hover:text-white transition flex items-center justify-center cursor-pointer">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="space-y-2 max-h-64 overflow-y-auto pt-1">
                        <div
                            v-for="store in stores"
                            :key="store.id"
                            @click="switchStore(store.id)"
                            class="p-3 rounded-2xl border flex items-center justify-between cursor-pointer transition"
                            :class="activeStore?.id === store.id ? 'bg-theme-light border-theme-primary text-theme-primary font-black' : 'bg-slate-50 dark:bg-slate-800/40 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"
                        >
                            <div class="flex items-center gap-2.5">
                                <Truck v-if="store.type === 'van'" class="w-4 h-4 text-theme-primary" />
                                <Store v-else class="w-4 h-4 text-theme-primary" />
                                <div>
                                    <p class="text-xs font-bold">{{ store.name }}</p>
                                    <p class="text-[10px] text-slate-400 dark:text-slate-500 font-sans">{{ store.type === 'van' ? $t('nav.van_store') : $t('nav.branch_store') }}</p>
                                </div>
                            </div>
                            <CheckCircle2 v-if="activeStore?.id === store.id" class="w-4 h-4 text-theme-primary shrink-0" />
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
