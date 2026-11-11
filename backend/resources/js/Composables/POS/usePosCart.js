import { computed, nextTick, ref } from 'vue';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import {
    increaseLineQty,
    decreaseLineQty,
    updateLineQty,
    updateLinePrice,
    updateLineDiscount,
    removeLine,
    priceFor,
    repriceLines,
} from '../../helpers/posCartLines';
import { focusLineField } from '../../helpers/posFocus';
import { dAdd, isPositive, normalize } from '../../helpers/decimal';
import { notifyWarning } from '../../helpers/alert';
import { currentCurrencyLabel } from '../useFormatters';

// Where focus goes after a manually added item (see pos.entry_sequence in the keyboard research):
// 'scan' stays in search, 'qty' / 'price' jump to that field, 'qty_price' starts at qty then Enter moves to price.
// Stays 'scan' until a per-store setting exists.
export const DEFAULT_ENTRY_SEQUENCE = 'scan';

/**
 * Cart operations and order tabs. The cart itself lives on the active order (see usePosOrderFields).
 */
export function usePosCart({
    fields,
    orderApi,
    multiPayments,
    activePriceTier,
    focusSearch,
    playScanBeep,
    formatMoney,
    entrySequence = ref(DEFAULT_ENTRY_SEQUENCE),
}) {
    const { cart, discountValue, additionalExpenses, cashReceived } = fields;
    const { createNewOrder, closeOrder, saveOrders, activeOrder } = orderApi;
    let fallbackEntrySequence = 0;

    const nextEntrySequence = () => {
        const order = activeOrder?.value;
        if (order) {
            const current = Number.isInteger(order.lastEntrySequence)
                ? order.lastEntrySequence
                : cart.value.reduce((max, l) => Math.max(max, Number(l.entry_sequence) || 0), 0);
            order.lastEntrySequence = current + 1;
            return order.lastEntrySequence;
        }
        fallbackEntrySequence += 1;
        return fallbackEntrySequence;
    };

    const getItemPrice = (item) => priceFor(item, activePriceTier.value);

    // POS-09: re-price the open cart when a customer with another price tier is picked.
    const repriceCart = (tier) => repriceLines(cart.value, tier);

    // A scan stream never loses focus: a scanned item without a price is flagged and toasted instead.
    // A manually added item without a price opens its price field.
    const focusAfterAdd = (line, scanned) => {
        const needsPrice = !(parseFloat(line.unit_price) > 0);
        if (needsPrice && scanned) {
            notifyWarning(trans('pos.price_missing_scanned', { name: line.name }));
            return;
        }
        let field = null;
        // Touch screens would pop the on-screen keyboard after every tap, so only a missing price asks for focus.
        const touchOnly = window.matchMedia?.('(pointer: coarse)').matches;
        if (needsPrice) field = 'price';
        else if (!scanned && !touchOnly && entrySequence.value !== 'scan')
            field = entrySequence.value === 'price' ? 'price' : 'qty';
        if (!field) return;
        nextTick(() => {
            if (!focusLineField(line.id, field)) focusSearch();
        });
    };

    // POS-06: a weighted item added without a weight (tap, search, plain barcode) waits in the weight picker.
    const weightPickerItem = ref(null);
    let weightPickerScanned = false;
    const weightPickerPrice = computed(() => (weightPickerItem.value ? getItemPrice(weightPickerItem.value) : 0));

    const addLine = (item, qty, scanned) => {
        playScanBeep();
        const existing = cart.value.find((c) => c.id === item.id);
        if (existing) {
            existing.quantity = existing.is_weighted
                ? dAdd(existing.quantity, qty)
                : parseFloat(existing.quantity) + parseFloat(qty);
            focusAfterAdd(existing, scanned);
            return;
        }
        const weighted = !!item.is_weighted;
        const line = {
            id: item.id,
            entry_sequence: nextEntrySequence(),
            name: item.name,
            code: item.code,
            unit: item.unit,
            is_weighted: weighted,
            unit_price: getItemPrice(item),
            selling_price: item.selling_price,
            wholesale_price: item.wholesale_price,
            price_retail: item.price_retail,
            price_wholesale: item.price_wholesale,
            min_selling_price: item.min_selling_price,
            has_min_price: !!item.has_min_price,
            quantity: weighted ? normalize(qty) : qty,
        };
        cart.value.push(line);
        focusAfterAdd(cart.value[cart.value.length - 1], scanned);
    };

    /** `weighed` means the quantity already is the weight (scale label or a typed `n*` quantity): no picker. */
    const addToCart = (item, qty = 1, { scanned = false, weighed = false } = {}) => {
        if (item.is_weighted && !weighed) {
            weightPickerItem.value = item;
            weightPickerScanned = scanned;
            return;
        }
        addLine(item, qty, scanned);
    };

    const confirmWeight = (qty) => {
        const item = weightPickerItem.value;
        if (!item || !isPositive(qty)) return;
        weightPickerItem.value = null;
        addLine(item, qty, weightPickerScanned);
    };

    const cancelWeight = () => {
        weightPickerItem.value = null;
        focusSearch();
    };

    const increaseCartItemQty = (idx) => increaseLineQty(cart.value, idx);
    const decreaseCartItemQty = (idx) => decreaseLineQty(cart.value, idx);
    const onCartQtyUpdate = (change) => updateLineQty(cart.value, change);
    const onCartPriceUpdate = (change) => updateLinePrice(cart.value, change);
    const onCartDiscountUpdate = (change) => updateLineDiscount(cart.value, change);
    const removeFromCart = (idx) => removeLine(cart.value, idx);

    const clearCart = () => {
        if (cart.value.length === 0) return;
        cart.value = [];
        if (activeOrder?.value) activeOrder.value.lastEntrySequence = 0;
        discountValue.value = '0';
        additionalExpenses.value = [];
        multiPayments.value = [];
        cashReceived.value = '0.000';
        saveOrders();
        focusSearch();
    };

    const handleCreateOrder = () => {
        createNewOrder();
        nextTick(focusSearch);
    };

    const handleCloseOrder = async (order) => {
        if (order.cart && order.cart.length > 0) {
            const subtotal = order.cart.reduce(
                (s, i) => s + (parseFloat(i.quantity) || 0) * (parseFloat(i.unit_price) || 0),
                0
            );
            const result = await Swal.fire({
                title: trans('pos.confirm_close_order_title'),
                text: trans('pos.confirm_close_order_text', {
                    count: order.cart.length,
                    total: `${formatMoney(subtotal)} ${currentCurrencyLabel()}`,
                }),
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: trans('pos.confirm_close_order_btn'),
                cancelButtonText: trans('common.cancel'),
                confirmButtonColor: '#e11d48',
            });
            if (!result.isConfirmed) return;
        }
        closeOrder(order.id);
        focusSearch();
    };

    return {
        addToCart,
        weightPickerItem,
        weightPickerPrice,
        confirmWeight,
        cancelWeight,
        repriceCart,
        increaseCartItemQty,
        decreaseCartItemQty,
        onCartQtyUpdate,
        onCartPriceUpdate,
        onCartDiscountUpdate,
        removeFromCart,
        clearCart,
        handleCreateOrder,
        handleCloseOrder,
    };
}
