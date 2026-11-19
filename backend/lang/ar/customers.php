<?php

return [
    'retail' => 'عميل قطاعي',
    'wholesale' => 'عميل جملة',

    'view_statement' => 'عرض كشف الحساب',

    // PLAN-AUDIT: the walk-in (cash) customer is a system row; renaming it would free a plan slot.
    'walk_in_identity_locked' => 'عميل النقدي بتاع النظام مينفعش يتغير اسمه أو رقمه. ضيف عميل جديد بدل كده.',

    // SHOP-03: customer credit limit.
    'credit_limit_exceeded' => 'البيعة دي هتعدّي حد ائتمان العميل (:limit :currency). رصيده الحالي :balance :currency والبيعة هتزوّد :amount :currency.',
    'credit_limit_override_required' => 'محتاج موافقة مدير عشان تبيع فوق حد الائتمان، أو حصّل كاش أكتر دلوقتي.',
    'activity_credit_limit_override' => 'تمت الموافقة على بيع آجل فوق الحد للعميل (:customer) في الفاتورة [:number]: الحد :limit والرصيد الجديد :balance',
];
