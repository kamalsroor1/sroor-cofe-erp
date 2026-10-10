import { ref, computed, onMounted, onUnmounted, toRaw } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useTrans } from './useTrans';

const STORAGE_KEY_ADVANCED = 'sroor_settings_show_advanced';

export function useSettingsShell(options = {}) {
    const { form, initialForm } = options;
    const { t } = useTrans();
    const authStore = useAuthStore();

    // 1. Mobile Responsiveness (breakpoint lg = 1024px)
    const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1200);
    const isMobileView = computed(() => windowWidth.value < 1024);

    // 2. Active Tab Tracking
    // Desktop default: 'general'. Mobile default: null (Drill-down Hub view)
    const selectedTab = ref(isMobileView.value ? null : 'general');

    const selectTab = (tabId) => {
        selectedTab.value = tabId;
    };

    const backToHub = () => {
        selectedTab.value = null;
    };

    const onResize = () => {
        if (typeof window === 'undefined') return;
        windowWidth.value = window.innerWidth;
        if (!isMobileView.value && !selectedTab.value) {
            selectedTab.value = 'general';
        }
    };

    // 3. Advanced Settings Toggle (persisted per user in localStorage)
    const showAdvanced = ref(
        typeof localStorage !== 'undefined' ? localStorage.getItem(STORAGE_KEY_ADVANCED) === 'true' : false
    );

    const toggleAdvanced = () => {
        showAdvanced.value = !showAdvanced.value;
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem(STORAGE_KEY_ADVANCED, showAdvanced.value ? 'true' : 'false');
        }
    };

    // 4. Permissions Checking
    // User has settings.manage (or admin / roles.manage). If not, canManage = false (read-only mode).
    const canManage = computed(() => {
        if (!authStore.user) return false;
        if (authStore.isAdmin) return true;
        return authStore.can('settings.manage') || authStore.can('roles.manage');
    });

    // 5. Unsaved Changes Dirty Tracking
    const hasUnsavedChanges = computed(() => {
        if (!form || !initialForm) return false;
        const current = toRaw(form.value || form);
        const baseline = toRaw(initialForm.value || initialForm);
        if (!current || !baseline) return false;

        const keys = Object.keys(baseline);
        return keys.some((k) => {
            const curVal = current[k];
            const baseVal = baseline[k];
            if (curVal === undefined || curVal === null) {
                return baseVal !== curVal;
            }
            return JSON.stringify(curVal) !== JSON.stringify(baseVal);
        });
    });

    // Route leave guard to prevent losing unsaved settings
    onBeforeRouteLeave((to, from, next) => {
        if (hasUnsavedChanges.value) {
            const confirmed = window.confirm(t('settings.unsaved_changes_warning'));
            if (confirmed) {
                next();
            } else {
                next(false);
            }
        } else {
            next();
        }
    });

    // Window beforeunload warning
    const onBeforeUnload = (e) => {
        if (hasUnsavedChanges.value) {
            e.preventDefault();
            e.returnValue = '';
        }
    };

    // 6. Section Title & Subtitle based on active tab or hub
    const currentTitle = computed(() => {
        if (!selectedTab.value && isMobileView.value) return t('settings.hub_title');
        const titles = {
            general: t('settings.tab_general'),
            branding: t('settings.tab_branding'),
            printing: t('settings.sec_printing_label'),
            inventory: t('settings.tab_inventory'),
            notifications: t('settings.tab_notifications'),
            device: t('settings.tab_device'),
        };
        return titles[selectedTab.value] || t('settings.title');
    });

    const currentSubtitle = computed(() => {
        if (!selectedTab.value && isMobileView.value) return t('settings.hub_subtitle');
        const subs = {
            general: t('settings.tab_general_sub'),
            branding: t('settings.branding_section_sub'),
            printing: t('settings.tab_printing_sub'),
            inventory: t('settings.tab_inventory_sub'),
            notifications: t('settings.tab_notifications_sub'),
            device: t('settings.tab_device_sub'),
        };
        return subs[selectedTab.value] || '';
    });

    onMounted(() => {
        if (typeof window !== 'undefined') {
            window.addEventListener('resize', onResize);
            window.addEventListener('beforeunload', onBeforeUnload);
        }
    });

    onUnmounted(() => {
        if (typeof window !== 'undefined') {
            window.removeEventListener('resize', onResize);
            window.removeEventListener('beforeunload', onBeforeUnload);
        }
    });

    return {
        selectedTab,
        selectTab,
        backToHub,
        isMobileView,
        showAdvanced,
        toggleAdvanced,
        canManage,
        hasUnsavedChanges,
        currentTitle,
        currentSubtitle,
    };
}
