import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import api from '@/Services/api';
import { useAuthStore } from '@/stores/auth';
import { describeTemporaryAccess, dismiss, isDismissed } from '@/helpers/temporaryAccess';

const TICK_MS = 60 * 1000;
const REFRESH_MS = 5 * 60 * 1000;

// One shared snapshot: the app shell banner and the /billing page read and refresh the same window.
const snapshot = ref(null);
const dismissedIds = ref(new Set());

/** Stores the window of a GET /billing/subscription payload (called by every reader of that endpoint). */
export function publishTemporaryAccess(data) {
    snapshot.value = {
        window: data?.temporary_access || null,
        planName: data?.plan?.name || '',
        endsAt: data?.subscription?.ends_at || null,
        fetchedAtMs: Date.now(),
    };
}

const storage = () => {
    try {
        return window.localStorage;
    } catch {
        return null;
    }
};

/**
 * #232 UI §3: banner state for the app shell. Loads the window itself (once, then every 5 minutes) for
 * users who may open /billing, and recomputes the countdown every 60 seconds.
 * A 403 (e.g. a quick-login session) is accepted silently so no permission alert is raised.
 */
export function useTemporaryAccess() {
    const auth = useAuthStore();
    const nowMs = ref(Date.now());
    let tick = null;
    let refresh = null;

    const load = async () => {
        if (!auth.hasPermission('billing.manage')) return;
        try {
            const res = await api.get('/billing/subscription', { validateStatus: (s) => s === 200 || s === 403 });
            if (res.status === 200) {
                publishTemporaryAccess(res.data.data);
                nowMs.value = Date.now();
            }
        } catch {
            // The banner is informational: a failed refresh keeps the last snapshot.
        }
    };

    const banner = computed(() => {
        const current = snapshot.value;
        if (!current?.window) return null;
        const id = current.window.invoice_id;
        return describeTemporaryAccess(current.window, {
            nowMs: nowMs.value,
            fetchedAtMs: current.fetchedAtMs,
            planName: current.planName,
            endsAt: current.endsAt,
            dismissed: dismissedIds.value.has(id) || (storage() ? isDismissed(id, storage()) : false),
        });
    });

    const invoiceId = computed(() => snapshot.value?.window?.invoice_id ?? null);

    const dismissBanner = () => {
        const id = invoiceId.value;
        if (id === null) return;
        if (storage()) dismiss(id, storage());
        dismissedIds.value = new Set([...dismissedIds.value, id]);
    };

    onMounted(() => {
        load();
        tick = setInterval(() => {
            nowMs.value = Date.now();
        }, TICK_MS);
        refresh = setInterval(load, REFRESH_MS);
    });
    onBeforeUnmount(() => {
        clearInterval(tick);
        clearInterval(refresh);
    });

    return { banner, invoiceId, dismissBanner };
}
