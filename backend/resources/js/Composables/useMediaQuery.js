import { onBeforeUnmount, ref } from 'vue';

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

// Phones: below Tailwind's `md` (768px). Tablets keep the desktop/tablet layout.
export const usePhoneViewport = () => useMediaQuery('(max-width: 767.98px)');
