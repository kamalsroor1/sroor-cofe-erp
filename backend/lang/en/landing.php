<?php

declare(strict_types=1);

/*
| Public marketing pages (PRD-07): labels the pricing page needs around the catalog data
| returned by GET /api/v1/public/plans. Plan, feature and add-on names come from
| lang/{ar,en}/plans.php through the API; prices are never written here.
*/

return [
    'unlimited' => 'Unlimited',
    'per_month' => 'per month',
    'per_year' => 'per year',
    'billing_monthly' => 'Monthly',
    'billing_yearly' => 'Yearly',
    'prices_exclude_vat' => 'Prices exclude VAT.',
    'founder_badge' => 'Founder price',
    'founder_slots_left' => ':count founder spots left',
    'popular' => 'Most popular',
    'trial_days' => ':count-day free trial',
    'request_workspace' => 'Request your workspace',
    'addons_title' => 'Add-ons',
    'features_title' => 'Included features',

    'limits' => [
        'users' => 'Users',
        'stores' => 'Sales branches',
        'warehouses' => 'Warehouses',
        'vans' => 'Delivery vans',
        'items' => 'Items',
        'invoices_per_month' => 'Invoices per month',
        'storage_mb' => 'Storage (MB)',
    ],

    'errors' => [
        'plans_unavailable' => 'Pricing is not available right now. Please try again shortly.',
    ],

    'request_trial' => 'Request a trial account',
    'currencies' => [
        'egp' => 'EGP',
    ],
    'page_titles' => [
        'home' => 'Sales and inventory management system',
        'pricing' => 'Pricing and plans',
        'signup' => 'Request a trial account',
    ],
    'nav' => [
        'pricing' => 'Pricing',
        'signup' => 'Request a trial account',
        'discover' => 'Discover the system and its pricing',
    ],
    'pricing' => [
        'title' => 'Choose the plan that fits your business',
        'subtitle' => 'Clear plans with published prices. You can change plan later.',
        'save_percent' => 'Save :percent% yearly',
        'founder_price_months' => 'Founder price for the first :count months',
        'plan_unavailable' => 'No plans are available right now.',
        'billing_toggle' => 'Billing cycle',
    ],
    'home' => [
        'hero_title' => 'One system for sales, inventory and accounts',
        'hero_subtitle' => 'Built for shops, wholesale, delivery vans and sold-by-weight items. Start with a free trial and we set the account up for you.',
        'points_title' => 'Why this system?',
        'points' => [
            'pos' => [
                'title' => 'Fast point of sale',
                'body' => 'A cashier screen with barcode, touch and keyboard shortcuts.',
            ],
            'inventory' => [
                'title' => 'Inventory and branches',
                'body' => 'Balances per branch and warehouse, transfers, purchases and returns.',
            ],
            'weighted' => [
                'title' => 'Sell by weight',
                'body' => 'Sell by kilo or gram with units of measurement you configure.',
            ],
            'wholesale' => [
                'title' => 'Wholesale and retail',
                'body' => 'Prices by customer tier and account statements for customers and suppliers.',
            ],
            'vans' => [
                'title' => 'Delivery vans',
                'body' => 'Transfer goods to delivery vans and follow their stock.',
            ],
            'reports' => [
                'title' => 'Reports and permissions',
                'body' => 'Sales and profit reports, with separate permissions per user.',
            ],
        ],
        'teaser_title' => 'Plans',
        'see_all_plans' => 'See all details and prices',
        'cta_title' => 'Ready to start?',
        'cta_subtitle' => 'Leave your details and we will contact you to set up the account.',
        'support_title' => 'Contact us',
    ],
];
