import { onMounted, onBeforeUnmount } from 'vue';
import { focusLineField, focusedLineId } from '../../helpers/posFocus';

const isTypingTarget = (el) => {
    if (!el) return false;
    const tag = el.tagName;
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
};

/**
 * Global POS shortcuts (approved map in docs/launch-readiness/analysis/07-pos-keyboard-research.md).
 * Keys are matched on `e.code` so they work on the Arabic layout. F-keys always work; plain
 * letters are never hijacked, and everything but Esc is blocked while a modal is open.
 *
 * F1 / ?  help      F2 search     F3 customer   F4 new order   F7 discount
 * F9 / Ctrl+Enter checkout   F10 catalog   Alt+Q qty   Alt+P price   Alt+L last line   Esc close / back to search
 */
export function usePosKeyboard({
    focusSearch,
    focusCustomer,
    focusDiscount,
    createOrder,
    toggleCatalog,
    toggleHelp,
    submit,
    canSubmit,
    closeTopModal,
    isModalOpen,
    getLastLineId,
    showSuccessModal,
}) {
    const editLine = (field, { last = false } = {}) => {
        const id = (!last && focusedLineId()) || getLastLineId();
        if (id != null) focusLineField(id, field);
    };

    const swalOpen = () => !!document.querySelector('.swal2-container');

    const onKeydown = (e) => {
        // IME composition (keyCode 229) must never trigger shortcuts.
        if (e.isComposing || e.keyCode === 229) return;
        if (e.repeat && e.code !== 'Escape') return;

        if (e.code === 'Escape') {
            // A modal or SweetAlert already handled this Esc.
            if (e.defaultPrevented || swalOpen()) return;
            if (closeTopModal()) e.preventDefault();
            else if (!isTypingTarget(e.target)) focusSearch();
            return;
        }

        if (e.code === 'Enter' && showSuccessModal.value && !swalOpen()) {
            showSuccessModal.value = false;
            focusSearch();
            return;
        }

        const typing = isTypingTarget(e.target);
        if (e.code === 'Slash' && e.shiftKey && !typing && !e.ctrlKey && !e.altKey) {
            e.preventDefault();
            toggleHelp();
            return;
        }

        if (e.code === 'F1') {
            e.preventDefault();
            toggleHelp();
            return;
        }

        if (isModalOpen() || swalOpen()) return;

        if (e.code === 'F2') {
            e.preventDefault();
            focusSearch();
        } else if (e.code === 'F3') {
            e.preventDefault();
            focusCustomer();
        } else if (e.code === 'F4') {
            e.preventDefault();
            createOrder();
        } else if (e.code === 'F7') {
            // Only claim the key once a discount input exists on screen.
            if (focusDiscount()) e.preventDefault();
        } else if (e.code === 'F10') {
            e.preventDefault();
            toggleCatalog();
        } else if (e.code === 'F9' || (e.ctrlKey && (e.code === 'Enter' || e.code === 'NumpadEnter'))) {
            e.preventDefault();
            if (canSubmit()) submit(false);
        } else if (e.altKey && !e.ctrlKey && e.code === 'KeyQ') {
            e.preventDefault();
            editLine('qty');
        } else if (e.altKey && !e.ctrlKey && e.code === 'KeyP') {
            e.preventDefault();
            editLine('price');
        } else if (e.altKey && !e.ctrlKey && e.code === 'KeyL') {
            e.preventDefault();
            editLine('price', { last: true });
        }
    };

    onMounted(() => window.addEventListener('keydown', onKeydown));
    onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
}
