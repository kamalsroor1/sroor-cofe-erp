import { ref } from 'vue';
import api from '../services/api';
import { confirmDelete, confirmDialog } from '../helpers/alert';
import { trans } from '../helpers/trans';

export function useDeleteHandler() {
    const isDeleting = ref(false);

    const deleteItem = async (url, itemName = '', customWarning = '') => {
        const itemLabel = itemName || trans('common.item_default_name');
        const isConfirmed = await confirmDelete(itemLabel, customWarning);
        if (isConfirmed) {
            isDeleting.value = true;
            try {
                const res = await api.delete(url);
                return res.data;
            } finally {
                isDeleting.value = false;
            }
        }
        return false;
    };

    const confirmAndExecute = async (actionCallback, options = {}) => {
        const isConfirmed = await confirmDialog(options);
        if (isConfirmed && typeof actionCallback === 'function') {
            return actionCallback();
        }
        return false;
    };

    return {
        isDeleting,
        deleteItem,
        confirmAndExecute,
    };
}
