import { nextTick } from 'vue';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import {
    increaseLineQty,
    decreaseLineQty,
    updateLineQty,
    updateLinePrice,
    removeLine,
} from '../../helpers/posCartLines';

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
}) {
    const { cart, discountValue, additionalExpenses, cashReceived } = fields;
    const { createNewOrder, closeOrder, saveOrders } = orderApi;

    const getItemPrice = (item) => {
        if (!item) return 0;
        const retail = parseFloat(item.selling_price ?? item.price_retail ?? item.price ?? 0);
        const wholesale = parseFloat(item.min_selling_price ?? item.price_wholesale ?? retail);
        return activePriceTier.value === 'wholesale'
            ? wholesale > 0
                ? wholesale
                : retail
            : retail > 0
              ? retail
              : wholesale;
    };

    const addToCart = (item, qty = 1) => {
        playScanBeep();
        const existing = cart.value.find((c) => c.id === item.id);
        if (existing) {
            existing.quantity = parseFloat(existing.quantity) + qty;
            return;
        }
        cart.value.push({
            id: item.id,
            name: item.name,
            code: item.code,
            unit: item.unit,
            unit_price: getItemPrice(item),
            price_retail: item.price_retail,
            price_wholesale: item.price_wholesale,
            min_selling_price: item.min_selling_price,
            quantity: qty,
        });
    };

    const increaseCartItemQty = (idx) => increaseLineQty(cart.value, idx);
    const decreaseCartItemQty = (idx) => decreaseLineQty(cart.value, idx);
    const onCartQtyUpdate = (change) => updateLineQty(cart.value, change);
    const onCartPriceUpdate = (change) => updateLinePrice(cart.value, change);
    const removeFromCart = (idx) => removeLine(cart.value, idx);

    const clearCart = () => {
        if (cart.value.length === 0) return;
        cart.value = [];
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
                    total: `${formatMoney(subtotal)} ${trans('common.currency')}`,
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
        increaseCartItemQty,
        decreaseCartItemQty,
        onCartQtyUpdate,
        onCartPriceUpdate,
        removeFromCart,
        clearCart,
        handleCreateOrder,
        handleCloseOrder,
    };
}
