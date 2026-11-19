import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { labelLineFromItem, saveLabelJob } from '../helpers/labelPrint';

/**
 * SHOP-04: items picked for label printing on the items list. The pick survives paging and filtering
 * (it keeps a snapshot of each picked item), and hands the final job to the label print page.
 */
export function useItemLabelSelection() {
    const router = useRouter();
    const picked = ref(new Map());
    const showLabelsModal = ref(false);
    const modalItems = ref([]);

    const selectedIds = computed(() => [...picked.value.keys()]);
    const selectedCount = computed(() => picked.value.size);

    const commit = (next) => {
        picked.value = next;
    };

    const toggleSelect = (item) => {
        const next = new Map(picked.value);
        if (next.has(item.id)) next.delete(item.id);
        else next.set(item.id, labelLineFromItem(item));
        commit(next);
    };

    const togglePage = (items, checked) => {
        const next = new Map(picked.value);
        for (const item of items) {
            if (checked) next.set(item.id, labelLineFromItem(item));
            else next.delete(item.id);
        }
        commit(next);
    };

    const clearSelection = () => commit(new Map());

    const openForSelection = () => {
        if (picked.value.size === 0) return;
        modalItems.value = [...picked.value.values()];
        showLabelsModal.value = true;
    };

    const openForItem = (item) => {
        modalItems.value = [labelLineFromItem(item)];
        showLabelsModal.value = true;
    };

    const closeLabelsModal = () => {
        showLabelsModal.value = false;
    };

    const startPrint = (job) => {
        saveLabelJob(job);
        showLabelsModal.value = false;
        router.push({ name: 'items.labels.print' });
    };

    return {
        selectedIds,
        selectedCount,
        toggleSelect,
        togglePage,
        clearSelection,
        showLabelsModal,
        modalItems,
        openForSelection,
        openForItem,
        closeLabelsModal,
        startPrint,
    };
}
