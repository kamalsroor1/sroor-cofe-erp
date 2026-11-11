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
        'terms' => 'Terms of Service',
        'privacy' => 'Privacy Policy',
        'dpa' => 'Data Processing Agreement (DPA)',
    ],
    'nav' => [
        'pricing' => 'Pricing',
        'signup' => 'Request a trial account',
        'discover' => 'Discover the system and its pricing',
        'terms' => 'Terms of Service',
        'privacy' => 'Privacy Policy',
        'dpa' => 'Data Processing Agreement',
        'features' => 'Features',
        'plans' => 'Plans',
        'faq' => 'FAQ',
        'login' => 'Sign in',
        'main' => 'Main navigation',
        'open_menu' => 'Open menu',
        'close_menu' => 'Close menu',
        'home' => 'Home page',
    ],
    'legal' => [
        'draft_badge' => 'Draft — requires legal review before publication',
        'version_label' => 'Document version:',
        'unavailable' => 'The document could not be loaded right now. Please try again.',
        'not_ready' => 'This document is not available yet.',
        'retry' => 'Try again',
        'back_signup' => 'Back to trial request',
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
        'hero_title' => 'Run your whole shop in one system: sales, inventory and accounts',
        'hero_subtitle' => 'A fast point of sale, stock for every branch, warehouse and delivery van, plus treasury and profit reports. Built for shops, wholesale, sold-by-weight goods and coffee roasteries. Start with a free trial and we set the account up for you.',
        'hero_badges' => [
            'languages' => 'Arabic and English',
            'devices' => 'Web, Android and Windows',
            'isolated' => 'A separate database for every shop',
        ],
        'audience' => [
            'title' => 'Who is it for?',
            'subtitle' => 'Designed for everyday selling businesses.',
            'items' => [
                'coffee' => [
                    'title' => 'Coffee shops and roasteries',
                    'body' => 'Sell coffee by weight and build blends from several beans with roast and grind options and their cost.',
                ],
                'retail' => [
                    'title' => 'Retail shops',
                    'body' => 'A barcode and touch point of sale, shifts and receipt printing.',
                ],
                'wholesale' => [
                    'title' => 'Wholesalers',
                    'body' => 'Retail and wholesale prices per item, with a statement and balance for every customer.',
                ],
                'vans' => [
                    'title' => 'Delivery vans',
                    'body' => 'Every van has its own stock: transfer goods to it from the warehouse and follow its balance.',
                ],
                'weighted' => [
                    'title' => 'Sold by weight',
                    'body' => 'Items sold by the kilo or gram, and scale-label barcodes.',
                ],
            ],
        ],
        'features' => [
            'title' => 'Everything you need to run your shop',
            'subtitle' => 'From the sales screen to profit reports, everything is connected.',
            'plan_note' => 'Some features depend on the plan. See the plans for the details of each one.',
            'groups' => [
                'pos' => [
                    'title' => 'Point of sale',
                    'body' => 'A fast sales screen that works by touch or keyboard.',
                    'items' => [
                        'barcode' => 'Barcode lookup and scale-label barcodes',
                        'quick_keys' => 'Touch screen with quick item keys',
                        'shortcuts' => 'Keyboard shortcuts for every step of a sale',
                        'multi_orders' => 'Several open orders at the same time',
                        'payments' => 'Cash, InstaPay, e-wallet, card or bank transfer',
                        'shifts' => 'Open and close shifts with a Z report',
                        'receipt' => 'Receipt printing on thermal printers',
                        'pin' => 'Quick cashier sign-in with a PIN',
                    ],
                ],
                'inventory' => [
                    'title' => 'Inventory and branches',
                    'body' => 'The balance of every item in every branch, warehouse and van.',
                    'items' => [
                        'stores' => 'Branches, warehouses and delivery vans, each with its own stock',
                        'transfers' => 'Stock transfers between branches and warehouses',
                        'movements' => 'Detailed movement history for every item',
                        'adjustments' => 'Stock adjustments',
                        'units' => 'Items by unit or by weight, with categories',
                        'imports' => 'Import items, customers and suppliers from Excel or CSV',
                    ],
                ],
                'purchases' => [
                    'title' => 'Purchases and returns',
                    'body' => 'Purchases and returns update stock and accounts.',
                    'items' => [
                        'purchases' => 'Purchase invoices from suppliers',
                        'returns' => 'Sales and purchase returns linked to the original invoice',
                        'reorder' => 'Reorder suggestions based on sales rate and stock',
                        'cancel' => 'Cancel a sales or purchase invoice',
                    ],
                ],
                'parties' => [
                    'title' => 'Customers and suppliers',
                    'body' => 'Follow balances, collections and payments.',
                    'items' => [
                        'statements' => 'Account statement for every customer and supplier',
                        'payments' => 'Collect from customers and pay suppliers',
                        'tiers' => 'Retail and wholesale prices by customer',
                        'last_price' => 'The last price a customer paid for an item',
                    ],
                ],
                'treasury' => [
                    'title' => 'Treasury and expenses',
                    'body' => 'Know where your money is, every day.',
                    'items' => [
                        'treasury' => 'Treasury summary and movements',
                        'expenses' => 'Expenses recorded by category',
                        'journal' => 'Printable daily journal',
                    ],
                ],
                'reports' => [
                    'title' => 'Reports and profit',
                    'body' => 'Clear numbers that help you decide.',
                    'items' => [
                        'profit_loss' => 'Profit and loss',
                        'items_profit' => 'Profitability per item',
                        'valuation' => 'Inventory valuation',
                        'stores' => 'Branch performance comparison',
                        'customers' => 'Sales by customer',
                        'expenses' => 'Expenses breakdown',
                    ],
                ],
                'team' => [
                    'title' => 'Users and control',
                    'body' => 'Every employee sees and does only what they are allowed to.',
                    'items' => [
                        'roles' => 'Detailed roles and permissions per user',
                        'stores_access' => 'Choose which branches each user can access',
                        'activity' => 'Activity log of operations',
                        'trash' => 'Trash with restore',
                        'telegram' => 'Telegram notifications',
                    ],
                ],
                'blender' => [
                    'title' => 'Coffee blends',
                    'body' => 'Build the customer\'s blend and sell it on the same screen.',
                    'items' => [
                        'blend' => 'Blends of several coffee beans in the percentages you set',
                        'roast_grind' => 'Choose the roast level and grind type',
                        'cost' => 'Calculate the blend cost and sell it on an invoice',
                    ],
                ],
            ],
        ],
        'platforms' => [
            'title' => 'Works on all your devices',
            'subtitle' => 'The same account and the same data on any device.',
            'items' => [
                'web' => [
                    'title' => 'In the browser',
                    'body' => 'Open the system on any computer, tablet or phone, nothing to install.',
                ],
                'android' => [
                    'title' => 'Android app',
                    'body' => 'For phones and tablets, with biometric sign-in.',
                ],
                'windows' => [
                    'title' => 'Windows app',
                    'body' => 'For the cashier computer, with direct receipt printing and cash drawer opening.',
                ],
            ],
            'download_cta' => 'Download the apps',
        ],
        'steps' => [
            'title' => 'How do you start?',
            'subtitle' => 'Three simple steps and you are ready to sell.',
            'items' => [
                'request' => [
                    'title' => 'Request a trial account',
                    'body' => 'Fill in your business details and pick a plan.',
                ],
                'setup' => [
                    'title' => 'We set up your workspace',
                    'body' => 'We review your request, prepare a private workspace with its own database, and contact you.',
                ],
                'start' => [
                    'title' => 'Start selling',
                    'body' => 'Sign in to your workspace, add your items or import them from Excel, and start selling.',
                ],
            ],
        ],
        'plans_title' => 'Plans and pricing',
        'plans_subtitle' => 'Pick the plan that fits the size of your business. You can change it later.',
        'see_all_plans' => 'See all details and prices',
        'faq' => [
            'title' => 'Frequently asked questions',
            'items' => [
                'isolation' => [
                    'question' => 'Is my shop\'s data separate from other shops?',
                    'answer' => 'Yes. Every shop has its own separate database and its own sign-in address, and no other shop can reach your data.',
                ],
                'languages' => [
                    'question' => 'Does the system support Arabic and English?',
                    'answer' => 'Yes. Every user can choose the interface language and change it at any time.',
                ],
                'devices' => [
                    'question' => 'Can I use it on a phone or tablet?',
                    'answer' => 'Yes. The system runs in the browser on any device, and there is also an Android app and a Windows app.',
                ],
                'branches' => [
                    'question' => 'Can I manage more than one branch?',
                    'answer' => 'Yes. The number of branches, warehouses and delivery vans allowed is shown on each plan.',
                ],
                'trial' => [
                    'question' => 'Is there a trial period?',
                    'answer' => 'Yes. Request a trial account and we set it up for you. The trial length is shown on each plan.',
                ],
                'change_plan' => [
                    'question' => 'Can I change my plan later?',
                    'answer' => 'Yes. You can request a plan change from the subscription page inside the system.',
                ],
            ],
        ],
        'cta_title' => 'Ready to start?',
        'cta_subtitle' => 'Leave your details and we will contact you to set up the account.',
        'support_title' => 'Contact us',
    ],
];
