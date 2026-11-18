<?php

declare(strict_types=1);

/*
| SIGN-11..13: public self-signup with an emailed verification code (POST /api/v1/signup*).
*/

return [
    'code_sent' => 'بعتنالك كود التأكيد على الإيميل.',
    'code_resent' => 'بعتنالك كود جديد على الإيميل.',
    'verified' => 'تم تأكيد الإيميل.',

    'errors' => [
        'disabled' => 'التسجيل متوقف مؤقتًا. حاول تاني بعد شوية أو كلّم الدعم.',
        'code_invalid' => 'الكود غلط أو انتهت صلاحيته. اطلب كود جديد لو محتاج.',
        'token_invalid' => 'جلسة التسجيل دي مش موجودة أو انتهت. ابدأ التسجيل من جديد.',
        'already_verified' => 'الإيميل ده اتأكد خلاص.',
        'code_throttled' => 'استنى شوية قبل ما تطلب كود جديد.',
    ],
];
