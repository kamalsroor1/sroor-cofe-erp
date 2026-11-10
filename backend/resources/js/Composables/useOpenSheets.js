import { ref } from 'vue';

// Number of BottomSheets currently open; lets screen-level keyboard shortcuts stand down while one is showing.
export const openSheetCount = ref(0);
