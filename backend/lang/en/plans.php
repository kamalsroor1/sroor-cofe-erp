<?php

declare(strict_types=1);

/*
| SaaS catalog (central): plan, feature and add-on names. Keys are referenced by
| plans.name_key, plan_features.name_key and addons.name_key (ENTI-1.8).
| Feature keys use underscores (pos.access -> pos_access).
*/

return [
    'prices_exclude_vat' => 'Prices exclude VAT.',

    'plans' => [
        'free' => [
            'name' => 'Trial',
            'description' => 'Free trial with every Growth feature and small limits.',
        ],
        'basic' => [
            'name' => 'Starter',
            'description' => 'For a single shop: sales, stock, purchases, treasury, shifts and returns.',
        ],
        'pro' => [
            'name' => 'Growth',
            'description' => 'For a few branches or a small wholesaler, with profit reports, reordering and transfers.',
        ],
        'enterprise' => [
            'name' => 'Business',
            'description' => 'For chains and distribution companies, with higher limits and every feature.',
        ],
    ],

    'features' => [
        'pos_access' => [
            'name' => 'Point of sale',
            'description' => 'Fast checkout with barcode and scale-barcode support and instant payment.',
        ],
        'invoices_create' => [
            'name' => 'Sales invoices',
            'description' => 'Cash and credit invoices with customer statements.',
        ],
        'invoices_edit' => [
            'name' => 'Edit and cancel invoices',
            'description' => 'Edit or cancel invoices and reverse their stock and treasury effects.',
        ],
        'whatsapp_share' => [
            'name' => 'Share invoices on WhatsApp',
            'description' => 'Send the invoice link to the customer on WhatsApp.',
        ],
        'quotations_manage' => [
            'name' => 'Quotations',
            'description' => 'Prepare customer quotations and turn them into invoices.',
        ],
        'pos_offline' => [
            'name' => 'Offline POS',
            'description' => 'Keep selling when the internet is down and sync invoices when it is back.',
        ],
        'items_manage' => [
            'name' => 'Items and stock',
            'description' => 'Manage items, prices, cost, units of measure and reorder levels.',
        ],
        'items_movements' => [
            'name' => 'Item movement card',
            'description' => 'Track incoming, outgoing and running balance for every item.',
        ],
        'transfers_manage' => [
            'name' => 'Stock transfers',
            'description' => 'Transfer goods between branches, warehouses and delivery vans.',
        ],
        'mixes_manage' => [
            'name' => 'Mix builder',
            'description' => 'Build a mix from several items by ratio or weight, cost it with its waste and sell it directly.',
        ],
        'purchases_manage' => [
            'name' => 'Purchases and suppliers',
            'description' => 'Record supplier purchase invoices and update cost and stock.',
        ],
        'purchases_reorder' => [
            'name' => 'Reorder assistant',
            'description' => 'Suggest purchase quantities from the consumption rate.',
        ],
        'expenses_manage' => [
            'name' => 'Expenses',
            'description' => 'Record and categorise expenses and pay them from the treasury.',
        ],
        'payments_manage' => [
            'name' => 'Receipts and payments',
            'description' => 'Collect customer payments and pay suppliers.',
        ],
        'shifts_manage' => [
            'name' => 'Cashier shifts',
            'description' => 'Open and close shifts and reconcile the cash drawer, shortages and overages.',
        ],
        'treasury_view' => [
            'name' => 'Treasury',
            'description' => 'Follow receipts, payments and cash on hand.',
        ],
        'returns_manage' => [
            'name' => 'Returns',
            'description' => 'Sales and purchase returns that settle stock and balances.',
        ],
        'reports_basic' => [
            'name' => 'Basic reports',
            'description' => 'Daily sales summary and shift reports.',
        ],
        'reports_advanced' => [
            'name' => 'Profit reports',
            'description' => 'Net profit, margin, cost of sales and best-selling items.',
        ],
        'reports_export' => [
            'name' => 'Report export',
            'description' => 'Export statements, stock and invoices to Excel files.',
        ],
        'audit_logs' => [
            'name' => 'Audit log',
            'description' => 'See who created, changed or cancelled every operation, and when.',
        ],
        'printing_thermal' => [
            'name' => 'Thermal printing',
            'description' => 'Print receipts directly on thermal printers.',
        ],
        'printing_a4' => [
            'name' => 'A4 printing',
            'description' => 'A4 invoices with your logo and business details.',
        ],
        'telegram_notifications' => [
            'name' => 'Telegram notifications',
            'description' => 'Send shift and sales summaries to Telegram automatically.',
        ],
        'api_access' => [
            'name' => 'Public API',
            'description' => 'Connect the system to other software through an API.',
        ],
        'custom_domain' => [
            'name' => 'Custom domain',
            'description' => 'Run the system on your own domain.',
        ],
    ],

    'addons' => [
        'store' => [
            'name' => 'Extra sales branch',
            'description' => 'One more sales branch, with one more user.',
        ],
        'warehouse' => [
            'name' => 'Extra warehouse',
            'description' => 'One more warehouse, without a point of sale.',
        ],
        'van' => [
            'name' => 'Extra delivery van',
            'description' => 'One more delivery van, with one more user for the sales rep.',
        ],
        'user' => [
            'name' => 'Extra user',
            'description' => 'One more user on the account.',
        ],
        'storage_10gb' => [
            'name' => 'Extra storage',
            'description' => 'More space for attachments, images and backups.',
        ],
        'items_5k' => [
            'name' => 'Extra items',
            'description' => 'Raise the maximum number of items.',
        ],
        'mixes' => [
            'name' => 'Mix builder',
            'description' => 'Build a mix from several items by ratio or weight, cost it with its waste and sell it directly.',
        ],
        'reports_advanced' => [
            'name' => 'Profit reports',
            'description' => 'Net profit, margin and cost of sales without upgrading your plan.',
        ],
        'audit_logs' => [
            'name' => 'Audit log',
            'description' => 'See who created, changed or cancelled every operation, and when.',
        ],
        'api_access' => [
            'name' => 'Public API',
            'description' => 'Connect the system to other software through an API.',
        ],
        'custom_domain' => [
            'name' => 'Custom domain',
            'description' => 'Run the system on your own domain.',
        ],
        'premium_support' => [
            'name' => 'Premium support',
            'description' => 'Priority replies and a dedicated account manager.',
        ],
        'onboarding' => [
            'name' => 'Onboarding and data migration',
            'description' => 'Account setup, import of items, customers and balances from Excel, and online training.',
        ],
    ],
];
