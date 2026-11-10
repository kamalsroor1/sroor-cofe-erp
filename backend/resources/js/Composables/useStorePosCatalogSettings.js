import { ref } from 'vue';
import api from '../Services/api';
import { notifyError, notifySuccess } from '../helpers/alert';
import { trans } from '../helpers/trans';

export const CARDS_PER_ROW_OPTIONS = ['2', '3', 'auto'];

/**
 * POS catalog display of one branch: `catalog_cards_per_row` (2, 3 or auto) and `show_item_images`.
 * Loads and saves only those keys of GET/PUT /stores/{id}/pos-settings (settings.manage on the server).
 */
export function useStorePosCatalogSettings() {
    const cardsPerRow = ref('3');
    const showImages = ref(false);
    const isLoading = ref(false);
    const isSaving = ref(false);
    const loadFailed = ref(false);

    const load = async (storeId) => {
        isLoading.value = true;
        loadFailed.value = false;
        try {
            const res = await api.get(`/stores/${storeId}/pos-settings`);
            const data = res.data?.data || {};
            cardsPerRow.value = CARDS_PER_ROW_OPTIONS.includes(data.catalog_cards_per_row)
                ? data.catalog_cards_per_row
                : '3';
            showImages.value = !!data.show_item_images;
        } catch {
            loadFailed.value = true;
        } finally {
            isLoading.value = false;
        }
    };

    const save = async (storeId) => {
        isSaving.value = true;
        try {
            await api.put(`/stores/${storeId}/pos-settings`, {
                catalog_cards_per_row: cardsPerRow.value,
                show_item_images: showImages.value,
            });
            notifySuccess(trans('pos.catalog_display_saved'));
            return true;
        } catch (e) {
            if (!e.userMessage) notifyError(trans('common.error'));
            return false;
        } finally {
            isSaving.value = false;
        }
    };

    return { cardsPerRow, showImages, isLoading, isSaving, loadFailed, load, save };
}
