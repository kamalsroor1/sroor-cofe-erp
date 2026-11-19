import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { buildLabels, loadLabelJob, pageCss, pageGeometry, paginateLabels } from '../helpers/labelPrint';

const PAGE_STYLE_ID = 'item-labels-page-style';

/**
 * SHOP-04: the label print page. Reads the job the items list handed over (session storage), expands
 * it into labels and pages, and sets the @page size of the chosen preset while the page is open.
 * Printing is the browser / desktop shell print (window.print), so it works on web and Electron alike.
 */
export function useItemLabelsPrint() {
    const router = useRouter();
    const job = ref(loadLabelJob());
    const isReady = ref(false);

    const settings = computed(() => job.value?.settings || null);
    const result = computed(() =>
        job.value ? buildLabels(job.value.lines, job.value.settings) : { labels: [], skipped: [], truncated: false }
    );
    const pages = computed(() => (settings.value ? paginateLabels(result.value.labels, settings.value.preset) : []));
    const geometry = computed(() => (settings.value ? pageGeometry(settings.value.preset) : null));

    const applyPageStyle = () => {
        if (!settings.value || typeof document === 'undefined') return;
        let style = document.getElementById(PAGE_STYLE_ID);
        if (!style) {
            style = document.createElement('style');
            style.id = PAGE_STYLE_ID;
            document.head.appendChild(style);
        }
        style.textContent = `@media print { ${pageCss(settings.value.preset)} }`;
    };

    const removePageStyle = () => {
        if (typeof document === 'undefined') return;
        document.getElementById(PAGE_STYLE_ID)?.remove();
    };

    const print = () => window.print();

    const goBack = () => router.push({ name: 'items.index' });

    onMounted(async () => {
        applyPageStyle();
        // Let the (possibly long) label grid paint behind the skeleton first.
        await nextTick();
        requestAnimationFrame(() => {
            isReady.value = true;
        });
    });

    onBeforeUnmount(removePageStyle);

    return { job, settings, result, pages, geometry, isReady, print, goBack };
}
