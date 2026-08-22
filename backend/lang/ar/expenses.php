<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Expenses & Landed Costs Language Lines (مصري عام وواضح)
    |--------------------------------------------------------------------------
    */

    'title'                     => 'المصاريف والخدمات الإضافية',
    'expenses_breakdown'        => 'تفاصيل المصاريف وتكاليف الشحن والخدمات',
    'expense_item'              => 'بند المصروف / الخدمة',
    'amount'                    => 'المبلغ (ج.م)',
    'who_pays'                  => 'مين اللي هيدفع / هيتحمل المصروف؟',
    'allocation_method'         => 'طريقة توزيع التكلفة على الأصناف:',
    'notes'                     => 'ملاحظات المصروف',
    'add_expense'               => 'إضافة مصروف',
    'custom_expense'            => 'بند مخصص',

    // Who pays options (واضحة وبالمصري)
    'paid_by_customer'          => 'مضاف على حساب العميل بالفاتورة (العميل هيدفعه)',
    'paid_by_supplier'          => 'مضاف لحساب المورد بالفاتورة (المورد دفعها وهيحاسبنا)',
    'paid_by_treasury_cash'     => 'مدفوع كاش نقدًا من الخزينة (سند صرف)',
    'paid_by_treasury_instapay' => 'مدفوع عبر إنستاباي من الحساب (سند صرف)',
    'paid_by_treasury_e_wallet' => 'مدفوع من المحفظة الذكية (سند صرف)',

    // Allocation methods (طرق توزيع التكلفة)
    'alloc_by_quantity'         => 'حسب الوزن والكمية (الكمية الأكبر تشيل أكتر)',
    'alloc_by_value'            => 'حسب قيمة وسعر الصنف (الأغلى يشيل أكتر)',
    'alloc_equal'               => 'بالتساوي على أسطر الأصناف',

    // Presets
    'preset_shipping'           => 'شحن ونقل وتوصيل',
    'preset_loading'            => 'عتالة وتنزيل وشيل',
    'preset_packaging'          => 'كراتين وأكياس وتغليف',
    'preset_customs'            => 'جمارك ونولون ومصاريف طريق',
    'preset_tip'                => 'إكرامية طيار الدليفري / السواق',

    // Landed cost metrics
    'base_cost'                 => 'سعر الشراء الأساسي',
    'landed_cost'               => 'التكلفة الفعلية المحملة (بعد المصاريف)',
    'allocated_share'           => 'نصيب الصنف من المصاريف',

    // Cost Centers (مراكز التكلفة)
    'cost_center'               => 'مركز التكلفة / نوع المصروف',
    'cc_rent'                   => 'إيجارات مقرات وفروع',
    'cc_utilities'              => 'كهرباء ومياه وغاز ومرافق',
    'cc_salaries'               => 'رواتب وعمالة وإكراميات',
    'cc_vehicles'               => 'وقود وزيوت وصيانة سيارات التوزيع',
    'cc_maintenance'            => 'صيانة أجهزة ومعدات وديكورات',
    'cc_packaging'              => 'مطبوعات وكراتين وأكياس تعبئة',
    'cc_hospitality'            => 'ضيافة وبوفيه ونظافة',
    'cc_marketing'              => 'تسويق وإعلانات ودعاية',
    'cc_shipping'               => 'شحن ونولون وتوصيل خارجي',
    'cc_operational'            => 'مصاريف تشغيلية ونثريات عامة',
    'total_month'               => 'إجمالي مصروفات هذا الشهر',
    'total_cash'                => 'مصروفات مسحوبة من الدرج كاش',
    'total_filtered'            => 'إجمالي المصروفات وفق التصفية الحالية',
    'new_expense'               => 'تسجيل وقيد مصروف تشغيلي جديد 💸',
    'edit_expense'              => 'تعديل بيانات المصروف',
    'delete_confirm'            => 'هل أنت متأكد من حذف المصروف (:title)؟',
    'no_expenses'               => 'لا توجد مصروفات مسجلة مطابقة للبحث',
    'quick_category'            => 'التصنيف السريع',
    'all_cost_centers'          => 'كافة مراكز التكلفة',
    'all_payment_methods'       => 'كافة طرق الدفع',
    'search_placeholder'        => '... بحث برقم المصروف أو البيان أو التصنيف',
    'category'                  => 'التصنيف',
    'recorded_success'          => 'تم قيد المصروف في الحسابات بنجاح',
    'updated_success'           => 'تم تعديل بيانات المصروف بنجاح',
    'deleted_success'           => 'تم نقل المصروف إلى سلة المحذوفات بنجاح',
    'subtitle'                  => 'بيانات ومصروفات التشغيل، مراكز التكلفة، والنثريات',
    'total_month_expenses'      => 'إجمالي مصروفات هذا الشهر',
    'total_month_sub'           => 'إجمالي مصروفات ونثريات الشهر الحالي',
    'cash_expenses'             => 'مصروفات كاش من الدرج',
    'cash_expenses_sub'         => 'المصروفات المنصرفة نقداً من درج الكاشير',
    'filtered_total'            => 'المصروفات المحددة بالفلتر',
    'filtered_total_sub'        => 'إجمالي نتائج الفلترة والبحث الحالي',
    'quick_categories_label'    => 'تصنيفات سريعة:',
    'clear_filter'              => '✕ إلغاء الفلتر',
    'cost_center_and_category'  => 'مركز التكلفة والتصنيف',
    'total_results_count'       => 'إجمالي النتائج: :count مصروف',
    'no_expenses_found'         => 'لا توجد مصروفات مسجلة',
    'no_expenses_description'   => 'لم يتم العثور على أي مصروفات مطابقة للبحث أو الفلتر',
    'add_first_expense'         => 'إضافة أول مصروف',
    'title_placeholder'         => 'اكتب عنوان أو بيان المصروف...',
    'category_placeholder'      => 'مثال: نثريات، بوفيه، إكراميات...',
    'suggestions_label'         => 'اقتراحات:',
    'notes_placeholder'         => 'أي ملاحظات إضافية حول المصروف...',
    'expense_added'             => 'تم تسجيل المصروف بنجاح',
    'expense_updated'           => 'تم تعديل بيانات المصروف بنجاح',
    'expense_deleted'           => 'تم حذف المصروف بنجاح',
    'delete_expense_confirm_title' => 'حذف المصروف (:title)؟',
    'delete_expense_confirm_text' => 'هل أنت متأكد من حذف هذا المصروف؟',
];
