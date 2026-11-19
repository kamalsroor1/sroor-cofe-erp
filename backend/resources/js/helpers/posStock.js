/**
 * SHOP-01: stock check when an item is added to the POS cart, exact at scale 3.
 *
 * The server stays the authority (StockService::deductStock refuses an oversell when
 * `allow_negative_stock` is off); this only keeps the cashier from building a cart that
 * checkout would reject, and warns when the setting lets the sale go below zero.
 *
 * Returns 'ok' | 'warn' | 'block'. `allowNegative` null/undefined (setting not loaded) never blocks.
 */
import { dAdd, dCmp } from './decimal.js';

export const STOCK_OK = 'ok';
export const STOCK_WARN = 'warn';
export const STOCK_BLOCK = 'block';

export function stockCheck({ currentStock, inCart = '0', adding = '1', allowNegative = null }) {
    if (currentStock === null || currentStock === undefined || currentStock === '') return STOCK_OK;
    const wanted = dAdd(inCart ?? '0', adding ?? '0');
    if (dCmp(wanted, currentStock) <= 0) return STOCK_OK;
    return allowNegative === false ? STOCK_BLOCK : STOCK_WARN;
}
