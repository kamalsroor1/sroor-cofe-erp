# Sales Returns & Refund Rules

The system protects inventory and treasury integrity by linking every sales return to its original invoice.

## 1. Mandatory Original Invoice Link
- **No unlinked returns**: Every return must reference an original sales invoice so items are refunded at their actual selling price and discount.
- The system prevents returning a quantity greater than the remaining returnable quantity on the original invoice.

## 2. Recording a Return
- Open **Returns Register** and click **New Return**, or use **Quick Return** from the Recent Sales drawer in the POS.
- Search by the original invoice number, enter the returned quantities for each line, and provide a return reason.
- Upon saving, the system restores stock to the branch warehouse and adjusts the treasury or customer credit balance inside a database transaction.
