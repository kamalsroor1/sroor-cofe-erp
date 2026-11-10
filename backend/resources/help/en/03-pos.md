# Quick Point of Sale (POS)

The POS cashier screen is built for maximum speed with touch screens, keyboards, and barcode scanners.

## 1. Keyboard Shortcuts & Quantity Multiplier
- **F1**: Open the keyboard shortcuts reference modal.
- **F2**: Focus the barcode and item search input immediately.
- **F4**: Search and attach a customer to the current invoice.
- **F8**: Hold (park) the current cart and recall it later.
- **F9** or **F10**: Open the payment panel and complete checkout.
- **Quick Multiplier (`3*`)**: Type a quantity followed by an asterisk like `3*` then scan a barcode or click an item to add 3 units at once.

## 2. Selling Weighed Items
- Items sold by weight (kg or gram) open the quick weight and price picker modal.
- Choose preset weights (e.g., 125g, 250g, 500g, 1kg) or enter a target amount and the system calculates the exact decimal weight automatically.

## 3. Discounts & Manager PIN Override
- Each branch configures a maximum discount percentage allowed directly for cashiers.
- If a discount exceeds the cashier limit, the system prompts for the **Manager PIN** to authorize the discount inline without leaving the sale.

## 4. Shift Gate & Payment Methods
- A sale cannot be completed until an **active cashier shift** is opened for the current branch.
- Checkout supports **Cash**, **Card**, **Digital Wallet**, **Credit (Customer Account)**, and **Split Payment** with automatic change calculation.
