<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Item;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Expense;

class RichDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Stores / Branches
        $stores = [
            [
                'code' => 'MAIN-01',
                'name' => 'المخزن والفرع الرئيسي - العاشر من رمضان',
                'type' => 'main_warehouse',
                'phone' => '01012316954',
                'address' => 'المنطقة الصناعية الثالثة، العاشر من رمضان',
                'is_active' => true,
                'is_main' => true,
            ],
            [
                'code' => 'BR-ZAG-02',
                'name' => 'فرع التجزئة والمبيعات المباشرة - الزقازيق',
                'type' => 'retail_shop',
                'phone' => '01099887766',
                'address' => 'شارع المحطة - بجوار ميدان المنتزه، الزقازيق',
                'is_active' => true,
                'is_main' => false,
            ],
            [
                'code' => 'BR-MANS-03',
                'name' => 'مطحنة وفرع تجزئة - المنصورة',
                'type' => 'retail_shop',
                'phone' => '01033445566',
                'address' => 'شارع الجيش - أمام الجامعة، المنصورة',
                'is_active' => true,
                'is_main' => false,
            ],
            [
                'code' => 'VAN-CANAL-01',
                'name' => 'سيارة توزيع جملة 1 - خط القناة والدلتا',
                'type' => 'wholesale_van',
                'phone' => '01011223344',
                'address' => 'خط الإسماعيلية وبورسعيد والسويس',
                'is_active' => true,
                'is_main' => false,
            ],
            [
                'code' => 'VAN-CAIRO-02',
                'name' => 'سيارة توزيع جملة 2 - القاهرة الكبرى',
                'type' => 'wholesale_van',
                'phone' => '01077889900',
                'address' => 'خط التجمع والمعادي وشبرا',
                'is_active' => true,
                'is_main' => false,
            ],
        ];

        $storeModels = [];
        foreach ($stores as $st) {
            $storeModels[] = Store::updateOrCreate(['code' => $st['code']], $st);
        }

        // Assign stores to super admin user
        $admin = User::find(1);
        if ($admin) {
            $admin->stores()->sync(collect($storeModels)->pluck('id'));
        }

        // 2. Coffee Items & Raw Materials
        $items = [
            [
                'code' => 'COF-BR-01',
                'name' => 'بن برازيلي سانتوس خام (أخضر)',
                'category' => 'بن خام',
                'unit' => 'كجم',
                'cost_price' => '320.000',
                'weighted_avg_cost' => '320.000',
                'selling_price' => '380.000',
                'min_stock_level' => '100.000',
                'notes' => 'حبوب بن برازيلي خام درجة أولى ممتاز للخلطات',
            ],
            [
                'code' => 'COF-COL-02',
                'name' => 'بن كولومبي سوبريمو خام فاخر',
                'category' => 'بن خام',
                'unit' => 'كجم',
                'cost_price' => '450.000',
                'weighted_avg_cost' => '450.000',
                'selling_price' => '520.000',
                'min_stock_level' => '50.000',
                'notes' => 'سوبريمو حبة كبيرة قوام ممتاز وحمضية متزنة',
            ],
            [
                'code' => 'COF-IND-03',
                'name' => 'بن هندي روبوستا فاخر AB شيري',
                'category' => 'بن خام',
                'unit' => 'كجم',
                'cost_price' => '260.000',
                'weighted_avg_cost' => '260.000',
                'selling_price' => '310.000',
                'min_stock_level' => '150.000',
                'notes' => 'روبوستا هندي عالي الكافيين وكريمة اسبريسو كثيفة',
            ],
            [
                'code' => 'COF-ETH-04',
                'name' => 'بن إثيوبي يرجاشيف أرابيكا خام',
                'category' => 'بن خام',
                'unit' => 'كجم',
                'cost_price' => '490.000',
                'weighted_avg_cost' => '490.000',
                'selling_price' => '580.000',
                'min_stock_level' => '40.000',
                'notes' => 'نكهة زهرية وفاكهية فريدة لمحبي القهوة المختصة',
            ],
            [
                'code' => 'COF-VIE-05',
                'name' => 'بن فيتنامي روبوستا تصفية أولى',
                'category' => 'بن خام',
                'unit' => 'كجم',
                'cost_price' => '230.000',
                'weighted_avg_cost' => '230.000',
                'selling_price' => '275.000',
                'min_stock_level' => '200.000',
                'notes' => 'خامات تجارية ممتازة للقهاوي والكافيهات الشعبية',
            ],
            [
                'code' => 'COF-GUA-06',
                'name' => 'بن جواتيمالا أنتيجوا بركاني خام',
                'category' => 'بن خام',
                'unit' => 'كجم',
                'cost_price' => '470.000',
                'weighted_avg_cost' => '470.000',
                'selling_price' => '550.000',
                'min_stock_level' => '30.000',
                'notes' => 'إيحاءات شوكولاتة وتوابل خفيفة',
            ],
            [
                'code' => 'RST-BR-01',
                'name' => 'بن برازيلي محمص وسط / غامق',
                'category' => 'بن محمص',
                'unit' => 'كجم',
                'cost_price' => '390.000',
                'weighted_avg_cost' => '390.000',
                'selling_price' => '470.000',
                'min_stock_level' => '60.000',
                'notes' => 'تحميص هوائي طازج وفاخر',
            ],
            [
                'code' => 'RST-COL-02',
                'name' => 'بن كولومبي محمص ميديوم روست',
                'category' => 'بن محمص',
                'unit' => 'كجم',
                'cost_price' => '530.000',
                'weighted_avg_cost' => '530.000',
                'selling_price' => '640.000',
                'min_stock_level' => '40.000',
                'notes' => 'جاهز للطحن الفوري للتركي والفلتر',
            ],
            [
                'code' => 'BLD-SR-01',
                'name' => 'توليفة الملوك الخاصة - اسبريسو جولد',
                'category' => 'توليفات وخلطات',
                'unit' => 'كجم',
                'cost_price' => '440.000',
                'weighted_avg_cost' => '440.000',
                'selling_price' => '560.000',
                'min_stock_level' => '50.000',
                'notes' => 'توليفة 80% أرابيكا و 20% روبوستا فاخرة',
            ],
            [
                'code' => 'BLD-TURK-02',
                'name' => 'توليفة بن تركي محوج بالحبهان والمستكة',
                'category' => 'توليفات وخلطات',
                'unit' => 'كجم',
                'cost_price' => '510.000',
                'weighted_avg_cost' => '510.000',
                'selling_price' => '650.000',
                'min_stock_level' => '30.000',
                'notes' => 'توليفة الملوك - حبهان هندي جامبو ومستكة يوناني',
            ],
            [
                'code' => 'BLD-FR-03',
                'name' => 'توليفة بن فرنساوي بالبندق والشوكولاتة',
                'category' => 'توليفات وخلطات',
                'unit' => 'كجم',
                'cost_price' => '460.000',
                'weighted_avg_cost' => '460.000',
                'selling_price' => '590.000',
                'min_stock_level' => '25.000',
                'notes' => 'قهوة فرنساوي مضاف إليها كريمر ونكهات طبيعية',
            ],
            [
                'code' => 'RAW-CARD-01',
                'name' => 'حبهان هندي جامبو أخضر نمرة 1',
                'category' => 'مستلزمات وتوابل',
                'unit' => 'كجم',
                'cost_price' => '1400.000',
                'weighted_avg_cost' => '1400.000',
                'selling_price' => '1750.000',
                'min_stock_level' => '10.000',
                'notes' => 'حبهان أخضر عالي الزيوت العطرية لتحويج القهوة',
            ],
            [
                'code' => 'PKG-BAG-1K',
                'name' => 'أكياس صمام تفريغ هواء 1 كجم - فاخرة',
                'category' => 'مستلزمات وتعبئة',
                'unit' => 'دستة',
                'cost_price' => '85.000',
                'weighted_avg_cost' => '85.000',
                'selling_price' => '115.000',
                'min_stock_level' => '100.000',
                'notes' => 'أكياس متطورة بصمام باتجاه واحد لحفظ غازات القهوة',
            ],
        ];

        $itemModels = [];
        foreach ($items as $it) {
            $item = Item::updateOrCreate(['code' => $it['code']], $it);
            $item->update(['current_stock' => '650.000']);
            $itemModels[] = $item;

            // Distribute stock across all stores
            foreach ($storeModels as $store) {
                StoreStock::updateOrCreate(
                    ['store_id' => $store->id, 'item_id' => $item->id],
                    [
                        'quantity' => $store->is_main ? '350.000' : '150.000',
                        'custom_selling_price' => null,
                    ]
                );
            }
        }

        // 3. Customers
        $customers = [
            [
                'name' => 'مطاحن بن الأندلس - كفر الشيخ',
                'phone' => '01099881122',
                'address' => 'شارع الخليفة المأمون، كفر الشيخ',
                'tax_number' => 'TR-44589',
                'current_balance' => '32500.000',
                'is_active' => true,
                'notes' => 'عميل جملة منتظم - سحب أسبوعي خام ومحمص',
            ],
            [
                'name' => 'كافيه ومحمصة البارون - التجمع الخامس',
                'phone' => '01122334455',
                'address' => 'شارع التسعين الشمالي، القاهرة الجديدة',
                'tax_number' => 'TR-99214',
                'current_balance' => '14200.000',
                'is_active' => true,
                'notes' => 'سحب اسبريسو جولد وتوليفات تركي',
            ],
            [
                'name' => 'محمصة الشرق الذهبية - طنطا',
                'phone' => '01233445566',
                'address' => 'شارع البحر، طنطا',
                'tax_number' => 'TR-33120',
                'current_balance' => '21000.000',
                'is_active' => true,
                'notes' => 'عميل قديم وموثوق - سداد خلال 15 يوم',
            ],
            [
                'name' => 'قهوة المعلم رجب التراثية - شبرا',
                'phone' => '01055667788',
                'address' => 'شارع شبرا مصر، القاهرة',
                'tax_number' => null,
                'current_balance' => '4800.000',
                'is_active' => true,
                'notes' => 'سحب بن فيتنامي وبرازيلي غامق',
            ],
            [
                'name' => 'سوبر ماركت خير زمان - فرع الزقازيق',
                'phone' => '01066778899',
                'address' => 'شارع طلبة عويضة، الزقازيق',
                'tax_number' => 'TR-11450',
                'current_balance' => '8500.000',
                'is_active' => true,
                'notes' => 'توريد عبوات بن معبأة 250جم',
            ],
            [
                'name' => 'مطعم وكافيه لافازا - الإسماعيلية',
                'phone' => '01011447788',
                'address' => 'نمرة 6، الإسماعيلية',
                'tax_number' => null,
                'current_balance' => '6200.000',
                'is_active' => true,
                'notes' => 'سحب نصف شهري حبوب اسبريسو محمص',
            ],
            [
                'name' => 'عميل نقدي عام (تجزئة المطحنة)',
                'phone' => '01000000000',
                'address' => 'المبيعات المباشرة بالفرع',
                'tax_number' => null,
                'current_balance' => '0.000',
                'is_active' => true,
                'notes' => 'حساب افتراضي لمبيعات الكاش المباشرة بالصالات',
            ],
        ];

        $customerModels = [];
        foreach ($customers as $c) {
            $customerModels[] = Customer::updateOrCreate(['phone' => $c['phone']], $c);
        }

        // 4. Suppliers
        $suppliers = [
            [
                'name' => 'شركة النيل الدولية لتجارة واستيراد البن الأخضر',
                'company_name' => 'شركة النيل الدولية للبن',
                'phone' => '01001234567',
                'address' => 'ميناء الإسكندرية - المنطقة الحرة',
                'current_balance' => '85000.000',
                'is_active' => true,
                'notes' => 'مستورد رئيسي للبين البرازيلي والكولومبي والفيتنامي',
            ],
            [
                'name' => 'مجموعة الأهرام لتوريدات البن الإفريقي واللاتيني',
                'company_name' => 'الأهرام للبن والتوريدات',
                'phone' => '01098765432',
                'address' => 'العاشر من رمضان - المخازن الجمركية',
                'current_balance' => '42000.000',
                'is_active' => true,
                'notes' => 'توريد بن إثيوبي وهندي وجواتيمالا',
            ],
            [
                'name' => 'المؤسسة الهندية الدولية للتوابل والحبهان',
                'company_name' => 'المؤسسة الهندية',
                'phone' => '01223344556',
                'address' => 'العتبة - شارع الجيش، القاهرة',
                'current_balance' => '18500.000',
                'is_active' => true,
                'notes' => 'حبهان هندي جامبو ومستكة وقرنفل ومحلب',
            ],
            [
                'name' => 'مصنع المتحدة للتغليف وصمامات حفظ القهوة',
                'company_name' => 'المتحدة للتغليف',
                'phone' => '01144556677',
                'address' => 'مدينة بدر الصناعية',
                'current_balance' => '9400.000',
                'is_active' => true,
                'notes' => 'أكياس وكراتين وتعبئة وتغليف مطاحن البن',
            ],
        ];

        $supplierModels = [];
        foreach ($suppliers as $s) {
            $supplierModels[] = Supplier::updateOrCreate(['phone' => $s['phone']], $s);
        }

        // 5. Sample Invoices (Sales)
        $invoiceDates = [
            now()->toDateString(),
            now()->subDay()->toDateString(),
            now()->subDays(2)->toDateString(),
            now()->subDays(4)->toDateString(),
        ];

        foreach ($invoiceDates as $idx => $invDate) {
            $cust = $customerModels[$idx % count($customerModels)];
            $store = $storeModels[$idx % count($storeModels)];
            $item1 = $itemModels[($idx * 2) % count($itemModels)];
            $item2 = $itemModels[($idx * 2 + 1) % count($itemModels)];

            $qty1 = '10.000';
            $price1 = $item1->selling_price;
            $line1Total = bcmul($qty1, (string)$price1, 3);

            $qty2 = '5.000';
            $price2 = $item2->selling_price;
            $line2Total = bcmul($qty2, (string)$price2, 3);

            $subtotal = bcadd($line1Total, $line2Total, 3);
            $discount = '50.000';
            $netTotal = bcsub($subtotal, $discount, 3);
            $paid = $idx === 0 ? $netTotal : bcmul($netTotal, '0.6', 3);
            $remaining = bcsub($netTotal, $paid, 3);

            $invNumber = 'INV-' . strtoupper($store->code) . '-' . str_replace('-', '', $invDate) . '-' . strtoupper(substr(uniqid(), -4));

            $inv = Invoice::firstOrCreate(['invoice_number' => $invNumber], [
                'customer_id' => $cust->id,
                'user_id' => 1,
                'store_id' => $store->id,
                'invoice_date' => $invDate,
                'payment_type' => $remaining == '0.000' ? 'cash' : 'credit',
                'status' => 'confirmed',
                'payment_status' => $remaining == '0.000' ? 'paid' : 'partially_paid',
                'subtotal' => $subtotal,
                'discount_type' => 'fixed',
                'discount_value' => $discount,
                'discount_amount' => $discount,
                'net_total' => $netTotal,
                'paid_amount' => $paid,
                'remaining_amount' => $remaining,
                'total_cost' => bcadd(bcmul($qty1, (string)$item1->cost_price, 3), bcmul($qty2, (string)$item2->cost_price, 3), 3),
                'notes' => 'فاتورة مبيعات توريد خامات بن مطاحن',
            ]);

            InvoiceItem::create([
                'invoice_id' => $inv->id,
                'item_id' => $item1->id,
                'quantity' => $qty1,
                'unit_price' => $price1,
                'cost_price' => $item1->cost_price,
                'total_price' => $line1Total,
            ]);

            InvoiceItem::create([
                'invoice_id' => $inv->id,
                'item_id' => $item2->id,
                'quantity' => $qty2,
                'unit_price' => $price2,
                'cost_price' => $item2->cost_price,
                'total_price' => $line2Total,
            ]);
        }

        // 6. Payment Vouchers (Receipts & Disbursements)
        Payment::firstOrCreate(['payment_number' => 'PAY-RCV-2026-' . strtoupper(substr(uniqid(), -4))], [
            'customer_id' => $customerModels[0]->id,
            'supplier_id' => null,
            'user_id' => 1,
            'amount' => '15000.000',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'notes' => 'تحصيل نقدي دفعة من حساب مطاحن الأندلس',
        ]);

        Payment::firstOrCreate(['payment_number' => 'PAY-RCV-2026-' . strtoupper(substr(uniqid(), -4))], [
            'customer_id' => $customerModels[1]->id,
            'supplier_id' => null,
            'user_id' => 1,
            'amount' => '8000.000',
            'payment_date' => now()->subDay()->toDateString(),
            'payment_method' => 'bank_transfer',
            'notes' => 'تحويل بنكي من كافيه البارون',
        ]);

        Payment::firstOrCreate(['payment_number' => 'PAY-DSP-2026-' . strtoupper(substr(uniqid(), -4))], [
            'customer_id' => null,
            'supplier_id' => $supplierModels[0]->id,
            'user_id' => 1,
            'amount' => '25000.000',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'notes' => 'سداد دفعة نقدية لشركة النيل لاستيراد البن',
        ]);

        Payment::firstOrCreate(['payment_number' => 'PAY-DSP-2026-' . strtoupper(substr(uniqid(), -4))], [
            'customer_id' => null,
            'supplier_id' => $supplierModels[2]->id,
            'user_id' => 1,
            'amount' => '7500.000',
            'payment_date' => now()->subDay()->toDateString(),
            'payment_method' => 'cash',
            'notes' => 'سداد فاتورة حبهان هندي ومستلزمات تحويج',
        ]);

        // 7. Operational Expenses
        Expense::create([
            'expense_number' => 'EXP-2026-001',
            'category'       => 'صيانة وتشغيل',
            'user_id'        => 1,
            'store_id'       => $storeModels[0]->id,
            'amount'         => '1200.000',
            'expense_date'   => now()->toDateString(),
            'payment_method' => 'cash',
            'title'          => 'صيانة ماكينة تحميص البن الهوائية وتغيير فلاتر',
            'notes'          => 'صيانة دورية للمحمص الرئيسي',
        ]);

        Expense::create([
            'expense_number' => 'EXP-2026-002',
            'category'       => 'نقل ومحروقات',
            'user_id'        => 1,
            'store_id'       => $storeModels[3]->id,
            'amount'         => '650.000',
            'expense_date'   => now()->toDateString(),
            'payment_method' => 'cash',
            'title'          => 'سولار وبنزين سيارة توزيع الجملة 1 (خط القناة)',
            'notes'          => 'تفويلة سولار خط الإسماعيلية وبورسعيد',
        ]);
    }
}
