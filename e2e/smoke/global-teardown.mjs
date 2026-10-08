// Removes the throwaway databases created by serve-local-smoke.mjs.
// Leftovers (e.g. a file still locked on Windows) are also removed at the start of the next run.
import { removeSmokeArtifacts } from './serve-local-smoke.mjs';

export default async function globalTeardown() {
    try {
        removeSmokeArtifacts();
    } catch (error) {
        console.warn(`[e2e-smoke] cleanup deferred to the next run: ${error.message}`);
    }
}
