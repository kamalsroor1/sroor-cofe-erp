<script setup>
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { trans } from '../helpers/trans';

const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();

const user = computed(() => authStore.user || {});
const mobileMenuOpen = ref(false);

const navItems = computed(() => [
    { name: trans('super.dashboard'), href: '/super-admin', icon: '📊', active: route.path === '/super-admin' },
    { name: trans('super.tenants'), href: '/super-admin/tenants', icon: '🏪', active: route.path.startsWith('/super-admin/tenants') },
    { name: trans('super.plans'), href: '/super-admin/plans', icon: '💼', active: route.path.startsWith('/super-admin/plans') },
    { name: trans('super.app_versions'), href: '/super-admin/app-versions', icon: '📱', active: route.path.startsWith('/super-admin/app-versions') },
]);

const handleLogout = async () => {
    await authStore.logout();
    router.push({ name: 'login' });
};
</script>

<template>
    <div class="min-h-screen bg-white dark:bg-slate-950 text-slate-100 flex flex-col antialiased selection:bg-indigo-500 selection:text-white font-tajawal" dir="rtl">
        <!-- Top Navbar -->
        <header class="h-16 border-b border-indigo-900/40 bg-slate-900/90 backdrop-blur-md sticky top-0 z-40 px-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <!-- Mobile Hamburger Toggle -->
                <button
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    type="button"
                    class="md:hidden w-10 h-10 rounded-2xl bg-slate-800 text-slate-900 dark:text-slate-200 flex items-center justify-center text-lg active:scale-90 transition cursor-pointer shadow-xs border border-slate-700"
                >
                    {{ mobileMenuOpen ? '✕' : '☰' }}
                </button>

                <router-link to="/super-admin" class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white font-black text-xl flex items-center justify-center shadow-lg shadow-indigo-500/20 shrink-0">
                        🛡️
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-black text-sm tracking-tight text-white">
                                {{ $t('super.platform_title') }}
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 hidden sm:inline-block">
                                {{ $t('super.central_platform') }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 font-bold hidden sm:block">
                            {{ $t('super.platform_subtitle') }}
                        </p>
                    </div>
                </router-link>
            </div>

            <!-- Top Actions -->
            <div class="flex items-center gap-2 sm:gap-3">
                <router-link
                    to="/"
                    class="h-10 px-3.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5 transition active:scale-95 border border-slate-700 shadow-xs cursor-pointer"
                >
                    <span>☕</span>
                    <span class="hidden sm:inline">{{ $t('super.back_to_pos') }}</span>
                </router-link>

                <div class="text-left hidden md:block pl-2 border-r border-slate-200 dark:border-slate-800 pr-3">
                    <div class="text-xs font-black text-white">{{ user?.name || $t('super.platform_admin') }}</div>
                    <div class="text-[10px] text-indigo-400 font-mono font-bold">SUPER ADMIN</div>
                </div>

                <button
                    @click="handleLogout"
                    type="button"
                    class="h-10 px-3.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-xs font-bold text-rose-400 flex items-center gap-1.5 transition cursor-pointer active:scale-95"
                    :title="$t('super.logout_title')"
                >
                    <span>🚪</span>
                    <span class="hidden sm:inline">{{ $t('nav.logout') }}</span>
                </button>
            </div>
        </header>

        <!-- Mobile Drawer Overlay & Sidebar (Smooth Native Transitions) -->
        <Transition name="fade">
            <div
                v-if="mobileMenuOpen"
                @click="mobileMenuOpen = false"
                class="fixed inset-0 z-50 bg-white dark:bg-slate-950/80 backdrop-blur-xs md:hidden"
            />
        </Transition>

        <Transition name="sidebar-drawer">
            <aside
                v-if="mobileMenuOpen"
                class="fixed inset-y-0 right-0 z-50 w-72 max-w-[85vw] h-full bg-slate-900 border-l border-indigo-900/40 p-4 flex flex-col space-y-6 shadow-2xl md:hidden font-tajawal"
            >
                <div class="flex items-center justify-between border-b border-indigo-900/40 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-sm">🛡️</div>
                        <span class="font-black text-xs text-white">{{ $t('super.platform_title') }}</span>
                    </div>
                    <button @click="mobileMenuOpen = false" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 text-xs flex items-center justify-center cursor-pointer">✕</button>
                </div>

                <div class="space-y-1.5">
                    <router-link
                        v-for="(item, idx) in navItems"
                        :key="idx"
                        :to="item.href"
                        @click="mobileMenuOpen = false"
                        class="flex items-center gap-3 px-3.5 py-3 rounded-2xl text-xs font-bold transition active:scale-98"
                        :class="item.active ? 'bg-indigo-600 text-white font-black shadow-lg shadow-indigo-600/25' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-800 hover:text-white'"
                    >
                        <span class="text-lg">{{ item.icon }}</span>
                        <span>{{ item.name }}</span>
                    </router-link>
                </div>

                <div class="mt-auto p-4 rounded-2xl bg-gradient-to-br from-indigo-950/60 to-slate-900 border border-indigo-800/40 text-xs space-y-2 font-tajawal">
                    <div class="font-black text-white flex items-center gap-1.5 text-xs">
                        <span>⚡</span>
                        <span>{{ $t('super.multi_db_engine_title') }}</span>
                    </div>
                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        {{ $t('super.multi_db_engine_desc') }}
                    </p>
                </div>
            </aside>
        </Transition>

        <!-- Body Shell -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Desktop Sidebar (Hidden on Mobile) -->
            <aside class="hidden md:flex w-64 bg-slate-900/60 border-l border-indigo-900/30 flex-col p-4 space-y-6 shrink-0 font-tajawal">
                <div class="space-y-1">
                    <div class="px-3 text-[11px] font-black tracking-wider text-indigo-400 uppercase mb-2">
                        {{ $t('super.platform_title') }}
                    </div>

                    <router-link
                        v-for="(item, idx) in navItems"
                        :key="idx"
                        :to="item.href"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition group"
                        :class="item.active ? 'bg-indigo-600 text-white font-black shadow-lg shadow-indigo-600/25' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-800 hover:text-white'"
                    >
                        <span class="text-base">{{ item.icon }}</span>
                        <span>{{ item.name }}</span>
                    </router-link>
                </div>

                <div class="mt-auto p-4 rounded-2xl bg-gradient-to-br from-indigo-950/60 to-slate-900 border border-indigo-800/40 text-xs space-y-2 font-tajawal">
                    <div class="font-black text-white flex items-center gap-1.5">
                        <span>⚡</span>
                        <span>{{ $t('super.multi_db_arch_title') }}</span>
                    </div>
                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        {{ $t('super.multi_db_arch_desc') }}
                    </p>
                </div>
            </aside>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-3.5 sm:p-6 lg:p-8 space-y-6">
                <slot />
            </main>
        </div>
    </div>
</template>
