<?php

// Desktop (Electron) hardware: receipt printer test slip and the per-device cash drawer (APP-5).
return [
    'drawer_pin_label' => 'طرف توصيل درج الكاش (Pin)',
    'drawer_pin_option' => 'Pin :pin',
    'drawer_pin_hint' => 'بيتحفظ على الجهاز ده بس. أغلب الأدراج بتشتغل على pin 2، ولو الدرج ماتفتحش جرّب pin 5.',
    'drawer_pin_saved' => 'اتحفظ pin :pin للدرج على الجهاز ده.',
    'drawer_pin_save_failed' => 'تعذر حفظ pin الدرج على الجهاز ده.',
    'drawer_testing' => 'جاري فتح الدرج...',
    'drawer_test_success_escpos' => 'أمر الفتح (pin :pin) اتبعت لطابعة الإيصالات. لو الدرج ماتفتحش جرّب الـ pin التاني.',
    'drawer_test_success_driver' => 'الطابعة رفضت الأمر المباشر، فإشارة الدرج اتبعتت عن طريق تعريف الطابعة. اتأكد إن التعريف مضبوط يفتح الدرج بعد الطباعة.',
    'drawer_error_no_printer' => 'مفيش أي طابعة متعرّفة على الجهاز ده.',
    'drawer_error_printer_not_found' => 'طابعة الإيصالات المختارة مش موجودة. اختارها تاني من القايمة.',
    'drawer_error_failed' => 'تعذر فتح الدرج. راجع توصيل الطابعة وكابل الدرج.',
    'test_slip_title' => 'تجربة الطباعة الحرارية المباشرة',
    'test_slip_connection' => 'حالة الاتصال',
    'test_slip_connected' => 'متصل',
    'test_slip_paper_width' => 'عرض الورق',
    'test_slip_footer' => 'اتطبعت بصمت من برنامج سطح المكتب.',
];
