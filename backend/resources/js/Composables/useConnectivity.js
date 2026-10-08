import { effectScope } from 'vue';
import { useOnline } from '@vueuse/core';
import api from '../Services/api';
import { createConnectivityMonitor, PROBE_TIMEOUT_MS } from '../helpers/connectivity';

// One app-wide monitor, created in a detached scope so it survives the component that first asked for it.
let monitor = null;

function createMonitor() {
    const scope = effectScope(true);
    return scope.run(() => {
        const instance = createConnectivityMonitor({
            browserOnline: useOnline(),
            probe: () => api.get('/ping', { timeout: PROBE_TIMEOUT_MS }),
            schedule: (fn, ms) => window.setTimeout(fn, ms),
            cancel: (id) => window.clearTimeout(id),
        });
        instance.start();
        return instance;
    });
}

/**
 * Live connectivity: browser online flag + `/api/v1/ping` heartbeat + axios network errors.
 * Sales/write actions must be blocked while `isOnline` is false (no offline queue until Phase 2).
 */
export function useConnectivity() {
    if (!monitor) monitor = createMonitor();

    return {
        isOnline: monitor.isOnline,
        isBrowserOnline: monitor.isBrowserOnline,
        isServerReachable: monitor.isServerReachable,
        checkNow: monitor.check,
    };
}

export default useConnectivity;
