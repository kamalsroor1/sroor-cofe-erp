<?php

declare(strict_types=1);

// PRD-22: public signed receipt page and receipt links.
return [
    'page_title' => 'Receipt :number',
    'invoice_number' => 'Invoice no.',
    'date' => 'Date',
    'time' => 'Time',
    'customer' => 'Customer',
    'item' => 'Item',
    'quantity' => 'Qty',
    'unit_price' => 'Price',
    'line_total' => 'Total',
    'subtotal' => 'Subtotal',
    'discount' => 'Discount',
    'net' => 'Net total',
    'paid' => 'Paid',
    'remaining' => 'Remaining',
    'thank_you' => 'Thank you for shopping with us.',
    'print' => 'Print',
    'notice' => 'This is a copy of your receipt. The link expires automatically.',
    'errors' => [
        'cancelled' => 'A cancelled invoice cannot be shared.',
    ],
];
