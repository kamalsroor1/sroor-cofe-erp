import { ref, computed, watch } from 'vue';
import api from '../../Services/api';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import { useRemoteSearch } from './useRemoteSearch';

/**
 * POS customer picker state: list, search, selection, quick-create and the POS-02 walk-in flag.
 */
export function usePosCustomer({
    selectedCustomerId,
    selectedCustomerSnapshot,
    activePriceTier,
    repriceCart,
    isOnline,
}) {
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

    // POS-09: the order keeps a snapshot of the picked customer, so a remote-search pick never falls back to walk-in.
    const selectedCustomer = computed(() => {
        const walkIn = { id: null, name: trans('pos.general_cash_customer'), phone: '', is_walk_in: true };
        if (!selectedCustomerId.value) return walkIn;
        const snapshot = selectedCustomerSnapshot.value;
        if (snapshot && snapshot.id === selectedCustomerId.value) return snapshot;
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

    const bindCustomer = (cust) => {
        selectedCustomerId.value = cust.id;
        selectedCustomerSnapshot.value = {
            id: cust.id,
            name: cust.name,
            phone: cust.phone || '',
            price_tier: cust.price_tier ?? null,
            is_walk_in: cust.is_walk_in === true,
        };
        const tier = cust.price_tier === 'wholesale' ? 'wholesale' : 'retail';
        if (tier !== activePriceTier.value) {
            activePriceTier.value = tier;
            repriceCart(tier);
        }
    };

    const selectCustomer = (cust) => {
        bindCustomer(cust);
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
                bindCustomer(newCust);
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
