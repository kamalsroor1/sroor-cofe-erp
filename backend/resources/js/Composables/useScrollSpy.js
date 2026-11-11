import { onBeforeUnmount, onMounted, ref } from 'vue';

const prefersReducedMotion = () =>
    typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

/**
 * Distance (px) from the viewport top of the line that decides the active section: just under the sticky
 * nav + chip bar (≈ 141px on desktop). Sections use a smaller scroll-margin-top so a chip jump lands on it.
 */
const SPY_LINE = 160;

/** Clicking a chip scrolls smoothly past other sections; ignore them for this long. */
const PROGRAMMATIC_SCROLL_MS = 900;

/**
 * Scroll-spy for in-page section navigation (no packages): an IntersectionObserver whose root margin
 * shrinks the viewport to a 1px line under the sticky header, so exactly the section crossing that line
 * is active. Between sections (gaps) the last active one stays. `scrollTo` scrolls smoothly (sections
 * carry their own scroll-margin-top) and moves keyboard focus to the section.
 */
export function useScrollSpy(ids) {
    const activeId = ref(ids[0] || '');
    let observer = null;
    let lockedUntil = 0;

    const observe = () => {
        observer?.disconnect();
        if (typeof IntersectionObserver !== 'function') return;

        const line = SPY_LINE;
        const bottom = Math.max(0, window.innerHeight - line - 1);
        observer = new IntersectionObserver(
            (entries) => {
                if (Date.now() < lockedUntil) return;
                const hit = entries.find((entry) => entry.isIntersecting);
                if (hit) activeId.value = hit.target.id;
            },
            { rootMargin: `-${line}px 0px -${bottom}px 0px` }
        );
        ids.forEach((id) => {
            const el = document.getElementById(id);
            if (el) observer.observe(el);
        });
    };

    let resizeTimer = null;
    const onResize = () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(observe, 150);
    };

    const scrollTo = (id) => {
        const el = document.getElementById(id);
        if (!el) return;
        activeId.value = id;
        lockedUntil = Date.now() + PROGRAMMATIC_SCROLL_MS;
        el.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
        el.focus({ preventScroll: true });
    };

    onMounted(() => {
        observe();
        window.addEventListener('resize', onResize);
    });
    onBeforeUnmount(() => {
        observer?.disconnect();
        clearTimeout(resizeTimer);
        window.removeEventListener('resize', onResize);
    });

    return { activeId, scrollTo };
}
