import { computed, onBeforeUnmount, watch } from 'vue';

const BLOCKED_KEY_EVENTS = ['keydown', 'keyup', 'keypress'];

const FOCUSABLE =
    'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * SET-02: while the lock screen is up, everything behind it must be dead, not just covered:
 *  - the rest of <body> (the app, open modals, SweetAlert dialogs) gets `inert`: no clicks, no focus,
 *    hidden from screen readers; nothing is unmounted, so carts / tabs / form inputs keep their state;
 *  - keyboard events that start outside the lock never reach the app (F2, F9, Ctrl+Enter, a barcode
 *    scanner typing like a keyboard); F5 / reload still work because the browser default is untouched;
 *  - Tab cycles inside the lock only;
 *  - focus goes back to where it was when the lock closes.
 *
 * @param {import('vue').Ref<HTMLElement|null>} rootRef the lock overlay's root element (a direct child of <body>)
 * @param {import('vue').Ref<boolean>} activeRef true while the lock is showing
 */
export function useScreenLockGuard(rootRef, activeRef) {
    let inerted = [];
    let observer = null;
    let previousFocus = null;

    const markInert = (node) => {
        if (!(node instanceof HTMLElement) || node === rootRef.value || node.inert) return;
        node.inert = true;
        inerted.push(node);
    };

    const inertEverythingElse = () => {
        Array.from(document.body.children).forEach(markInert);
        // A dialog opened by a background request while locked must be dead as well.
        observer = new MutationObserver((records) => {
            records.forEach((record) => record.addedNodes.forEach(markInert));
        });
        observer.observe(document.body, { childList: true });
    };

    const restoreEverythingElse = () => {
        observer?.disconnect();
        observer = null;
        inerted.forEach((node) => {
            node.inert = false;
        });
        inerted = [];
    };

    const swallowOutsideKeys = (event) => {
        if (rootRef.value?.contains(event.target)) return;
        event.stopImmediatePropagation();
    };

    const trapTab = (event) => {
        if (event.key !== 'Tab' || !rootRef.value) return;
        const items = Array.from(rootRef.value.querySelectorAll(FOCUSABLE)).filter((el) => el.offsetParent !== null);
        if (items.length === 0) {
            event.preventDefault();
            return;
        }
        const first = items[0];
        const last = items[items.length - 1];
        const current = document.activeElement;
        if (event.shiftKey && (current === first || !rootRef.value.contains(current))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (current === last || !rootRef.value.contains(current))) {
            event.preventDefault();
            first.focus();
        }
    };

    const engage = () => {
        previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        inertEverythingElse();
        window.addEventListener('keydown', trapTab, true);
        BLOCKED_KEY_EVENTS.forEach((name) => window.addEventListener(name, swallowOutsideKeys, true));
    };

    const release = () => {
        BLOCKED_KEY_EVENTS.forEach((name) => window.removeEventListener(name, swallowOutsideKeys, true));
        window.removeEventListener('keydown', trapTab, true);
        restoreEverythingElse();
        const target = previousFocus;
        previousFocus = null;
        if (target?.isConnected) target.focus({ preventScroll: true });
    };

    // Engaged only once the overlay element exists (also true right after a reload that restored the lock).
    const engaged = computed(() => activeRef.value && !!rootRef.value);

    watch(
        engaged,
        (isEngaged, wasEngaged) => {
            if (isEngaged && !wasEngaged) engage();
            else if (!isEngaged && wasEngaged) release();
        },
        { flush: 'post', immediate: true }
    );

    onBeforeUnmount(() => {
        if (engaged.value) release();
    });
}
