import { nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';

/** Section ids of the marketing homepage that the header links to. */
export const LANDING_SECTIONS = Object.freeze(['features', 'plans', 'faq']);

const prefersReducedMotion = () =>
    typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

/** Smoothly scrolls a homepage section into view and moves keyboard focus to it. */
function scrollToSection(id) {
    const section = document.getElementById(id);
    if (!section) return;

    section.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
    section.focus({ preventScroll: true });
}

/**
 * In-page navigation for the public landing pages. The pages scroll inside LandingShell's <main>,
 * so anchors are scrolled explicitly; from another landing page the link opens the homepage on
 * that section (the homepage calls scrollToRouteHash once mounted).
 */
export function useLandingAnchors() {
    const route = useRoute();
    const router = useRouter();

    const sectionHref = (id) => router.resolve({ name: 'landing.home', hash: `#${id}` }).href;

    const goToSection = async (id) => {
        if (route.name !== 'landing.home') {
            await router.push({ name: 'landing.home', hash: `#${id}` });
            return;
        }
        scrollToSection(id);
    };

    const scrollToRouteHash = async () => {
        const id = route.hash.replace(/^#/, '');
        if (!id) return;
        await nextTick();
        scrollToSection(id);
    };

    return { sectionHref, goToSection, scrollToRouteHash };
}
