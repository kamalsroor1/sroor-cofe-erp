import { useAppConfigStore } from '../../stores/appConfig';
import api from '../../Services/api';

/**
 * The tenant's business date (system.business_date from /system/context), refreshed lightly
 * on demand because a POS tab can stay open across the business-day cutoff.
 */
export function usePosBusinessDate() {
    const appConfig = useAppConfigStore();

    const refreshBusinessDate = async () => {
        try {
            const res = await api.get('/system/context');
            const fresh = res.data?.data?.system?.business_date;
            if (fresh) appConfig.system = { ...appConfig.system, business_date: fresh };
        } catch {
            // Keep the date already in the store; the caller decides what to do without one.
        }
        return appConfig.system?.business_date || null;
    };

    return { refreshBusinessDate };
}
