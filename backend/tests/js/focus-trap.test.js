// GEN-012: modal & bottom-sheet dialogs must trap focus, expose dialog ARIA semantics,
// lock body scroll, coordinate nested stacks, and restore focus to the opener on close.
// Run from backend/: node --test "tests/js/focus-trap.test.js"
import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath, URL } from 'node:url';
import { effectScope, nextTick, ref } from 'vue';
import {
    createFocusTrapStack,
    getFocusableElements,
    handleTrapKeydown,
    trapTabKey,
    useFocusTrap,
} from '../../resources/js/Composables/useFocusTrap.js';

const readComponent = (path) =>
    readFileSync(fileURLToPath(new URL(`../../resources/js/${path}`, import.meta.url)), 'utf8');

function createMockElement(tagName, props = {}) {
    const attrs = { ...(props.attrs || {}) };
    const el = {
        tagName: tagName.toUpperCase(),
        type: props.type || '',
        name: props.name || '',
        checked: Boolean(props.checked),
        disabled: Boolean(props.disabled),
        tabIndex: props.tabIndex,
        offsetParent: props.offsetParent === undefined ? {} : props.offsetParent,
        _style: {
            display: props.display || 'block',
            visibility: props.visibility || 'visible',
            position: props.position || 'static',
        },
        _closestHidden: Boolean(props.closestHidden),
        children: props.children || [],
        focusCalls: 0,
        getAttribute(name) {
            return attrs[name] ?? null;
        },
        closest(selector) {
            if (el._closestHidden && selector.includes('[hidden]')) return {};
            return null;
        },
        focus() {
            el.focusCalls += 1;
            if (el._doc) el._doc.activeElement = el;
        },
        contains(target) {
            if (target === el) return true;
            return el.children.some((child) =>
                typeof child.contains === 'function' ? child.contains(target) : child === target
            );
        },
        querySelectorAll() {
            return el.children;
        },
    };
    return el;
}

function createMockDocument() {
    const listeners = new Map();
    const classes = new Set();
    const nodes = new Set();
    const doc = {
        activeElement: null,
        swalOpen: false,
        body: {
            classList: {
                add: (c) => classes.add(c),
                remove: (c) => classes.delete(c),
                contains: (c) => classes.has(c),
            },
        },
        register(...elements) {
            for (const element of elements) {
                element._doc = doc;
                nodes.add(element);
            }
        },
        contains(target) {
            return nodes.has(target);
        },
        querySelector(selector) {
            // A blocking (non-toast) SweetAlert2 dialog; toasts never match this selector.
            if (selector === '.swal2-container .swal2-popup:not(.swal2-toast)') return doc.swalOpen ? {} : null;
            return null;
        },
        addEventListener(type, fn) {
            if (!listeners.has(type)) listeners.set(type, new Set());
            listeners.get(type).add(fn);
        },
        removeEventListener(type, fn) {
            listeners.get(type)?.delete(fn);
        },
        dispatchKeydown(eventInit) {
            const event = {
                key: eventInit.key,
                shiftKey: Boolean(eventInit.shiftKey),
                defaultPrevented: false,
                preventDefault() {
                    this.defaultPrevented = true;
                },
            };
            for (const fn of [...(listeners.get('keydown') || [])]) {
                fn(event);
            }
            return event;
        },
    };
    return doc;
}

function createMockWindow() {
    return {
        getComputedStyle(el) {
            return el._style || { display: 'block', visibility: 'visible', position: 'static' };
        },
    };
}

function createKeyEvent(key, shiftKey = false) {
    return {
        key,
        shiftKey,
        defaultPrevented: false,
        preventDefault() {
            this.defaultPrevented = true;
        },
    };
}

describe('getFocusableElements', () => {
    it('filters out disabled, aria-hidden, hidden/inert ancestors, negative tabindex, and invisible elements', () => {
        const win = createMockWindow();
        const visibleBtn = createMockElement('button');
        const disabledBtn = createMockElement('button', { disabled: true });
        const ariaHiddenBtn = createMockElement('button', { attrs: { 'aria-hidden': 'true' } });
        const hiddenAncestorInput = createMockElement('input', { closestHidden: true });
        const negTabInput = createMockElement('input', { tabIndex: -1 });
        const displayNoneBtn = createMockElement('button', { display: 'none' });
        const visibilityHiddenBtn = createMockElement('button', { visibility: 'hidden' });
        const detachedNullOffset = createMockElement('button', { offsetParent: null });
        const fixedVisibleBtn = createMockElement('button', { offsetParent: null, position: 'fixed' });

        const container = createMockElement('div', {
            children: [
                visibleBtn,
                disabledBtn,
                ariaHiddenBtn,
                hiddenAncestorInput,
                negTabInput,
                displayNoneBtn,
                visibilityHiddenBtn,
                detachedNullOffset,
                fixedVisibleBtn,
            ],
        });

        const focusables = getFocusableElements(container, { win });
        assert.deepEqual(focusables, [visibleBtn, fixedVisibleBtn]);
    });

    it('treats a radio group as a single tab stop (checked member, or directional edge when unchecked)', () => {
        const win = createMockWindow();
        const r1 = createMockElement('input', { type: 'radio', name: 'method', checked: false });
        const r2 = createMockElement('input', { type: 'radio', name: 'method', checked: true });
        const r3 = createMockElement('input', { type: 'radio', name: 'method', checked: false });
        const submit = createMockElement('button');
        const container = createMockElement('div', { children: [r1, r2, r3, submit] });

        assert.deepEqual(getFocusableElements(container, { win }), [r2, submit]);

        r2.checked = false;
        assert.deepEqual(getFocusableElements(container, { shiftKey: false, win }), [r1, submit]);
        assert.deepEqual(getFocusableElements(container, { shiftKey: true, win }), [r3, submit]);
    });
});

describe('trapTabKey', () => {
    it('wraps Tab from last to first and Shift+Tab from first to last', () => {
        const doc = createMockDocument();
        const win = createMockWindow();
        const first = createMockElement('button');
        const middle = createMockElement('input');
        const last = createMockElement('button');
        const container = createMockElement('div', { children: [first, middle, last] });
        doc.register(container, first, middle, last);

        // Middle element does not intercept Tab
        doc.activeElement = middle;
        const midEvent = createKeyEvent('Tab', false);
        assert.equal(trapTabKey(midEvent, container, { doc, win }), false);
        assert.equal(midEvent.defaultPrevented, false);

        // Last -> First on Tab
        doc.activeElement = last;
        const wrapForward = createKeyEvent('Tab', false);
        assert.equal(trapTabKey(wrapForward, container, { doc, win }), true);
        assert.equal(wrapForward.defaultPrevented, true);
        assert.equal(doc.activeElement, first);

        // First -> Last on Shift+Tab
        const wrapBackward = createKeyEvent('Tab', true);
        assert.equal(trapTabKey(wrapBackward, container, { doc, win }), true);
        assert.equal(wrapBackward.defaultPrevented, true);
        assert.equal(doc.activeElement, last);
    });

    it('handles panel focus, outside focus escape, single focusable, and zero focusables', () => {
        const doc = createMockDocument();
        const win = createMockWindow();
        const only = createMockElement('button');
        const outside = createMockElement('button');
        const container = createMockElement('div', { children: [only] });
        doc.register(container, only, outside);

        // Focus on container itself -> Tab moves to first, Shift+Tab to last
        doc.activeElement = container;
        assert.equal(trapTabKey(createKeyEvent('Tab', false), container, { doc, win }), true);
        assert.equal(doc.activeElement, only);

        // Single focusable -> both Tab and Shift+Tab keep focus inside
        assert.equal(trapTabKey(createKeyEvent('Tab', false), container, { doc, win }), true);
        assert.equal(doc.activeElement, only);
        assert.equal(trapTabKey(createKeyEvent('Tab', true), container, { doc, win }), true);
        assert.equal(doc.activeElement, only);

        // Outside focus snaps back inside
        doc.activeElement = outside;
        assert.equal(trapTabKey(createKeyEvent('Tab', false), container, { doc, win }), true);
        assert.equal(doc.activeElement, only);

        // Zero focusables keeps focus on the container itself
        container.children = [];
        doc.activeElement = outside;
        const emptyEvent = createKeyEvent('Tab', false);
        assert.equal(trapTabKey(emptyEvent, container, { doc, win }), true);
        assert.equal(emptyEvent.defaultPrevented, true);
        assert.equal(doc.activeElement, container);
    });

    it('queries focusables dynamically on each keydown', () => {
        const doc = createMockDocument();
        const win = createMockWindow();
        const first = createMockElement('button');
        const submit = createMockElement('button');
        const container = createMockElement('div', { children: [first, submit] });
        doc.register(container, first, submit);

        // Disable submit dynamically -> first becomes both first and last
        submit.disabled = true;
        doc.activeElement = first;
        const ev1 = createKeyEvent('Tab', false);
        assert.equal(trapTabKey(ev1, container, { doc, win }), true);
        assert.equal(doc.activeElement, first);

        // Re-enable submit and append a new button dynamically
        submit.disabled = false;
        const added = createMockElement('button');
        container.children.push(added);
        doc.register(added);

        doc.activeElement = first;
        assert.equal(trapTabKey(createKeyEvent('Tab', true), container, { doc, win }), true);
        assert.equal(doc.activeElement, added);
    });
});

describe('nested stack coordination & handleTrapKeydown', () => {
    it('allows only the topmost dialog to handle Tab and Escape', () => {
        const doc = createMockDocument();
        const win = createMockWindow();
        const stack = createFocusTrapStack();
        const modalA = createMockElement('div', { children: [createMockElement('button')] });
        const modalB = createMockElement('div', { children: [createMockElement('button')] });
        doc.register(modalA, modalB, ...modalA.children, ...modalB.children);

        stack.push('modal-a');
        stack.push('modal-b');

        let closedA = false;
        let closedB = false;

        // Modal A is NOT top -> ignores Escape and Tab
        const escA = createKeyEvent('Escape');
        assert.equal(
            handleTrapKeydown(escA, {
                container: modalA,
                isTop: stack.isTop('modal-a'),
                onEscape: () => (closedA = true),
                doc,
                win,
            }),
            false
        );
        assert.equal(closedA, false);

        // Modal B IS top -> handles Escape
        const escB = createKeyEvent('Escape');
        assert.equal(
            handleTrapKeydown(escB, {
                container: modalB,
                isTop: stack.isTop('modal-b'),
                onEscape: (e) => {
                    e.preventDefault();
                    closedB = true;
                },
                doc,
                win,
            }),
            true
        );
        assert.equal(closedB, true);
        assert.equal(escB.defaultPrevented, true);

        // After B closes, A becomes top and handles Escape
        stack.remove('modal-b');
        const escA2 = createKeyEvent('Escape');
        assert.equal(
            handleTrapKeydown(escA2, {
                container: modalA,
                isTop: stack.isTop('modal-a'),
                onEscape: () => (closedA = true),
                doc,
                win,
            }),
            true
        );
        assert.equal(closedA, true);
    });

    it('yields when Escape was already handled or a SweetAlert2 modal is open', () => {
        const doc = createMockDocument();
        const win = createMockWindow();
        const container = createMockElement('div', { children: [createMockElement('button')] });

        let called = false;
        const alreadyHandled = createKeyEvent('Escape');
        alreadyHandled.preventDefault();
        assert.equal(
            handleTrapKeydown(alreadyHandled, { container, isTop: true, onEscape: () => (called = true), doc, win }),
            false
        );
        assert.equal(called, false);

        doc.swalOpen = true;
        assert.equal(
            handleTrapKeydown(createKeyEvent('Escape'), {
                container,
                isTop: true,
                onEscape: () => (called = true),
                doc,
                win,
            }),
            false
        );
        assert.equal(called, false);
    });
});

describe('useFocusTrap composable lifecycle', () => {
    let prevDoc;
    let prevWin;
    let doc;

    beforeEach(() => {
        prevDoc = globalThis.document;
        prevWin = globalThis.window;
        doc = createMockDocument();
        globalThis.document = doc;
        globalThis.window = createMockWindow();
    });

    afterEach(() => {
        globalThis.document = prevDoc;
        globalThis.window = prevWin;
    });

    it('moves focus into dialog on open, locks scroll, traps Tab, closes on Esc, and restores opener focus', async () => {
        const opener = createMockElement('button');
        const closeBtn = createMockElement('button');
        const input = createMockElement('input');
        const saveBtn = createMockElement('button');
        const panel = createMockElement('div', { children: [closeBtn, input, saveBtn] });
        doc.register(opener, panel, closeBtn, input, saveBtn);
        doc.activeElement = opener;

        const show = ref(false);
        const panelRef = ref(panel);
        const stack = createFocusTrapStack();
        const scope = effectScope();

        scope.run(() => {
            useFocusTrap(panelRef, show, {
                stack,
                onEscape: (e) => {
                    e.preventDefault();
                    show.value = false;
                },
            });
        });

        // Open modal
        show.value = true;
        await nextTick();
        assert.equal(doc.activeElement, closeBtn);
        assert.equal(doc.body.classList.contains('overflow-hidden'), true);

        // Tab from last wraps to first
        doc.activeElement = saveBtn;
        const tabEv = doc.dispatchKeydown({ key: 'Tab', shiftKey: false });
        assert.equal(tabEv.defaultPrevented, true);
        assert.equal(doc.activeElement, closeBtn);

        // Shift+Tab from first wraps to last
        const shiftTabEv = doc.dispatchKeydown({ key: 'Tab', shiftKey: true });
        assert.equal(shiftTabEv.defaultPrevented, true);
        assert.equal(doc.activeElement, saveBtn);

        // Escape closes and returns focus to opener + unlocks body scroll
        const escEv = doc.dispatchKeydown({ key: 'Escape' });
        assert.equal(escEv.defaultPrevented, true);
        assert.equal(show.value, false);
        assert.equal(doc.activeElement, opener);
        assert.equal(doc.body.classList.contains('overflow-hidden'), false);

        scope.stop();
    });

    it('coordinates nested modals: keeps scroll locked and restores focus step by step', async () => {
        const pageBtn = createMockElement('button');
        const modalABtn = createMockElement('button');
        const modalBBtn = createMockElement('button');
        const panelA = createMockElement('div', { children: [modalABtn] });
        const panelB = createMockElement('div', { children: [modalBBtn] });
        doc.register(pageBtn, modalABtn, modalBBtn, panelA, panelB);
        doc.activeElement = pageBtn;

        const showA = ref(false);
        const showB = ref(false);
        const stack = createFocusTrapStack();
        const scope = effectScope();

        scope.run(() => {
            useFocusTrap(ref(panelA), showA, {
                stack,
                onEscape: (e) => {
                    e.preventDefault();
                    showA.value = false;
                },
            });
            useFocusTrap(ref(panelB), showB, {
                stack,
                onEscape: (e) => {
                    e.preventDefault();
                    showB.value = false;
                },
            });
        });

        showA.value = true;
        await nextTick();
        assert.equal(doc.activeElement, modalABtn);

        showB.value = true;
        await nextTick();
        assert.equal(doc.activeElement, modalBBtn);
        assert.equal(stack.size(), 2);

        // Esc closes only B; A remains open and scroll stays locked; focus returns to modalABtn
        doc.dispatchKeydown({ key: 'Escape' });
        assert.equal(showB.value, false);
        assert.equal(showA.value, true);
        assert.equal(doc.activeElement, modalABtn);
        assert.equal(doc.body.classList.contains('overflow-hidden'), true);

        // Esc now closes A; scroll unlocks; focus returns to pageBtn
        doc.dispatchKeydown({ key: 'Escape' });
        assert.equal(showA.value, false);
        assert.equal(doc.activeElement, pageBtn);
        assert.equal(doc.body.classList.contains('overflow-hidden'), false);

        scope.stop();
    });
});

describe('shared dialog base components expose required ARIA and useFocusTrap', () => {
    for (const file of [
        'Components/Common/AppModal.vue',
        'Components/Common/BottomSheet.vue',
        'Components/Common/FilterDrawer.vue',
    ]) {
        it(`${file} sets role="dialog", aria-modal="true", aria-labelledby, tabindex="-1", and uses useFocusTrap`, () => {
            const src = readComponent(file);
            assert.match(src, /role="dialog"/);
            assert.match(src, /aria-modal="true"/);
            assert.match(src, /:aria-labelledby=/);
            assert.match(src, /tabindex="-1"/);
            assert.match(src, /useId\(/);
            assert.match(src, /useFocusTrap\(/);
            assert.match(src, /:aria-label="\$t\('common\.close'\)"/);
        });
    }
});
