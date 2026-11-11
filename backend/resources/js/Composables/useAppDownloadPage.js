import { computed, ref } from 'vue';
import { useAppDownloads } from './useAppUpdate';

/** Platform of a plain browser visit, from the user agent (the native shells already report their own). */
const browserPlatform = () => {
    const ua = typeof navigator !== 'undefined' ? navigator.userAgent || '' : '';
    if (/Android/i.test(ua)) return 'android';
    if (/Windows/i.test(ua)) return 'windows';
    return null;
};

/**
 * Public download page (/download): the latest release per platform (useAppDownloads, unchanged contract for the
 * in-app updater) split into the visitor's own platform, featured first, and the other platforms.
 */
export function useAppDownloadPage() {
    const { releases, isLoading: isFetching, hasError, load: fetchReleases, currentPlatform } = useAppDownloads();

    // Loading until the first fetch settles, so the first paint is the skeleton, never the empty state.
    const started = ref(false);
    const isLoading = computed(() => !started.value || isFetching.value);
    const load = () => {
        started.value = true;
        return fetchReleases();
    };

    const visitorPlatform = currentPlatform === 'web' ? browserPlatform() : currentPlatform;

    const featured = computed(
        () => releases.value.find((release) => release.platform === visitorPlatform) || releases.value[0] || null
    );
    const others = computed(() => releases.value.filter((release) => release !== featured.value));
    const featuredIsCurrent = computed(() => featured.value?.platform === visitorPlatform);

    return { featured, others, featuredIsCurrent, isLoading, hasError, load };
}
