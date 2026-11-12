// SET-02: how many state-changing API requests (POST / PUT / PATCH / DELETE) are in flight right now.
// The idle lock postpones itself while this is above zero, so it can never appear in the middle of
// saving an invoice. Services/api.js feeds it; nothing here knows about any screen.

let inFlight = 0;

const WRITE_METHODS = new Set(['post', 'put', 'patch', 'delete']);

export function isWriteMethod(method) {
    return WRITE_METHODS.has(String(method ?? 'get').toLowerCase());
}

export function writeStarted(config) {
    if (!isWriteMethod(config?.method)) return;
    // Marked on the config so the matching response (or error) releases exactly what was counted.
    config.__countedWrite = true;
    inFlight += 1;
}

export function writeFinished(config) {
    if (!config?.__countedWrite) return;
    config.__countedWrite = false;
    inFlight = Math.max(inFlight - 1, 0);
}

export function hasPendingWrites() {
    return inFlight > 0;
}
