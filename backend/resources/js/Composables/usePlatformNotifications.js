import { ref, computed, onBeforeUnmount } from 'vue';
import centralApi from '../Services/centralApi';
import { useTrans } from './useTrans';

/**
 * Super-admin panel notifications (the bell), Api\Central\PlatformNotificationController:
 *   GET  /super-admin/notifications?unread=1&per_page=20 -> { data, unread_count }
 *   POST /super-admin/notifications/{id}/read | /read-all
 * The unread count refreshes quietly every minute while the layout is mounted; the list loads when the panel opens.
 */

const ENDPOINT = '/super-admin/notifications';
const POLL_MS = 60_000;

export function usePlatformNotifications() {
    const { t } = useTrans();

    const items = ref([]);
    const unreadCount = ref(0);
    const isLoading = ref(false);
    const loadError = ref('');
    let timer = null;

    const hasUnread = computed(() => unreadCount.value > 0);

    const load = async ({ quiet = false } = {}) => {
        if (!quiet) {
            isLoading.value = true;
            loadError.value = '';
        }
        try {
            const response = await centralApi.get(ENDPOINT, { params: { unread: 1, per_page: 20 }, silent: true });
            items.value = response.data?.data || [];
            unreadCount.value = Number(response.data?.unread_count) || 0;
        } catch (error) {
            if (!quiet) loadError.value = error.userMessage || t('billing.console.notifications.load_failed');
        } finally {
            if (!quiet) isLoading.value = false;
        }
    };

    const markRead = async (item) => {
        if (!item || item.is_read) return;
        items.value = items.value.filter((entry) => entry.id !== item.id);
        unreadCount.value = Math.max(0, unreadCount.value - 1);
        try {
            await centralApi.post(`${ENDPOINT}/${item.id}/read`, null, { silent: true });
        } catch {
            await load({ quiet: true });
        }
    };

    const markAllRead = async () => {
        const previous = items.value;
        items.value = [];
        unreadCount.value = 0;
        try {
            await centralApi.post(`${ENDPOINT}/read-all`, null, { silent: true });
        } catch {
            items.value = previous;
            await load({ quiet: true });
        }
    };

    const startPolling = () => {
        load({ quiet: true });
        timer = window.setInterval(() => load({ quiet: true }), POLL_MS);
    };

    onBeforeUnmount(() => window.clearInterval(timer));

    return { items, unreadCount, hasUnread, isLoading, loadError, load, markRead, markAllRead, startPolling };
}
