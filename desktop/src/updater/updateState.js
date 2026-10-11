'use strict';

// APP-7: persisted state of the desktop updater (settings key `updateState`). Pure: every
// function takes the state and `now` (ms) and returns a NEW state, so it is unit tested
// without electron or a clock.
//
//  - failed attempts: at most MAX_FAILED_ATTEMPTS failures inside ATTEMPT_WINDOW_MS, then
//    the updater stops (update_attempts_exhausted) and the manual download page is offered;
//  - pending: written just before the installer starts; resolved at the next startup
//    (installed version == target -> success, otherwise a failed attempt);
//  - boot guard: a boot that does not reach "healthy" (BOOT_CONFIRM_DELAY_MS after the page
//    loaded) is a failed boot. After MAX_FAILED_BOOTS failed boots of a freshly updated
//    build, the previous verified release is OFFERED (never installed silently).

const MAX_FAILED_ATTEMPTS = 3;
const ATTEMPT_WINDOW_MS = 24 * 60 * 60 * 1000;
const MAX_FAILED_BOOTS = 3;
const BOOT_CONFIRM_DELAY_MS = 60 * 1000;
const KINDS = new Set(['update', 'rollback']);

function emptyState() {
    return {
        failedAttempts: [],
        pending: null,
        lastResult: null,
        bootInProgress: false,
        failedBoots: 0,
        postUpdateWatch: null,
    };
}

function isCode(value) {
    return Number.isInteger(value) && value > 0;
}

function isTime(value) {
    return Number.isFinite(value) && value > 0;
}

/** Sanitizes whatever is on disk (hand-edited / older format) and drops expired attempts. */
function normalizeState(raw, now) {
    const state = emptyState();
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return state;

    if (Array.isArray(raw.failedAttempts)) {
        state.failedAttempts = raw.failedAttempts
            .filter((at) => isTime(at) && at <= now && now - at < ATTEMPT_WINDOW_MS)
            .slice(-MAX_FAILED_ATTEMPTS * 2);
    }

    const p = raw.pending;
    if (p && typeof p === 'object' && isCode(p.versionCode) && isCode(p.fromVersionCode) && isTime(p.startedAt)) {
        state.pending = {
            versionCode: p.versionCode,
            fromVersionCode: p.fromVersionCode,
            kind: KINDS.has(p.kind) ? p.kind : 'update',
            startedAt: p.startedAt,
        };
    }

    const r = raw.lastResult;
    if (r && typeof r === 'object' && (r.status === 'success' || r.status === 'failed') && isCode(r.versionCode)) {
        state.lastResult = {
            status: r.status,
            versionCode: r.versionCode,
            kind: KINDS.has(r.kind) ? r.kind : 'update',
            at: isTime(r.at) ? r.at : now,
        };
    }

    state.bootInProgress = raw.bootInProgress === true;
    state.failedBoots = Number.isInteger(raw.failedBoots) && raw.failedBoots > 0 ? raw.failedBoots : 0;

    const w = raw.postUpdateWatch;
    if (w && typeof w === 'object' && isCode(w.versionCode) && isCode(w.fromVersionCode)) {
        state.postUpdateWatch = {
            versionCode: w.versionCode,
            fromVersionCode: w.fromVersionCode,
            since: isTime(w.since) ? w.since : now,
        };
    }

    return state;
}

/** @returns {{ok: boolean, remaining: number}} */
function canAttempt(state, now) {
    const recent = state.failedAttempts.filter((at) => now - at < ATTEMPT_WINDOW_MS);
    const remaining = Math.max(0, MAX_FAILED_ATTEMPTS - recent.length);
    return { ok: remaining > 0, remaining };
}

function recordFailedAttempt(state, now) {
    return {
        ...state,
        failedAttempts: [...state.failedAttempts.filter((at) => now - at < ATTEMPT_WINDOW_MS), now],
    };
}

/**
 * Just before the installer starts. The app exits right after on purpose, so the current boot
 * is not counted as failed.
 */
function markPending(state, { versionCode, fromVersionCode, kind = 'update' }, now) {
    return {
        ...state,
        pending: { versionCode, fromVersionCode, kind: KINDS.has(kind) ? kind : 'update', startedAt: now },
        bootInProgress: false,
    };
}

/**
 * First thing at startup: did the installer started last time actually install its version?
 * @returns {{state: object, outcome: null|'success'|'failed'}}
 */
function resolvePendingAtStartup(state, currentVersionCode, now) {
    const pending = state.pending;
    if (!pending) return { state, outcome: null };

    if (currentVersionCode === pending.versionCode) {
        return {
            outcome: 'success',
            state: {
                ...state,
                pending: null,
                failedAttempts: [],
                lastResult: { status: 'success', versionCode: pending.versionCode, kind: pending.kind, at: now },
                failedBoots: 0,
                bootInProgress: false,
                // A rollback is the "known good" build: only a forward update is watched for crashes.
                postUpdateWatch:
                    pending.kind === 'update'
                        ? { versionCode: pending.versionCode, fromVersionCode: pending.fromVersionCode, since: now }
                        : null,
            },
        };
    }

    const failed = recordFailedAttempt(state, now);
    return {
        outcome: 'failed',
        state: {
            ...failed,
            pending: null,
            lastResult: { status: 'failed', versionCode: pending.versionCode, kind: pending.kind, at: now },
        },
    };
}

/** Startup: an earlier boot that never became healthy counts as a failed boot of the watched update. */
function beginBoot(state) {
    const unfinished = state.bootInProgress === true;
    return {
        ...state,
        failedBoots: unfinished && state.postUpdateWatch ? state.failedBoots + 1 : state.failedBoots,
        bootInProgress: true,
    };
}

/** BOOT_CONFIRM_DELAY_MS after the page loaded: this build works, stop watching it. */
function confirmBoot(state) {
    return { ...state, bootInProgress: false, failedBoots: 0, postUpdateWatch: null };
}

/** A clean quit before the boot was confirmed is not a crash. */
function endBootCleanly(state) {
    return { ...state, bootInProgress: false };
}

function shouldOfferRollback(state) {
    return Boolean(state.postUpdateWatch) && state.failedBoots >= MAX_FAILED_BOOTS;
}

/** After the rollback dialog: declining asks again only after MAX_FAILED_BOOTS more failed boots. */
function rollbackOffered(state) {
    return { ...state, failedBoots: 0 };
}

/** What the renderer may see (IPC updater:get-state): no paths, no URLs. */
function summarize(state, now) {
    const attempt = canAttempt(state, now);
    return {
        pending: Boolean(state.pending),
        attemptsRemaining: attempt.remaining,
        attemptsExhausted: !attempt.ok,
        lastResult: state.lastResult
            ? {
                  status: state.lastResult.status,
                  versionCode: state.lastResult.versionCode,
                  kind: state.lastResult.kind,
              }
            : null,
        failedBoots: state.failedBoots,
        watchingUpdate: Boolean(state.postUpdateWatch),
    };
}

module.exports = {
    MAX_FAILED_ATTEMPTS,
    ATTEMPT_WINDOW_MS,
    MAX_FAILED_BOOTS,
    BOOT_CONFIRM_DELAY_MS,
    emptyState,
    normalizeState,
    canAttempt,
    recordFailedAttempt,
    markPending,
    resolvePendingAtStartup,
    beginBoot,
    confirmBoot,
    endBootCleanly,
    shouldOfferRollback,
    rollbackOffered,
    summarize,
};
