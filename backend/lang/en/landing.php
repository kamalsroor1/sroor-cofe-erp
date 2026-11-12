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
        'features' => 'Features',
        'industry' => 'Solutions by business type',
        'contact' => 'Contact us',
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
        'industries' => 'Industries',
        'download' => 'Download',
        'contact' => 'Contact us',
        'locale_short' => [
            'ar' => 'ع',
            'en' => 'EN',
        ],
    ],
    'footer' => [
        'tagline' => 'Point of sale, inventory and accounts for your shop in one system.',
        'product' => 'Product',
        'industries' => 'Industries',
        'company' => 'Company',
        'platforms_label' => 'Available platforms',
        'platforms' => [
            'web' => 'Web',
            'android' => 'Android',
            'windows' => 'Windows',
        ],
        'copyright' => '© :year :name. All rights reserved.',
    ],
    'pages' => [
        'features' => [
            'kicker' => 'Features',
            'title' => 'Everything you need to run your shop',
            'subtitle' => 'From the sales screen to profit reports, everything is connected.',
        ],
        'contact' => [
            'kicker' => 'Contact us',
            'title' => 'We are here to help',
            'subtitle' => 'Call us or send us an email, or request a trial account and we will get in touch.',
        ],
        'industries' => [
            'coffee' => [
                'kicker' => 'For coffee shops and roasteries',
                'title' => 'Point of sale and inventory for coffee shops and roasteries',
                'subtitle' => 'Sell coffee by weight, make the customer\'s blend with its roast and grind, and know its cost and your margin.',
            ],
            'retail' => [
                'kicker' => 'For retail shops',
                'title' => 'A fast checkout and accurate stock for your shop',
                'subtitle' => 'Barcode and touch checkout, shifts, receipt printing, and stock updated with every sale.',
            ],
            'wholesale' => [
                'kicker' => 'For wholesalers',
                'title' => 'Wholesale, retail and receivables in one account',
                'subtitle' => 'A retail and a wholesale price for every item, and a statement and balance for every customer.',
            ],
            'vans' => [
                'kicker' => 'For delivery vans',
                'title' => 'Every delivery van has its own stock and account',
                'subtitle' => 'Transfer goods from the warehouse to the van, and follow its balance and sales.',
            ],
        ],
    ],
    // Sample data drawn inside the decorative app-screen mockups (not real customers or figures).
    'mock' => [
        'url' => 'ibraa.app',
        'money' => 'EGP :amount',
        'kg' => ':qty kg',
        'qty_kg' => ':qty kg × :price',
        'qty_unit' => ':qty × :price',
        'percent' => ':value%',
        'products' => [
            'brazilian' => 'Brazilian coffee',
            'yemeni' => 'Yemeni coffee',
            'colombian' => 'Colombian coffee',
            'espresso_blend' => 'Espresso blend',
            'cappuccino' => 'Cappuccino',
            'latte' => 'Latte',
            'green_tea' => 'Green tea',
            'croissant' => 'Croissant',
            'cardamom' => 'Ground cardamom',
            'water' => 'Mineral water',
            'paper_cups' => '8oz paper cups',
        ],
        'pos' => [
            'search' => 'Search by name or barcode',
            'all' => 'All',
            'coffee' => 'Coffee',
            'drinks' => 'Drinks',
            'sweets' => 'Sweets',
            'order' => 'Order :number',
            'cash' => 'Cash',
            'instapay' => 'InstaPay',
            'card' => 'Visa',
            'total' => 'Total',
            'pay_print' => 'Pay and print',
        ],
        'inventory' => [
            'title' => 'Item balances',
            'all_branches' => 'All branches',
            'transfer' => 'Stock transfer',
            'item' => 'Item',
            'main_branch' => 'Main branch',
            'warehouse' => 'Warehouse',
            'van' => 'Van :number',
            'low' => 'Running low',
        ],
        'reports' => [
            'title' => 'Reports',
            'sales' => 'Sales',
            'expenses' => 'Expenses',
            'net_profit' => 'Net profit',
            'pnl' => 'Profit and loss — last 12 months',
            'items_profit' => 'Item profitability',
        ],
        'phone' => [
            'title' => 'Today\'s report',
            'today_sales' => 'Today\'s sales',
            'vs_yesterday' => '+:value% vs yesterday',
            'treasury' => 'Treasury',
            'best_sellers' => 'Best sellers',
        ],
        'blend' => [
            'title' => 'New blend',
            'roast' => 'Roast level',
            'roast_light' => 'Light',
            'roast_medium' => 'Medium',
            'roast_dark' => 'Dark',
            'grind' => 'Grind',
            'grind_espresso' => 'Espresso',
            'grind_french' => 'French press',
            'grind_turkish' => 'Turkish',
            'cost' => 'Cost',
            'sale_price' => 'Sale price',
            'add_to_invoice' => 'Add to invoice',
        ],
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
        'billing_toggle' => 'Billing cycle',
        'save_short' => 'Save :percent%',
        'save_up_to' => 'Save up to :percent%',
        'founder_banner' => 'Founder price for the first :months months - :count spots left',
        'compare_link' => 'Compare all features',
        'compare' => [
            'title' => 'Compare plans',
            'subtitle' => 'Every plan with its limits and features in detail.',
            'limits_group' => 'Limits',
            'storage' => 'Storage',
            'included' => 'Included',
            'not_included' => 'Not included',
            'picker_label' => 'Compare:',
            'pick_first' => 'First plan',
            'pick_second' => 'Second plan',
            'show_all' => 'Show all features',
            'show_less' => 'Show less',
            'units' => [
                'mb' => ':value MB',
                'gb' => ':value GB',
            ],
            'modules' => [
                'sales' => 'Sales',
                'inventory' => 'Inventory',
                'purchases' => 'Purchases',
                'finance' => 'Accounts and treasury',
                'reports' => 'Reports',
                'system' => 'Control and integrations',
                'other' => 'Other features',
            ],
        ],
        'addons' => [
            'title' => 'Add-ons for what you need',
            'subtitle' => 'Raise your plan limits or turn on a feature without changing plans.',
            'one_time' => 'one time',
            'volume_discount' => 'Lower price for larger quantities',
        ],
    ],
    'home' => [
        'hero' => [
            'eyebrow' => 'For shops, wholesalers and coffee roasteries',
            'title' => 'Your shop\'s checkout, stock and accounts… in one system',
            'subtitle' => 'Sell faster by touch or barcode, follow the stock of every branch, warehouse and van, and know your profit every day.',
            'see_plans' => 'See the plans',
        ],
        'showcase' => [
            'title' => 'Every working day, on one screen',
            'subtitle' => 'From the first invoice to the profit report, everything is connected.',
            'rows' => [
                'pos' => [
                    'kicker' => 'Point of sale',
                    'title' => 'A fast checkout by touch or barcode',
                    'body' => 'One sales screen for the whole shop: search by name or barcode, read scale labels, and print the receipt.',
                    'bullets' => [
                        'multi_orders' => 'Several open orders at the same time',
                        'payments' => 'Cash, InstaPay, mobile wallet or Visa',
                        'shifts' => 'Shifts with a Z report and PIN sign-in',
                    ],
                ],
                'inventory' => [
                    'kicker' => 'Inventory',
                    'title' => 'Your stock in every branch, warehouse and van',
                    'body' => 'Every item\'s balance everywhere, in real time, and every sale, purchase and transfer is recorded in the item\'s movements.',
                    'bullets' => [
                        'transfers' => 'Transfers between branches, warehouses and vans',
                        'units' => 'Items by the unit or by kilo and gram',
                        'reorder' => 'Reorder suggestions based on the sales rate',
                    ],
                ],
                'reports' => [
                    'kicker' => 'Accounts and reports',
                    'title' => 'Know your profit and where your money is, every day',
                    'body' => 'Treasury, expenses and customer and supplier accounts are linked to sales, so reports are ready with no extra work.',
                    'bullets' => [
                        'profit' => 'Profit and loss and per-item profitability',
                        'statements' => 'A statement for every customer and supplier',
                        'treasury' => 'Treasury, expenses and a printable daily journal',
                    ],
                ],
            ],
        ],
        'coffee' => [
            'eyebrow' => 'For roasteries and coffee shops',
            'title' => 'Own a roastery? Make the customer\'s blend and sell it on the same screen',
            'body' => 'Pick the coffee items, the share of each, the roast level and the grind, and the system costs the blend and puts it on an invoice.',
        ],
        'more' => [
            'title' => 'And there is more inside',
            'note' => 'Some features depend on the plan.',
            'items' => [
                'returns' => 'Returns linked to the invoice',
                'statements' => 'Customer and supplier statements',
                'price_tiers' => 'Retail and wholesale prices',
                'permissions' => 'Permissions per user and branch',
                'activity' => 'A log of every operation',
                'trash' => 'Recycle bin with restore',
                'telegram' => 'Telegram notifications',
                'excel' => 'Import from Excel',
                'thermal' => 'Thermal receipt printing',
                'languages' => 'Arabic and English',
            ],
        ],
        'devices' => [
            'web' => [
                'title' => 'In the browser',
                'body' => 'Nothing to install',
            ],
            'android' => [
                'title' => 'Android',
                'body' => 'Fingerprint sign-in',
            ],
            'windows' => [
                'title' => 'Windows',
                'body' => 'For the cashier PC',
            ],
        ],
        'plans_tagline' => 'Published prices, and you can change your plan later.',
        'plans_empty' => [
            'title' => 'Plans are being prepared',
            'body' => 'Request a trial account and we will contact you with the plan and price that fit your business size.',
        ],
        'hero_badges' => [
            'languages' => 'Arabic and English',
            'devices' => 'Web, Android and Windows',
            'isolated' => 'A separate database for every shop',
        ],
        'audience' => [
            'title' => 'Who is it for?',
            'label' => 'Built for',
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
                    'title' => 'Recipes',
                    'body' => 'Assemble a product from materials by ratio and sell it on the same screen.',
                    'items' => [
                        'blend' => 'Recipes of several materials in the percentages you set',
                        'roast_grind' => 'Optional preparation options to suit your business',
                        'cost' => 'Calculate the recipe cost and sell it on an invoice',
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
            'heading' => 'Start in 3 steps',
            'subtitle' => 'Three simple steps and you are ready to sell.',
            'items' => [
                'request' => [
                    'title' => 'Request a trial account',
                    'body' => 'Fill in your business details and pick a plan.',
                    'short' => 'Fill in your business details and pick a plan.',
                ],
                'setup' => [
                    'title' => 'We set up your workspace',
                    'body' => 'We review your request, prepare a private workspace with its own database, and contact you.',
                    'short' => 'A separate database for your shop, and we get in touch.',
                ],
                'start' => [
                    'title' => 'Start selling',
                    'body' => 'Sign in to your workspace, add your items or import them from Excel, and start selling.',
                    'short' => 'Add your items or import them from Excel, and go.',
                ],
            ],
        ],
        'plans_title' => 'Plans and pricing',
        'see_all_plans' => 'See all details and prices',
        'faq' => [
            'title' => 'Frequently asked questions',
            'subtitle' => 'Can\'t find your answer? Talk to us and we will reply.',
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
        'cta_subtitle' => 'Leave your details and we will contact you to set up your account.',
        'support_title' => 'Contact us',
    ],
];
