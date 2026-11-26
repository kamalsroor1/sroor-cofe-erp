import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useAppConfigStore } from '@/stores/appConfig';
import { describeTemporaryAccess, dismiss, isDismissed } from '@/helpers/temporaryAccess';

const TICK_MS = 60 * 1000;

const dismissedIds = ref(new Set());

/**
 * Stores the window of a GET /billing/subscription payload as the banner's window. The /billing page
 * calls this after it loads and after a receipt upload, so the banner follows what the page just read.
 */
export function publishTemporaryAccess(data) {
    useAppConfigStore().setTemporaryAccess(data?.temporary_access || null);
}

const storage = () => {
    try {
        return window.localStorage;
    } catch {
        return null;
    }
};

/**
 * #232 UI §3: banner state for the app shell. The window comes with the bootstrap context
 * (`subscription_temporary_access` of /system/context, stored in the appConfig store), so no request is
 * made here; the countdown is recomputed every 60 seconds.
 */
export function useTemporaryAccess() {
    const appConfig = useAppConfigStore();
    const nowMs = ref(Date.now());
    let tick = null;

    const banner = computed(() => {
        const current = appConfig.temporaryAccess;
        if (!current) return null;
        const id = current.invoice_id;
        return describeTemporaryAccess(current, {
            nowMs: nowMs.value,
            fetchedAtMs: appConfig.temporaryAccessFetchedAtMs,
            dismissed: dismissedIds.value.has(id) || (storage() ? isDismissed(id, storage()) : false),
        });
    });

    const invoiceId = computed(() => appConfig.temporaryAccess?.invoice_id ?? null);

    const dismissBanner = () => {
        const id = invoiceId.value;
        if (id === null) return;
        if (storage()) dismiss(id, storage());
        dismissedIds.value = new Set([...dismissedIds.value, id]);
    };

    onMounted(() => {
        tick = setInterval(() => {
            nowMs.value = Date.now();
        }, TICK_MS);
    });
    onBeforeUnmount(() => clearInterval(tick));

    return { banner, invoiceId, dismissBanner };
}
