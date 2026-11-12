import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useAppConfigStore } from '../../stores/appConfig';
import { useAuthStore } from '../../stores/auth';
import { useDesktopHardware } from '../useDesktopHardware';
import { useAudioFeedback } from '../useAudioFeedback';
import { useFormatters } from '../useFormatters';
import { usePosOrders } from '../usePosOrders';
import { useConnectivity } from '../useConnectivity';
import { usePosOrderFields } from './usePosOrderFields';
import { usePosCatalog } from './usePosCatalog';
import { usePosCustomer } from './usePosCustomer';
import { usePosItemSearch } from './usePosItemSearch';
import { usePosPayment } from './usePosPayment';
import { usePosCart } from './usePosCart';
import { usePosReceipt } from './usePosReceipt';
import { usePosSubmit } from './usePosSubmit';
import { usePosKeyboard } from './usePosKeyboard';
import { hasPlanFeature } from '../../helpers/planFeatures';
import { usePosRecentSales } from './usePosRecentSales';
import { usePosQuickReturn } from './usePosQuickReturn';
import { usePosShift } from './usePosShift';
import { openSheetCount } from '../useOpenSheets';

/**
 * Wires every POS composable together for the POS screen and returns what the view renders.
 */
export function usePosTerminal() {
    const authStore = useAuthStore();
    const appConfigStore = useAppConfigStore();
    const hardware = useDesktopHardware();
    const sounds = useAudioFeedback();
    const { formatMoney, formatDate, formatDateTime } = useFormatters();
    // OFFL-1: no offline queue yet (end of Phase 2) — every server write is blocked while offline.
    const { isOnline } = useConnectivity();

    const headerRef = ref(null);
    const focusSearch = () => headerRef.value?.focusSearch();
    const lastCreatedInvoice = ref(null);

    const orderApi = usePosOrders();
    const { orders, activeOrderId, activeOrder } = orderApi;
    const fields = usePosOrderFields(activeOrder);

    const customer = usePosCustomer({
        selectedCustomerId: fields.selectedCustomerId,
        selectedCustomerSnapshot: fields.selectedCustomer,
        activePriceTier: fields.activePriceTier,
        repriceCart: (tier) => cartOps.repriceCart(tier),
        isOnline,
    });
    const catalog = usePosCatalog({ authStore, customers: customer.customers });
    const payment = usePosPayment({
        fields,
        activeOrder,
        activeOrderId,
        isWalkInCustomer: customer.isWalkInCustomer,
        maxDiscountPercent: catalog.maxDiscountPercent,
    });
    const cartOps = usePosCart({
        fields,
        orderApi,
        multiPayments: payment.multiPayments,
        activePriceTier: fields.activePriceTier,
        focusSearch,
        playScanBeep: sounds.playScanBeep,
        formatMoney,
    });
    const search = usePosItemSearch({
        items: catalog.items,
        searchResults: catalog.searchResults,
        isSearching: catalog.isSearchingResults,
        addToCart: cartOps.addToCart,
        focusSearch,
        onMiss: sounds.playErrorTone,
    });
    // The grid shows the server's matches for the same text the header search holds.
    watch(search.searchTerm, (term) => catalog.setSearch(term));
    const { printLastInvoice, reprintInvoice } = usePosReceipt({
        lastCreatedInvoice,
        paymentType: fields.paymentType,
        isDesktop: hardware.isDesktop,
        printThermalReceipt: hardware.printThermalReceipt,
        openCashDrawer: hardware.openCashDrawer,
        playDrawerSound: sounds.playDrawerSound,
        appConfigStore,
        formatDate,
        formatDateTime,
    });
    const { isSubmitting, showSuccessModal, lastChangeAmount, submitInvoice } = usePosSubmit({
        fields,
        activeOrder,
        activeStore: catalog.activeStore,
        isOnline,
        buildPayment: payment.buildPayment,
        buildPayloadLines: payment.buildPayloadLines,
        saveOrders: orderApi.saveOrders,
        clearActiveOrder: orderApi.clearActiveOrder,
        focusSearch,
        printLastInvoice,
        lastCreatedInvoice,
        sounds,
        onShiftRequired: catalog.reloadShift,
    });

    const recentSales = usePosRecentSales();
    const quickReturn = usePosQuickReturn({ isOnline, onDone: recentSales.load });
    const canReturn = computed(
        () => authStore.can('returns.manage') && hasPlanFeature(appConfigStore.tenant, 'returns.manage')
    );

    // POS-07: a user with this permission may exceed the discount cap; everyone else needs a manager PIN.
    const canOverrideDiscount = computed(() => authStore.can('pos.discount.override'));

    // POS-10: shift gate, shift menu (X-report / close) and open/close calls for the active branch.
    const shift = usePosShift({
        authStore,
        activeShift: catalog.activeShift,
        requiresShift: catalog.requiresShift,
        isLoading: catalog.isLoading,
        reloadShift: catalog.reloadShift,
        isOnline,
    });

    const showShortcutsHelp = ref(false);
    const modalCloseOrder = [
        showShortcutsHelp,
        shift.showPanel,
        quickReturn.show,
        recentSales.show,
        customer.showCustomerPickerModal,
        payment.showExpensesModal,
        payment.showMultiPaymentModal,
        showSuccessModal,
        cartOps.weightPickerItem,
    ];
    usePosKeyboard({
        focusSearch,
        focusCustomer: () => (customer.showCustomerPickerModal.value = true),
        openRecentSales: recentSales.open,
        // The compact footer keeps the discount collapsed: F7 opens it first, then focuses the field.
        focusDiscount: () => {
            const input = document.querySelector('[data-pos-discount-input]');
            if (input) {
                input.focus();
                return true;
            }
            const toggle = document.querySelector('[data-pos-discount-toggle]');
            if (!toggle) return false;
            toggle.click();
            nextTick(() => document.querySelector('[data-pos-discount-input]')?.focus());
            return true;
        },
        createOrder: cartOps.handleCreateOrder,
        toggleCatalog: catalog.toggleCatalog,
        toggleHelp: () => (showShortcutsHelp.value = !showShortcutsHelp.value),
        submit: submitInvoice,
        canSubmit: () => !isSubmitting.value && fields.cart.value.length > 0,
        isModalOpen: () => openSheetCount.value > 0 || modalCloseOrder.some((flag) => flag.value),
        closeTopModal: () => {
            const open = modalCloseOrder.find((flag) => flag.value);
            if (!open) return false;
            if (open === quickReturn.show) quickReturn.close();
            else if (open === recentSales.show) recentSales.close();
            else open.value = false;
            focusSearch();
            return true;
        },
        getLastLineId: () => fields.cart.value.at(-1)?.id ?? null,
        showSuccessModal,
    });

    const cartEmpty = computed(() => fields.cart.value.length === 0);

    onMounted(() => {
        orderApi.loadOrders();
        catalog.fetchInitialData();
        nextTick(focusSearch);
    });

    return {
        authStore,
        headerRef,
        lastCreatedInvoice,
        isOnline,
        orders,
        activeOrderId,
        fields,
        customer,
        catalog,
        payment,
        cartOps,
        search,
        printLastInvoice,
        isSubmitting,
        showSuccessModal,
        lastChangeAmount,
        submitInvoice,
        cartEmpty,
        orderApi,
        showShortcutsHelp,
        recentSales,
        quickReturn,
        reprintInvoice,
        canReturn,
        canOverrideDiscount,
        shift,
    };
}
