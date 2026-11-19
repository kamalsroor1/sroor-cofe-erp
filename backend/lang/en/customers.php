<?php

return [
    'retail' => 'Retail Customer',
    'wholesale' => 'Wholesale Customer',

    'view_statement' => 'View Statement',

    // PLAN-AUDIT: the walk-in (cash) customer is a system row; renaming it would free a plan slot.
    'walk_in_identity_locked' => 'The system walk-in (cash) customer cannot be renamed or get another phone. Add a new customer instead.',

    // SHOP-03: customer credit limit.
    'credit_limit_exceeded' => 'This sale exceeds the customer\'s credit limit (:limit :currency). Current balance :balance :currency, this sale adds :amount :currency.',
    'credit_limit_override_required' => 'A manager approval is needed to sell above the credit limit, or collect more cash now.',
    'activity_credit_limit_override' => 'Credit sale above the limit approved for customer (:customer) on invoice [:number]: limit :limit, new balance :balance',
];
