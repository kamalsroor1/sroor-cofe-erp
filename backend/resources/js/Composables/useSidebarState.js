import { computed, onBeforeUnmount, onMounted, ref, unref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useDesktopViewport, useWideViewport } from './useMediaQuery';

const STORAGE_KEY = 'sidebar_collapsed';

/**
 * Desktop sidebar state for the app shell (breakpoints in helpers/breakpoints.js).
 *  - >= 1280px: open or collapsed rail, pushing the content; the choice is remembered per user.
 *  - 1024-1279px: always a rail in the layout flow; "expanding" opens it as an overlay (never persisted).
 *  - < 1024px: not rendered (the layout shows the drawer + bottom nav instead).
 * `forceCollapsed` (the POS) starts the sidebar as a rail without touching the saved choice.
 */
export function useSidebarState(forceCollapsed) {
    const authStore = useAuthStore();
    const route = useRoute();
    const isDesktopUp = useDesktopViewport();
    const isWide = useWideViewport();

    const storageKey = computed(() => (authStore.user?.id ? `${STORAGE_KEY}:${authStore.user.id}` : STORAGE_KEY));

    const readPreference = () => {
        try {
            const own = localStorage.getItem(storageKey.value);
            return (own ?? localStorage.getItem(STORAGE_KEY)) === 'true';
        } catch {
            return false;
        }
    };

    const persist = (collapsed) => {
        try {
            localStorage.setItem(storageKey.value, collapsed ? 'true' : 'false');
        } catch {
            // Storage unavailable (private mode / quota): the choice just lasts for this session.
        }
    };

    const preferCollapsed = ref(readPreference());
    watch(storageKey, () => {
        preferCollapsed.value = readPreference();
    });

    const isOverlayOpen = ref(false);
    const isTemporarilyOpen = ref(false);

    const isOverlay = computed(() => isDesktopUp.value && !isWide.value);
    const isCollapsed = computed(() => {
        if (isOverlay.value) return !isOverlayOpen.value;
        if (unref(forceCollapsed)) return !isTemporarilyOpen.value;
        return preferCollapsed.value;
    });

    const toggle = () => {
        if (isOverlay.value) {
            isOverlayOpen.value = !isOverlayOpen.value;
        } else if (unref(forceCollapsed)) {
            isTemporarilyOpen.value = !isTemporarilyOpen.value;
        } else {
            preferCollapsed.value = !preferCollapsed.value;
            persist(preferCollapsed.value);
        }
    };

    const closeOverlay = () => {
        isOverlayOpen.value = false;
    };

    watch(
        () => unref(forceCollapsed),
        () => {
            isTemporarilyOpen.value = false;
        }
    );
    watch(() => route.path, closeOverlay);
    watch(isOverlay, closeOverlay);

    const onKeydown = (event) => {
        if (event.key === 'Escape' && isOverlayOpen.value) closeOverlay();
    };
    onMounted(() => document.addEventListener('keydown', onKeydown));
    onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));

    return { isDesktopUp, isCollapsed, isOverlay, isOverlayOpen, toggle, closeOverlay };
}
