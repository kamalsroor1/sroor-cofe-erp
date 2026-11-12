import { onBeforeUnmount, ref } from 'vue';
import { COMPACT_QUERY, DESKTOP_QUERY, WIDE_QUERY } from '../helpers/breakpoints';

/**
 * Reactive `matchMedia` wrapper. Falls back to `false` where matchMedia is unavailable (SSR, old webviews).
 */
export function useMediaQuery(query) {
    const matches = ref(false);
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return matches;

    const mql = window.matchMedia(query);
    matches.value = mql.matches;
    const onChange = (event) => {
        matches.value = event.matches;
    };
    mql.addEventListener('change', onChange);
    onBeforeUnmount(() => mql.removeEventListener('change', onChange));

    return matches;
}

// Phones and tablets: below DESKTOP_MIN, the same switch point as the app shell (see helpers/breakpoints.js).
export const useCompactViewport = () => useMediaQuery(COMPACT_QUERY);

// App shell: >= 1024px shows the sidebar rail, >= 1280px the open sidebar (see helpers/breakpoints.js).
export const useDesktopViewport = () => useMediaQuery(DESKTOP_QUERY);
export const useWideViewport = () => useMediaQuery(WIDE_QUERY);
