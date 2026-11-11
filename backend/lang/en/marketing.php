<?php

declare(strict_types=1);

/*
| Inner marketing pages (phase 2B): features, industry page (/for/:industry), download, contact.
| The feature bullets live in landing.home.features.groups and the industry headings in landing.pages.industries.
| Every sentence here must describe a shipped feature: no invented numbers, customers or testimonials.
*/

return [
    'features' => [
        'see_pricing' => 'See pricing',
        'sections_label' => 'Feature sections',
    ],

    'industry' => [
        'pains_title' => 'Designed for your daily work',
        'others_title' => 'Other businesses we serve',
        'items' => [
            'coffee' => [
                'weight' => [
                    'title' => 'Sell by the kilo and gram',
                    'body' => 'Weighted items and scale-label barcode reading.',
                ],
                'blend' => [
                    'title' => 'A customer blend in seconds',
                    'body' => 'Item shares, roast level and grind type, with the cost worked out.',
                ],
                'profit' => [
                    'title' => 'Profit per item',
                    'body' => 'A report showing what you earn on every coffee.',
                ],
            ],
            'retail' => [
                'queue' => [
                    'title' => 'Shorter queues',
                    'body' => 'Barcode search and keyboard shortcuts for every step.',
                ],
                'shift' => [
                    'title' => 'Shifts closed right',
                    'body' => 'Open and close the shift with a Z report.',
                ],
                'stock' => [
                    'title' => 'Accurate stock',
                    'body' => 'Detailed item movements and reorder suggestions.',
                ],
            ],
            'wholesale' => [
                'pricing' => [
                    'title' => 'A price for every customer',
                    'body' => 'Retail and wholesale prices, plus the last price the customer paid for the item.',
                ],
                'statements' => [
                    'title' => 'Statements ready',
                    'body' => 'A statement for every customer and supplier, with collections and payments.',
                ],
                'returns' => [
                    'title' => 'Linked returns',
                    'body' => 'Sales and purchase returns linked to the original invoice.',
                ],
            ],
            'vans' => [
                'stock' => [
                    'title' => 'Van stock',
                    'body' => 'Every van keeps its own balance.',
                ],
                'transfers' => [
                    'title' => 'Easy transfers',
                    'body' => 'Move goods from the warehouse to the van and back.',
                ],
                'mobile' => [
                    'title' => 'From the phone',
                    'body' => 'An Android app for the sales rep, with fingerprint sign-in.',
                ],
            ],
        ],
    ],

    'download' => [
        'title' => 'Get :name on your device',
        'subtitle' => 'The same account and the same data from the browser, your phone or the cashier PC.',
        'version' => 'Version',
        'size' => 'File size',
        'published' => 'Published',
        'checksum_copied' => 'Checksum copied',
        'show_checksum' => 'SHA-256 file checksum',
        'platforms' => [
            'android' => [
                'body' => 'For phones and tablets, with fingerprint sign-in.',
                'cta' => 'Download the app',
            ],
            'windows' => [
                'body' => 'For the cashier PC, with receipt printing and the cash drawer.',
                'cta' => 'Download for Windows',
            ],
        ],
        'browser' => [
            'title' => 'In the browser',
            'body' => 'Open the system from any device, nothing to install.',
            'cta' => 'Open your workspace',
        ],
    ],

    'contact' => [
        'channels_label' => 'Ways to reach us',
        'phone' => [
            'title' => 'Call support',
            'cta' => 'Call now',
            'whatsapp' => 'WhatsApp',
        ],
        'email' => [
            'title' => 'Send an email',
            'cta' => 'Send a message',
        ],
        'trial' => [
            'title' => 'Want to try it?',
            'body' => 'Tell us about your business and we will set up a trial account and get in touch.',
        ],
        'no_channels' => 'Request a trial account with your mobile number and our team will contact you.',
    ],
];
