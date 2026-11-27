import { getCurrentInstance, nextTick, onBeforeUnmount, onScopeDispose, toValue, useId, watch } from 'vue';
import { useBodyScrollLock } from './useBodyScrollLock.js';

let fallbackTrapCounter = 0;

export const FOCUSABLE_SELECTOR = [
    'a[href]',
    'area[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    'summary',
    '[tabindex]:not([tabindex="-1"])',
    '[contenteditable]:not([contenteditable="false"])',
].join(', ');

export function createFocusTrapStack() {
    const stack = [];

    return {
        push(id) {
            if (!stack.includes(id)) stack.push(id);
        },
        remove(id) {
            const index = stack.indexOf(id);
            if (index !== -1) stack.splice(index, 1);
        },
        isTop(id) {
            return stack.length > 0 && stack[stack.length - 1] === id;
        },
        size() {
            return stack.length;
        },
        clear() {
            stack.length = 0;
        },
    };
}

const defaultTrapStack = createFocusTrapStack();

export function isElementFocusable(el, win = globalThis.window) {
    if (!el || el.disabled) return false;
    if (el.getAttribute?.('aria-hidden') === 'true') return false;
    if (typeof el.tabIndex === 'number' && el.tabIndex < 0) return false;
    if (typeof el.closest === 'function' && el.closest('[hidden], [inert], fieldset[disabled]')) return false;

    const getComputedStyle = win?.getComputedStyle;
    if (typeof getComputedStyle === 'function') {
        const style = getComputedStyle(el);
        if (style?.display === 'none' || style?.visibility === 'hidden') return false;
        if (el.offsetParent === null && style?.position !== 'fixed') return false;
    } else if ('offsetParent' in el && el.offsetParent === null) {
        return false;
    }

    return true;
}

export function getFocusableElements(
    container,
    { shiftKey = false, activeElement = null, win = globalThis.window } = {}
) {
    if (!container || typeof container.querySelectorAll !== 'function') return [];

    const candidates = Array.from(container.querySelectorAll(FOCUSABLE_SELECTOR)).filter((el) =>
        isElementFocusable(el, win)
    );

    const radioGroups = new Map();
    for (const el of candidates) {
        if (el.tagName === 'INPUT' && el.type === 'radio' && el.name) {
            if (!radioGroups.has(el.name)) radioGroups.set(el.name, []);
            radioGroups.get(el.name).push(el);
        }
    }

    if (radioGroups.size === 0) return candidates;

    return candidates.filter((el) => {
        if (el.tagName !== 'INPUT' || el.type !== 'radio' || !el.name) return true;
        const group = radioGroups.get(el.name);
        const checked = group.find((radio) => radio.checked);
        if (checked) return el === checked;
        if (activeElement && group.includes(activeElement)) return el === activeElement;
        return shiftKey ? el === group[group.length - 1] : el === group[0];
    });
}

export function trapTabKey(event, container, { doc = globalThis.document, win = globalThis.window } = {}) {
    if (event.key !== 'Tab' || !container) return false;

    const active = doc?.activeElement ?? null;
    const items = getFocusableElements(container, {
        shiftKey: Boolean(event.shiftKey),
        activeElement: active,
        win,
    });

    if (items.length === 0) {
        event.preventDefault();
        container.focus?.({ preventScroll: true });
        return true;
    }

    const first = items[0];
    const last = items[items.length - 1];
    const isInside =
        typeof container.contains === 'function'
            ? container.contains(active)
            : active === container || items.includes(active);

    if (event.shiftKey) {
        if (!isInside || active === container || active === first) {
            event.preventDefault();
            last.focus();
            return true;
        }
        return false;
    }

    if (!isInside || active === container || active === last) {
        event.preventDefault();
        first.focus();
        return true;
    }

    return false;
}

export function handleTrapKeydown(
    event,
    { container, isTop = true, onEscape = null, doc = globalThis.document, win = globalThis.window } = {}
) {
    if (!isTop) return false;
    // A blocking SweetAlert2 dialog owns the keyboard; toasts (notifySuccess) must not disable the trap.
    if (doc?.querySelector?.('.swal2-container .swal2-popup:not(.swal2-toast)')) return false;

    if (event.key === 'Escape') {
        if (event.defaultPrevented) return false;
        if (typeof onEscape === 'function') {
            onEscape(event);
            return true;
        }
        return false;
    }

    if (event.key === 'Tab') {
        return trapTabKey(event, container, { doc, win });
    }

    return false;
}

export function useFocusTrap(panelRef, isOpen, options = {}) {
    const {
        onEscape = null,
        lockScroll = true,
        initialFocus = 'first',
        restoreFocus = true,
        onActivate = null,
        onDeactivate = null,
        stack = defaultTrapStack,
    } = options;

    const hasInstance = Boolean(getCurrentInstance());
    const trapId = hasInstance ? useId() : `focus-trap-${++fallbackTrapCounter}`;
    const { lock: acquireScrollLock, unlock: releaseScrollLock } = useBodyScrollLock();

    let isActive = false;
    let previousFocus = null;

    const focusIntoPanel = () => {
        const panel = panelRef.value;
        if (!panel || typeof document === 'undefined') return;

        // Respect a child control that already grabbed focus (e.g. autofocus or child onMounted).
        if (panel.contains(document.activeElement) && document.activeElement !== panel) return;

        if (initialFocus === 'panel') {
            panel.focus?.({ preventScroll: true });
            return;
        }

        const items = getFocusableElements(panel);
        if (items.length > 0) {
            items[0].focus({ preventScroll: true });
        } else {
            panel.focus?.({ preventScroll: true });
        }
    };

    const onKeydown = (event) => {
        if (!isActive) return;
        handleTrapKeydown(event, {
            container: panelRef.value,
            isTop: stack.isTop(trapId),
            onEscape,
        });
    };

    const activate = () => {
        if (isActive) return;
        isActive = true;
        previousFocus = typeof document !== 'undefined' ? document.activeElement : null;
        stack.push(trapId);
        if (lockScroll) acquireScrollLock();
        if (typeof document !== 'undefined') {
            document.addEventListener('keydown', onKeydown);
        }
        onActivate?.();
        nextTick(() => {
            if (isActive) focusIntoPanel();
        });
    };

    const deactivate = () => {
        if (!isActive) return;
        isActive = false;
        stack.remove(trapId);
        if (typeof document !== 'undefined') {
            document.removeEventListener('keydown', onKeydown);
        }
        if (lockScroll) releaseScrollLock();
        onDeactivate?.();

        const target = previousFocus;
        previousFocus = null;
        if (
            restoreFocus &&
            target &&
            typeof target.focus === 'function' &&
            (typeof document === 'undefined' || document.contains(target))
        ) {
            target.focus({ preventScroll: true });
        }
    };

    watch(
        () => Boolean(toValue(isOpen)),
        (visible) => {
            if (visible) activate();
            else deactivate();
        },
        { immediate: true, flush: 'sync' }
    );

    if (hasInstance) {
        onBeforeUnmount(deactivate);
    } else {
        onScopeDispose(deactivate);
    }

    return {
        trapId,
        isTop: () => stack.isTop(trapId),
    };
}
