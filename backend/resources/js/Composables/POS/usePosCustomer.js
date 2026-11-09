import { ref, computed, watch } from 'vue';
import api from '../../Services/api';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import { useRemoteSearch } from './useRemoteSearch';

/**
 * POS customer picker state: list, search, selection, quick-create and the POS-02 walk-in flag.
 */
export function usePosCustomer({ selectedCustomerId, isOnline }) {
    const customers = ref([]);
    const showCustomerPickerModal = ref(false);
    const customerSearchQuery = ref('');
    const isSubmittingQuickCustomer = ref(false);

    const {
        results: remoteCustomerResults,
        isSearching: isSearchingCustomers,
        search,
    } = useRemoteSearch('/customers', (res) => res.data?.data || res.data?.customers || []);

    watch(customerSearchQuery, (newVal) => search(newVal));

    const selectedCustomer = computed(() => {
        const walkIn = { id: null, name: trans('pos.general_cash_customer'), phone: '' };
        if (!selectedCustomerId.value) return walkIn;
        return customers.value.find((c) => c.id === selectedCustomerId.value) || walkIn;
    });

    // POS-02: the generic cash customer (no customer, or the placeholder-phone row) must pay in full.
    const isWalkInCustomer = computed(
        () =>
            !selectedCustomerId.value ||
            (selectedCustomer.value.is_walk_in ?? selectedCustomer.value.phone === '0000000000') === true
    );

    const filteredCustomerList = computed(() => {
        const q = customerSearchQuery.value.trim().toLowerCase();
        if (!q) return customers.value;

        const merged = new Map();
        customers.value
            .filter((c) => (c.name && c.name.toLowerCase().includes(q)) || (c.phone && c.phone.includes(q)))
            .forEach((c) => merged.set(c.id, c));
        remoteCustomerResults.value.forEach((c) => merged.set(c.id, c));

        return Array.from(merged.values());
    });

    const selectCustomer = (cust) => {
        selectedCustomerId.value = cust.id;
        showCustomerPickerModal.value = false;
    };

    const createQuickCustomer = async ({ name, phone }) => {
        if (!isOnline.value) return;
        isSubmittingQuickCustomer.value = true;
        try {
            const res = await api.post('/customers', { name, phone });
            const newCust = res.data?.data;
            if (newCust) {
                customers.value.unshift(newCust);
                selectedCustomerId.value = newCust.id;
                showCustomerPickerModal.value = false;
            }
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: trans('common.error'),
                text: e.userMessage || trans('pos.add_customer_failed'),
            });
        } finally {
            isSubmittingQuickCustomer.value = false;
        }
    };

    return {
        customers,
        showCustomerPickerModal,
        customerSearchQuery,
        isSubmittingQuickCustomer,
        isSearchingCustomers,
        selectedCustomer,
        isWalkInCustomer,
        filteredCustomerList,
        selectCustomer,
        createQuickCustomer,
    };
}
