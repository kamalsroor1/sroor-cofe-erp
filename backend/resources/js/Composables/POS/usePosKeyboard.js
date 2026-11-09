import { onMounted, onBeforeUnmount } from 'vue';

/**
 * Global POS shortcuts: F2 search, F4 new order, F9 / Ctrl+Enter submit, F10 catalog, Enter closes success.
 */
export function usePosKeyboard({ focusSearch, createOrder, toggleCatalog, submit, canSubmit, showSuccessModal }) {
    const onKeydown = (e) => {
        if (e.key === 'F2') {
            e.preventDefault();
            focusSearch();
        } else if (e.key === 'F4') {
            e.preventDefault();
            createOrder();
        } else if (e.key === 'F10') {
            e.preventDefault();
            toggleCatalog();
        } else if (e.key === 'F9' || (e.ctrlKey && e.key === 'Enter')) {
            e.preventDefault();
            if (canSubmit()) submit(false);
        } else if (e.key === 'Enter' && showSuccessModal.value) {
            showSuccessModal.value = false;
            focusSearch();
        }
    };

    onMounted(() => window.addEventListener('keydown', onKeydown));
    onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
}
