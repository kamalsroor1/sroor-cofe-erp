import { ref, watch } from 'vue';

// UX-233: one checkout button; printing is a remembered per-device choice shared by the button and F9.
const STORAGE_KEY = 'pos_print_after_save';

const read = () => {
    try {
        return localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
};

const printAfterSave = ref(read());

watch(printAfterSave, (value) => {
    try {
        localStorage.setItem(STORAGE_KEY, value ? '1' : '0');
    } catch {
        // Storage blocked (private mode): the choice simply lasts for this page load.
    }
});

export function usePrintAfterSave() {
    return { printAfterSave };
}
