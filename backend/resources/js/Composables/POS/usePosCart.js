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
    WEIGHT_STEP,
} from '../../helpers/posCartLines';
import { focusLineField } from '../../helpers/posFocus';
import { dAdd, dSum, isPositive, normalize } from '../../helpers/decimal';
import { lineGross } from '../../helpers/posDiscount';
import { stockCheck, STOCK_OK, STOCK_BLOCK, STOCK_WARN } from '../../helpers/posStock';
import { confirmDialog, notifyError, notifyWarning } from '../../helpers/alert';
import { currentCurrencyLabel } from '../useFormatters';
import { usePosSaleSettings } from './usePosSaleSettings';

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
    formatQty = formatMoney,
    entrySequence = ref(DEFAULT_ENTRY_SEQUENCE),
}) {
    const { cart, discountValue, additionalExpenses, cashReceived } = fields;
    const { createNewOrder, closeOrder, saveOrders, activeOrder } = orderApi;
    const { allowNegativeStock } = usePosSaleSettings();
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

    // SHOP-01: zero / short stock is refused when negative stock is off (the server refuses it too), else warned.
    const passesStockCheck = (item, qty, existing) => {
        const verdict = stockCheck({
            currentStock: item.current_stock,
            inCart: existing ? existing.quantity : '0',
            adding: qty,
            allowNegative: allowNegativeStock.value,
        });
        if (verdict === STOCK_OK) return true;
        const params = { name: item.name, qty: formatQty(item.current_stock), unit: item.unit || '' };
        if (verdict === STOCK_BLOCK) {
            notifyError(trans('pos.stock_insufficient_blocked', params));
            return false;
        }
        if (verdict === STOCK_WARN) notifyWarning(trans('pos.stock_insufficient_warning', params));
        return true;
    };

    const addLine = (item, qty, scanned) => {
        const existing = cart.value.find((c) => c.id === item.id);
        if (!passesStockCheck(item, qty, existing)) {
            focusSearch();
            return;
        }
        playScanBeep();
        if (existing) {
            existing.quantity = existing.is_weighted
                ? dAdd(existing.quantity, qty)
                : Math.round(Number(existing.quantity) + Number(qty));
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
            // SHOP-01: kept on the line so + / typed quantities are checked against the same stock.
            current_stock: item.current_stock,
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

    // SHOP-01: the + button and a typed quantity go through the same stock check as adding the item.
    const increaseCartItemQty = (idx) => {
        const line = cart.value[idx];
        if (!line) return false;
        const step = line.is_weighted ? String(WEIGHT_STEP) : '1';
        if (!passesStockCheck(line, step, line)) return false;
        return increaseLineQty(cart.value, idx);
    };
    const decreaseCartItemQty = (idx) => decreaseLineQty(cart.value, idx);
    const onCartQtyUpdate = (change) => {
        const line = cart.value[change?.index];
        if (line && isPositive(String(change.value ?? '')) && !passesStockCheck(line, String(change.value), null)) {
            return false;
        }
        return updateLineQty(cart.value, change);
    };
    const onCartPriceUpdate = (change) => updateLinePrice(cart.value, change);
    const onCartDiscountUpdate = (change) => updateLineDiscount(cart.value, change);
    const removeFromCart = (idx) => removeLine(cart.value, idx);

    // SHOP-01: one tap must not wipe a full cart.
    const clearCart = async () => {
        if (cart.value.length === 0) return;
        const confirmed = await confirmDialog({
            title: trans('pos.clear_cart'),
            text: trans('pos.clear_cart_confirm'),
            confirmButtonText: trans('pos.clear_cart'),
            cancelButtonText: trans('common.cancel'),
            isDanger: true,
        });
        if (!confirmed) {
            focusSearch();
            return;
        }
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
            const subtotal = dSum(order.cart.map(lineGross));
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
