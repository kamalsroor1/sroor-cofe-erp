import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import router from '../router';

export const useTabsStore = defineStore('tabs', () => {
    // 🏠 Default initial pinned tab (Dashboard)
    const initialTab = {
        id: 'dashboard',
        title: 'لوحة التحكم',
        path: '/',
        fullPath: '/',
        name: 'dashboard',
        icon: 'LayoutDashboard',
        closable: false,
        isPinned: true,
    };

    const tabs = ref([initialTab]);
    const activeTabId = ref('dashboard');
    const maxTabs = ref(12);

    // Array of component names to be cached in <KeepAlive>
    const cachedViews = computed(() => {
        return tabs.value
            .map((t) => t.name)
            .filter(Boolean);
    });

    /**
     * Helper to get an appropriate icon for a route
     */
    const getRouteIcon = (route) => {
        const path = route.path || '';
        if (path === '/' || path.startsWith('/dashboard')) return 'LayoutDashboard';
        if (path.startsWith('/pos')) return 'Zap';
        if (path.startsWith('/invoices')) return 'FileText';
        if (path.startsWith('/purchases')) return 'ShoppingCart';
        if (path.startsWith('/returns')) return 'RotateCcw';
        if (path.startsWith('/daily-journal')) return 'Wallet';
        if (path.startsWith('/expenses')) return 'Receipt';
        if (path.startsWith('/items') || path.startsWith('/categories')) return 'Package';
        if (path.startsWith('/customers')) return 'Users';
        if (path.startsWith('/suppliers')) return 'Building2';
        if (path.startsWith('/stores')) return 'Store';
        if (path.startsWith('/stock-transfers')) return 'ArrowLeftRight';
        if (path.startsWith('/reports')) return 'BarChart3';
        if (path.startsWith('/settings')) return 'Sliders';
        if (path.startsWith('/coffee-blender')) return 'Coffee';
        return 'FileText';
    };

    /**
     * Helper to get tab title from route meta or translation
     */
    const getRouteTitle = (route) => {
        return route.meta?.title || route.name || 'شاشة';
    };

    /**
     * Add or switch to a tab based on a route
     */
    const addTab = (route) => {
        if (!route || !route.path) return;

        // Skip non-tab routes like login or standalone print
        if (route.meta?.guestOnly || route.meta?.isPrintView || route.meta?.layout === 'blank') {
            return;
        }

        const fullPath = route.fullPath || route.path;
        const existingTab = tabs.value.find((t) => t.fullPath === fullPath);

        if (existingTab) {
            activeTabId.value = existingTab.id;
            return;
        }

        // If max tabs reached, remove the oldest non-pinned tab
        if (tabs.value.length >= maxTabs.value) {
            const firstClosableIndex = tabs.value.findIndex((t) => t.closable);
            if (firstClosableIndex !== -1) {
                tabs.value.splice(firstClosableIndex, 1);
            }
        }

        const newTab = {
            id: `tab_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`,
            title: getRouteTitle(route),
            path: route.path,
            fullPath: fullPath,
            name: route.name || route.matched?.[0]?.components?.default?.name,
            icon: getRouteIcon(route),
            closable: route.path !== '/' && route.path !== '/dashboard',
            isPinned: route.path === '/' || route.path === '/dashboard',
        };

        tabs.value.push(newTab);
        activeTabId.value = newTab.id;
    };

    /**
     * Switch to a tab and navigate router
     */
    const selectTab = (tab) => {
        if (!tab) return;
        activeTabId.value = tab.id;
        if (router.currentRoute.value.fullPath !== tab.fullPath) {
            router.push(tab.fullPath);
        }
    };

    /**
     * Remove a tab by ID
     */
    const removeTab = (tabId) => {
        const tabIndex = tabs.value.findIndex((t) => t.id === tabId);
        if (tabIndex === -1) return;

        const tabToRemove = tabs.value[tabIndex];
        if (!tabToRemove.closable) return;

        const isCurrentActive = activeTabId.value === tabId;
        tabs.value.splice(tabIndex, 1);

        if (isCurrentActive) {
            // Switch to previous tab, or next if was first
            const nextTab = tabs.value[Math.max(0, tabIndex - 1)];
            if (nextTab) {
                selectTab(nextTab);
            }
        }
    };

    /**
     * Close all other tabs except the specified one and pinned tabs
     */
    const closeOtherTabs = (tabId) => {
        tabs.value = tabs.value.filter((t) => !t.closable || t.id === tabId);
        const currentTab = tabs.value.find((t) => t.id === tabId);
        if (currentTab) {
            selectTab(currentTab);
        }
    };

    /**
     * Close all closable tabs and return to Dashboard
     */
    const closeAllTabs = () => {
        tabs.value = tabs.value.filter((t) => !t.closable);
        const defaultTab = tabs.value[0] || initialTab;
        selectTab(defaultTab);
    };

    /**
     * Refresh current tab by momentarily removing from cached views
     */
    const refreshTab = (tabId) => {
        const tab = tabs.value.find((t) => t.id === tabId);
        if (!tab) return;

        const originalName = tab.name;
        tab.name = '';
        router.replace({ path: '/redirect' + tab.fullPath }).catch(() => {
            router.replace(tab.fullPath);
        });

        setTimeout(() => {
            tab.name = originalName;
        }, 100);
    };

    return {
        tabs,
        activeTabId,
        maxTabs,
        cachedViews,
        addTab,
        selectTab,
        removeTab,
        closeOtherTabs,
        closeAllTabs,
        refreshTab,
    };
});
