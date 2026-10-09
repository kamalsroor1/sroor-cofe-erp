# Lane A Report: List Tables Migration to DataTable.vue

## Converted Tables
Converted all 15 hand-written list tables to use the shared `Components/Common/DataTable.vue` component, maintaining full feature parity (columns, sorting, pagination, empty states, and custom action slots), adding responsive mobile cards under `lg`, horizontal scroll edge shadows, coarse touch targets ≥44px, and `data-testid` attributes:

1. `backend/resources/js/Components/Customers/CustomersTable.vue`
   - Test ID: `customers-table`, `action-statement`, `action-edit`, `action-delete`
   - Custom slots: `cell-name`, `cell-current_balance`, `cell-phone`, `cell-actions`
2. `backend/resources/js/Components/Customers/CustomerStatementTable.vue`
   - Test ID: `customer-statement-table`
   - Custom slots: `cell-type`, `cell-debit`, `cell-credit`, `cell-balance`
3. `backend/resources/js/Components/Suppliers/SuppliersTable.vue`
   - Test ID: `suppliers-table`, `action-statement`, `action-pay`, `action-edit`, `action-delete`
   - Custom slots: `cell-name`, `cell-current_balance`, `cell-actions`
4. `backend/resources/js/Components/Suppliers/SupplierStatementTable.vue`
   - Test ID: `supplier-statement-table`
   - Custom slots: `cell-type`, `cell-debit`, `cell-credit`, `cell-balance`
5. `backend/resources/js/Components/Expenses/ExpensesTable.vue`
   - Test ID: `expenses-table`, `action-edit`, `action-delete`
   - Custom slots: `cell-title`, `cell-category`, `cell-amount`, `cell-payment_method`, `cell-actions`
6. `backend/resources/js/Components/Invoices/InvoicesTable.vue`
   - Test ID: `invoices-table`, `action-print`, `action-view`, `action-receipt`
   - Custom slots: `cell-invoice_number`, `cell-customer`, `cell-net_total`, `cell-status`, `cell-payment_type`, `cell-actions`
7. `backend/resources/js/Components/Purchases/PurchasesTable.vue`
   - Test ID: `purchases-table`, `action-view`, `action-delete`
   - Custom slots: `cell-purchase_number`, `cell-supplier`, `cell-total_amount`, `cell-payment_status`, `cell-actions`
8. `backend/resources/js/Components/Returns/ReturnsTable.vue`
   - Test ID: `returns-table`, `action-view`, `action-delete`
   - Custom slots: `cell-return_number`, `cell-return_type`, `cell-party_name`, `cell-total_amount`, `cell-reason`, `cell-actions`
9. `backend/resources/js/Components/StockTransfers/StockTransfersTable.vue`
   - Test ID: `transfers-table`, `action-view`
   - Custom slots: `cell-transfer_number`, `cell-from_store_name`, `cell-to_store_name`, `cell-items_count`, `cell-status`, `cell-actions`
10. `backend/resources/js/Components/StoreStocks/StoreStocksTable.vue`
    - Test ID: `store-stocks-table`
    - Custom slots: `cell-item_name`, `cell-item_code`, `cell-unit`, `cell-quantity`, `cell-min_stock_level`, `cell-cost_price`, `cell-total_valuation`, `cell-status`
11. `backend/resources/js/Components/ItemMovements/ItemMovementsTable.vue`
    - Test ID: `movements-table`
    - Custom slots: `cell-movement_type`, `cell-item_name`, `cell-quantity`, `cell-stock_after`, `cell-unit_cost`
12. `backend/resources/js/Components/SmartReorder/SmartReorderTable.vue`
    - Test ID: `smart-reorder-table`, `action-order`
    - Custom slots: `cell-item_name`, `cell-urgency`, `cell-current_stock`, `cell-suggested_quantity`, `cell-actions`
13. `backend/resources/js/Components/Trash/TrashTable.vue`
    - Test ID: `trash-table`, `action-restore`, `action-delete`
    - Custom slots: `cell-title`, `cell-subtitle`, `cell-deleted_at`, `cell-actions`
14. `backend/resources/js/Components/Users/UsersTable.vue`
    - Test ID: `users-table`, `action-edit`, `action-delete`
    - Custom slots: `cell-name`, `cell-roles`, `cell-is_active`, `cell-actions`
15. `backend/resources/js/Components/Dashboard/DashboardRecentInvoices.vue`
    - Test ID: `recent-invoices-table`, `action-view`
    - Custom slots: `cell-invoice_number`, `cell-customer`, `cell-total`, `cell-status`, `cell-actions`

## DataTable.vue Updates
- Added inline error bar for stale rows with retry button when `error && rows.length > 0`.
- Added `@page-change="$emit('page-change', $event)"` emit forward.

## Files Modified
- `backend/resources/js/Components/Common/DataTable.vue`
- `backend/resources/js/Components/Customers/CustomersTable.vue`
- `backend/resources/js/Components/Customers/CustomerStatementTable.vue`
- `backend/resources/js/Components/Suppliers/SuppliersTable.vue`
- `backend/resources/js/Components/Suppliers/SupplierStatementTable.vue`
- `backend/resources/js/Components/Expenses/ExpensesTable.vue`
- `backend/resources/js/Components/Invoices/InvoicesTable.vue`
- `backend/resources/js/Components/Purchases/PurchasesTable.vue`
- `backend/resources/js/Components/Returns/ReturnsTable.vue`
- `backend/resources/js/Components/StockTransfers/StockTransfersTable.vue`
- `backend/resources/js/Components/StoreStocks/StoreStocksTable.vue`
- `backend/resources/js/Components/ItemMovements/ItemMovementsTable.vue`
- `backend/resources/js/Components/SmartReorder/SmartReorderTable.vue`
- `backend/resources/js/Components/Trash/TrashTable.vue`
- `backend/resources/js/Components/Users/UsersTable.vue`
- `backend/resources/js/Components/Dashboard/DashboardRecentInvoices.vue`

## Lang Keys
Zero new keys needed in forbidden lang files. Existing translation keys used throughout.
