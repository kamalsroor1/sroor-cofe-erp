import { computed, onMounted, ref } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useAppConfigStore } from '../stores/appConfig';
import { useRoutePermission } from './useRoutePermission';

const STEPS = [
    { key: 'company_info', to: '/settings', helpSlug: 'getting-started' },
    { key: 'branch_setup', to: '/stores', helpSlug: 'getting-started' },
    { key: 'categories', to: '/categories', helpSlug: 'items' },
    { key: 'items', to: '/items', helpSlug: 'items' },
    { key: 'users', to: '/users', helpSlug: 'users-roles' },
    { key: 'shift', to: '/pos', helpSlug: 'treasury-shifts' },
    { key: 'first_sale', to: '/pos', helpSlug: 'pos' },
];

const dismissKey = (tenantKey, userId) => `setup_checklist_dismissed_${tenantKey ?? 'tenant'}_${userId ?? 'anon'}`;

const readDismissed = (key) => {
    try {
        return localStorage.getItem(key) === '1';
    } catch {
        return false;
    }
};

/**
 * "Getting started" checklist state for the dashboard: steps the user can open, progress and dismissal.
 */
export function useSetupChecklist() {
    const authStore = useAuthStore();
    const appConfigStore = useAppConfigStore();
    const { canAccessPath } = useRoutePermission();

    const storageKey = computed(() =>
        dismissKey(appConfigStore.tenant?.id ?? appConfigStore.tenant?.slug, authStore.user?.id)
    );
    const dismissed = ref(readDismissed(storageKey.value));

    const steps = computed(() => {
        const data = appConfigStore.setupProgress;
        if (!data) return [];
        return STEPS.filter((step) => canAccessPath(step.to)).map((step) => ({
            ...step,
            done: !!data[step.key],
        }));
    });

    // Progress is computed from the steps this user can actually open, not the server totals.
    const progress = computed(() => {
        const total = steps.value.length;
        const completed = steps.value.filter((step) => step.done).length;
        return { completed, total, percent: total ? Math.round((completed / total) * 100) : 0 };
    });

    const isVisible = computed(
        () =>
            !!appConfigStore.setupProgress &&
            !dismissed.value &&
            progress.value.total > 0 &&
            progress.value.completed < progress.value.total
    );

    const dismiss = () => {
        dismissed.value = true;
        try {
            localStorage.setItem(storageKey.value, '1');
        } catch {
            // Storage unavailable (private mode / quota): dismissal lasts for this session only.
        }
    };

    onMounted(() => {
        // The boot request carries fresh progress for the first visit; later visits re-read it.
        if (appConfigStore.setupProgressBooted && isVisible.value) {
            appConfigStore.refreshSetupProgress();
        }
        appConfigStore.setupProgressBooted = true;
    });

    return { steps, progress, isVisible, dismiss };
}
