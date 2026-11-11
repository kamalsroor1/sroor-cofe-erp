import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { Coffee, Download, Layers, MessageCircle, Package, Store, Tags, Truck } from 'lucide-vue-next';

/** Business types with a dedicated marketing page (/for/:industry). Labels reuse the audience copy. */
export const LANDING_INDUSTRIES = Object.freeze([
    { key: 'coffee', icon: Coffee },
    { key: 'retail', icon: Store },
    { key: 'wholesale', icon: Package },
    { key: 'vans', icon: Truck },
]);

/** Top-level marketing navigation, in reading order. `children` marks the industries dropdown. */
export const LANDING_NAV_LINKS = Object.freeze([
    { key: 'features', labelKey: 'landing.nav.features', icon: Layers, to: { name: 'landing.features' } },
    { key: 'industry', labelKey: 'landing.nav.industries', icon: Store, children: LANDING_INDUSTRIES },
    { key: 'pricing', labelKey: 'landing.nav.pricing', icon: Tags, to: { name: 'landing.pricing' } },
    { key: 'download', labelKey: 'landing.nav.download', icon: Download, to: { name: 'app.download' } },
    { key: 'contact', labelKey: 'landing.nav.contact', icon: MessageCircle, to: { name: 'landing.contact' } },
]);

const ACTIVE_BY_ROUTE = Object.freeze({
    'landing.features': 'features',
    'landing.industry': 'industry',
    'landing.pricing': 'pricing',
    'app.download': 'download',
    'landing.contact': 'contact',
});

export const industryRoute = (key) => ({ name: 'landing.industry', params: { industry: key } });
export const industryLabelKey = (key) => `landing.home.audience.items.${key}.title`;

/** Marketing header / footer navigation and the link that matches the current route. */
export function useLandingNav() {
    const route = useRoute();
    const activeKey = computed(() => ACTIVE_BY_ROUTE[route.name] || '');

    return { links: LANDING_NAV_LINKS, industries: LANDING_INDUSTRIES, activeKey, industryRoute, industryLabelKey };
}
