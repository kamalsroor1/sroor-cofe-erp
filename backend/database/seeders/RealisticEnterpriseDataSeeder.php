<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Category;
use App\Models\Item;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\CashShift;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\ReturnDocument;
use App\Models\ReturnItem;
use App\Models\StockMovement;
use App\Models\ActivityLog;

class RealisticEnterpriseDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $user = User::first() ?? User::create([
                'name' => 'المدير العام',
                'email' => 'admin@demo.com',
                'password' => bcrypt('password'),
            ]);

            // =========================================================================
            // 1. STORES & BRANCHES (الفروع والمخازن وسيارات التوزيع)
            // =========================================================================
            $storesData = [
                [
                    'code' => 'MAIN-10TH',
                    'name' => 'المخزن والفرع الرئيسي - العاشر من رمضان',
                    'type' => 'main_warehouse',
                    'phone' => '01012316954',
                    'address' => 'المنطقة الصناعية الثالثة B4، العاشر من رمضان',
                    'is_active' => true,
                    'is_main' => true,
                ],
                [
                    'code' => 'BR-ZAG-01',
                    'name' => 'فرع التجزئة والمبيعات المباشرة - الزقازيق',
                    'type' => 'retail_shop',
                    'phone' => '01099887766',
                    'address' => 'شارع المحطة - بجوار ميدان المنتزه، الزقازيق',
                    'is_active' => true,
                    'is_main' => false,
                ],
                [
                    'code' => 'BR-MANS-02',
                    'name' => 'مطحنة وفرع تجزئة - المنصورة',
                    'type' => 'retail_shop',
                    'phone' => '01033445566',
                    'address' => 'شارع الجيش - أمام بوابة الجامعة، المنصورة',
                    'is_active' => true,
                    'is_main' => false,
                ],
                [
                    'code' => 'BR-TAG-03',
                    'name' => 'فرع كافيه ومطحنة - التجمع الخامس',
                    'type' => 'retail_shop',
                    'phone' => '01122334455',
                    'address' => 'مجمع البنوك - شارع التسعين الشمالي، التجمع الخامس',
                    'is_active' => true,
                    'is_main' => false,
                ],
                [
                    'code' => 'BR-MAADI-04',
                    'name' => 'فرع ومحمصة المعادي - دجلة',
                    'type' => 'retail_shop',
                    'phone' => '01088776655',
                    'address' => 'شارع 233 - دجلة، المعادي، القاهرة',
                    'is_active' => true,
                    'is_main' => false,
                ],
                [
                    'code' => 'VAN-DELTA-01',
                    'name' => 'سيارة توزيع جملة 1 - خط الدلتا والقناة',
                    'type' => 'wholesale_van',
                    'phone' => '01011223344',
                    'address' => 'خط الإسماعيلية، بورسعيد، السويس، والشرقية',
                    'is_active' => true,
                    'is_main' => false,
                ],
                [
                    'code' => 'VAN-CAIRO-02',
                    'name' => 'سيارة توزيع جملة 2 - القاهرة الكبرى والجيزة',
                    'type' => 'wholesale_van',
                    'phone' => '01077889900',
                    'address' => 'خط التجمع، المعادي، الشيخ زايد، ووسط البلد',
                    'is_active' => true,
                    'is_main' => false,
                ],
            ];

            $stores = [];
            foreach ($storesData as $st) {
                $stores[] = Store::updateOrCreate(['code' => $st['code']], $st);
            }
            $mainStore = $stores[0];
            $user->stores()->sync(collect($stores)->pluck('id'));

            // =========================================================================
            // 2. CATEGORIES (فئات وتصنيفات واقعية)
            // =========================================================================
            $categoriesData = [
                ['name' => 'حبوب بن خام (أخضر)', 'icon' => '🌱', 'sort_order' => 1, 'is_active' => true],
                ['name' => 'بن محمص حبوب وسادة', 'icon' => '☕', 'sort_order' => 2, 'is_active' => true],
                ['name' => 'توليفات وخلطات القهوة الخاصة', 'icon' => '✨', 'sort_order' => 3, 'is_active' => true],
                ['name' => 'قهوة اسبريسو ومختصة Single Origin', 'icon' => '⚡', 'sort_order' => 4, 'is_active' => true],
                ['name' => 'قهوة تركي محوجة ومستكة', 'icon' => '👑', 'sort_order' => 5, 'is_active' => true],
                ['name' => 'قهوة فرنسية ونكهات بندق وفانيليا', 'icon' => '🌰', 'sort_order' => 6, 'is_active' => true],
                ['name' => 'حبهان وتوابل ومحوجات القهوة', 'icon' => '🌿', 'sort_order' => 7, 'is_active' => true],
                ['name' => 'مستلزمات وتعبئة وتغليف وبكجات', 'icon' => '📦', 'sort_order' => 8, 'is_active' => true],
                ['name' => 'أعشاب وشاي ومشروبات ساخنة', 'icon' => '🫖', 'sort_order' => 9, 'is_active' => true],
                ['name' => 'صوصات وسيروب ومكملات كافيهات', 'icon' => '🍯', 'sort_order' => 10, 'is_active' => true],
            ];

            $categories = [];
            foreach ($categoriesData as $cat) {
                $categories[] = Category::updateOrCreate(['name' => $cat['name']], $cat);
            }

            // =========================================================================
            // 3. SPECIALTY COFFEE ITEMS & PRODUCTS (الأصناف والأسعار الحقيقية)
            // =========================================================================
            $productsData = [
                // بن خام
                ['code' => 'COF-BR-RAW', 'name' => 'بن برازيلي سانتوس خام درجة أولى NY2', 'category_id' => $categories[0]->id, 'unit' => 'كجم', 'cost_price' => '320.000', 'selling_price' => '380.000', 'min_stock_level' => '200.000'],
                ['code' => 'COF-COL-RAW', 'name' => 'بن كولومبي سوبريمو خام فاخر شاشة 18', 'category_id' => $categories[0]->id, 'unit' => 'كجم', 'cost_price' => '450.000', 'selling_price' => '520.000', 'min_stock_level' => '100.000'],
                ['code' => 'COF-IND-RAW', 'name' => 'بن هندي روبوستا AB شيري خام ممتاز', 'category_id' => $categories[0]->id, 'unit' => 'كجم', 'cost_price' => '260.000', 'selling_price' => '310.000', 'min_stock_level' => '300.000'],
                ['code' => 'COF-ETH-RAW', 'name' => 'بن إثيوبي يرجاشيف أرابيكا خام فاخر', 'category_id' => $categories[0]->id, 'unit' => 'كجم', 'cost_price' => '490.000', 'selling_price' => '580.000', 'min_stock_level' => '80.000'],
                ['code' => 'COF-VIE-RAW', 'name' => 'بن فيتنامي روبوستا تصفية أولى شاشة 18', 'category_id' => $categories[0]->id, 'unit' => 'كجم', 'cost_price' => '230.000', 'selling_price' => '275.000', 'min_stock_level' => '400.000'],
                ['code' => 'COF-GUA-RAW', 'name' => 'بن جواتيمالا أنتيجوا بركاني خام', 'category_id' => $categories[0]->id, 'unit' => 'كجم', 'cost_price' => '470.000', 'selling_price' => '550.000', 'min_stock_level' => '60.000'],
                ['code' => 'COF-HON-RAW', 'name' => 'بن هندوراس إس إتش جي خام فاخر', 'category_id' => $categories[0]->id, 'unit' => 'كجم', 'cost_price' => '390.000', 'selling_price' => '460.000', 'min_stock_level' => '100.000'],
                ['code' => 'COF-IND-PER', 'name' => 'بن إندونيسي جافا روبوستا إكسترا خام', 'category_id' => $categories[0]->id, 'unit' => 'كجم', 'cost_price' => '275.000', 'selling_price' => '330.000', 'min_stock_level' => '150.000'],
                ['code' => 'COF-KEN-RAW', 'name' => 'بن كيني AA بلس خام حبة كبيرة', 'category_id' => $categories[0]->id, 'unit' => 'كجم', 'cost_price' => '520.000', 'selling_price' => '620.000', 'min_stock_level' => '50.000'],
                ['code' => 'COF-COS-RAW', 'name' => 'بن كوستاريكا تارازو خام مغسول', 'category_id' => $categories[0]->id, 'unit' => 'كجم', 'cost_price' => '480.000', 'selling_price' => '570.000', 'min_stock_level' => '50.000'],

                // بن محمص
                ['code' => 'RST-BR-MID', 'name' => 'بن برازيلي محمص ميديوم روست (وسط)', 'category_id' => $categories[1]->id, 'unit' => 'كجم', 'cost_price' => '380.000', 'selling_price' => '460.000', 'min_stock_level' => '100.000'],
                ['code' => 'RST-BR-DRK', 'name' => 'بن برازيلي محمص دارك روست (غامق)', 'category_id' => $categories[1]->id, 'unit' => 'كجم', 'cost_price' => '385.000', 'selling_price' => '470.000', 'min_stock_level' => '100.000'],
                ['code' => 'RST-COL-MID', 'name' => 'بن كولومبي محمص وسط هوائي طازج', 'category_id' => $categories[1]->id, 'unit' => 'كجم', 'cost_price' => '530.000', 'selling_price' => '640.000', 'min_stock_level' => '60.000'],
                ['code' => 'RST-IND-DRK', 'name' => 'بن هندي روبوستا محمص غامق كريما مكثفة', 'category_id' => $categories[1]->id, 'unit' => 'كجم', 'cost_price' => '310.000', 'selling_price' => '380.000', 'min_stock_level' => '120.000'],
                ['code' => 'RST-ETH-LGT', 'name' => 'بن إثيوبي محمص فاتح لايت روست للفلتر', 'category_id' => $categories[1]->id, 'unit' => 'كجم', 'cost_price' => '580.000', 'selling_price' => '710.000', 'min_stock_level' => '40.000'],

                // توليفات وخلطات القهوة الخاصة
                ['code' => 'BLD-SR-GLD', 'name' => 'توليفة الملوك الخاصة - اسبريسو جولد 80/20', 'category_id' => $categories[2]->id, 'unit' => 'كجم', 'cost_price' => '440.000', 'selling_price' => '560.000', 'min_stock_level' => '150.000'],
                ['code' => 'BLD-SR-PLT', 'name' => 'توليفة بلاتينيوم 100% أرابيكا سبيشالتي', 'category_id' => $categories[2]->id, 'unit' => 'كجم', 'cost_price' => '520.000', 'selling_price' => '680.000', 'min_stock_level' => '80.000'],
                ['code' => 'BLD-SR-CRB', 'name' => 'خلطة الإيطالي كريما مكثفة كافيهات', 'category_id' => $categories[2]->id, 'unit' => 'كجم', 'cost_price' => '360.000', 'selling_price' => '460.000', 'min_stock_level' => '250.000'],
                ['code' => 'BLD-SR-MOR', 'name' => 'توليفة الصباح والروقان (توليفة معتدلة)', 'category_id' => $categories[2]->id, 'unit' => 'كجم', 'cost_price' => '410.000', 'selling_price' => '510.000', 'min_stock_level' => '100.000'],

                // تركي ومحوج
                ['code' => 'TRK-PLN-LGT', 'name' => 'بن تركي سادة فاتح مطحون ناعم', 'category_id' => $categories[4]->id, 'unit' => 'كجم', 'cost_price' => '390.000', 'selling_price' => '480.000', 'min_stock_level' => '80.000'],
                ['code' => 'TRK-PLN-MID', 'name' => 'بن تركي سادة وسط مطحون طازج', 'category_id' => $categories[4]->id, 'unit' => 'كجم', 'cost_price' => '390.000', 'selling_price' => '480.000', 'min_stock_level' => '100.000'],
                ['code' => 'TRK-PLN-DRK', 'name' => 'بن تركي سادة غامق مطحون طازج', 'category_id' => $categories[4]->id, 'unit' => 'كجم', 'cost_price' => '395.000', 'selling_price' => '485.000', 'min_stock_level' => '80.000'],
                ['code' => 'TRK-SP-LGT', 'name' => 'بن تركي محوج بالحبهان والمستكة (فاتح)', 'category_id' => $categories[4]->id, 'unit' => 'كجم', 'cost_price' => '510.000', 'selling_price' => '650.000', 'min_stock_level' => '90.000'],
                ['code' => 'TRK-SP-MID', 'name' => 'بن تركي محوج بالحبهان والمستكة (وسط)', 'category_id' => $categories[4]->id, 'unit' => 'كجم', 'cost_price' => '510.000', 'selling_price' => '650.000', 'min_stock_level' => '120.000'],
                ['code' => 'TRK-SP-DRK', 'name' => 'بن تركي محوج بالحبهان والمستكة (غامق)', 'category_id' => $categories[4]->id, 'unit' => 'كجم', 'cost_price' => '515.000', 'selling_price' => '660.000', 'min_stock_level' => '90.000'],
                ['code' => 'TRK-ROYAL', 'name' => 'خلطة الباشا الملكي دبل حبهان ومستكة وزعفران', 'category_id' => $categories[4]->id, 'unit' => 'كجم', 'cost_price' => '620.000', 'selling_price' => '820.000', 'min_stock_level' => '40.000'],

                // قهوة فرنسية ونكهات
                ['code' => 'FRN-HAZEL', 'name' => 'قهوة فرنسي بالبندق والشوكولاتة بالحليب', 'category_id' => $categories[5]->id, 'unit' => 'كجم', 'cost_price' => '460.000', 'selling_price' => '590.000', 'min_stock_level' => '70.000'],
                ['code' => 'FRN-VANIL', 'name' => 'قهوة فرنسي فانيليا فرنسية بالكريمر', 'category_id' => $categories[5]->id, 'unit' => 'كجم', 'cost_price' => '450.000', 'selling_price' => '580.000', 'min_stock_level' => '50.000'],
                ['code' => 'FRN-CARAM', 'name' => 'قهوة فرنسي بالكراميل والتوفي الفاخر', 'category_id' => $categories[5]->id, 'unit' => 'كجم', 'cost_price' => '460.000', 'selling_price' => '590.000', 'min_stock_level' => '50.000'],

                // حبهان وتوابل
                ['code' => 'SP-CARD-JMB', 'name' => 'حبهان هندي جامبو أخضر نمرة 1 ممتاز', 'category_id' => $categories[6]->id, 'unit' => 'كجم', 'cost_price' => '1400.000', 'selling_price' => '1750.000', 'min_stock_level' => '20.000'],
                ['code' => 'SP-MAST-GRE', 'name' => 'مستكة يوناني حرة أصلي درجة أولى', 'category_id' => $categories[6]->id, 'unit' => 'كجم', 'cost_price' => '4500.000', 'selling_price' => '5600.000', 'min_stock_level' => '5.000'],
                ['code' => 'SP-CLV-IND', 'name' => 'قرنفل إندونيسي مسمار ممتاز', 'category_id' => $categories[6]->id, 'unit' => 'كجم', 'cost_price' => '550.000', 'selling_price' => '720.000', 'min_stock_level' => '25.000'],
                ['code' => 'SP-NUT-GRN', 'name' => 'جوزة الطيب سيلاني فاخرة حبات كاملة', 'category_id' => $categories[6]->id, 'unit' => 'كجم', 'cost_price' => '650.000', 'selling_price' => '850.000', 'min_stock_level' => '15.000'],

                // تعبئة وتغليف
                ['code' => 'PKG-BAG-250', 'name' => 'أكياس كرافت بصمام تفريغ 250جم فاخرة', 'category_id' => $categories[7]->id, 'unit' => 'دستة', 'cost_price' => '45.000', 'selling_price' => '65.000', 'min_stock_level' => '200.000'],
                ['code' => 'PKG-BAG-500', 'name' => 'أكياس كرافت بصمام تفريغ 500جم فاخرة', 'category_id' => $categories[7]->id, 'unit' => 'دستة', 'cost_price' => '65.000', 'selling_price' => '90.000', 'min_stock_level' => '150.000'],
                ['code' => 'PKG-BAG-1KG', 'name' => 'أكياس كرافت بصمام تفريغ 1كجم فاخرة', 'category_id' => $categories[7]->id, 'unit' => 'دستة', 'cost_price' => '85.000', 'selling_price' => '115.000', 'min_stock_level' => '150.000'],
                ['code' => 'PKG-CUP-4OZ', 'name' => 'أكواب اسبريسو ورقية دبل وول 4 أونص (كرتونة 1000كوب)', 'category_id' => $categories[7]->id, 'unit' => 'كرتونة', 'cost_price' => '420.000', 'selling_price' => '540.000', 'min_stock_level' => '50.000'],
                ['code' => 'PKG-CUP-8OZ', 'name' => 'أكواب ورقية دبل وول 8 أونص مطبوعة (كرتونة 1000كوب)', 'category_id' => $categories[7]->id, 'unit' => 'كرتونة', 'cost_price' => '580.000', 'selling_price' => '720.000', 'min_stock_level' => '40.000'],
                ['code' => 'PKG-CUP-12OZ', 'name' => 'أكواب ورقية دبل وول 12 أونص مطبوعة (كرتونة 1000كوب)', 'category_id' => $categories[7]->id, 'unit' => 'كرتونة', 'cost_price' => '690.000', 'selling_price' => '850.000', 'min_stock_level' => '30.000'],
            ];

            $items = [];
            foreach ($productsData as $prod) {
                $item = Item::updateOrCreate(
                    ['code' => $prod['code']],
                    array_merge($prod, [
                        'weighted_avg_cost' => $prod['cost_price'],
                        'current_stock' => '1250.000',
                        'is_active' => true,
                    ])
                );
                $items[] = $item;

                // Distribute stock across all 7 stores
                foreach ($stores as $st) {
                    $qty = $st->is_main ? '650.000' : '100.000';
                    StoreStock::updateOrCreate(
                        ['store_id' => $st->id, 'item_id' => $item->id],
                        ['quantity' => $qty, 'custom_selling_price' => null]
                    );
                }
            }

            // =========================================================================
            // 4. SUPPLIERS (الموردون وشركات الاستيراد والتجهيزات)
            // =========================================================================
            $suppliersData = [
                ['name' => 'شركة الأهرام الدولية لاستيراد البن الأخضر', 'company_name' => 'الأهرام كوفي تريدينج', 'phone' => '01001234567', 'address' => 'المنطقة الحرة، الإسكندرية', 'current_balance' => '185000.000'],
                ['name' => 'مجموعة البستاني لتجارة وتوزيع البن الإفريقي', 'company_name' => 'البستاني كوفي جروب', 'phone' => '01007654321', 'address' => 'ميناء بورسعيد، المنطقة اللوجستية', 'current_balance' => '142000.000'],
                ['name' => 'الشركة المتحدة لاستيراد البن الهندي والإندونيسي', 'company_name' => 'يونايتد كوفي إمبورت', 'phone' => '01221144778', 'address' => 'مدينة نصر - المنطقة الحرة، القاهرة', 'current_balance' => '96000.000'],
                ['name' => 'مطاحن وتجهيزات الشرق الأوسط للماكينات', 'company_name' => 'ميدل إيست روسترز', 'phone' => '01112233990', 'address' => 'العاشر من رمضان - المنطقة الصناعية A1', 'current_balance' => '45000.000'],
                ['name' => 'مصنع النصر للكرتون والأكياس والتعبئة', 'company_name' => 'النصر باك', 'phone' => '01556677889', 'address' => 'مدينة العبور - المنطقة الصناعية الثانية', 'current_balance' => '28000.000'],
                ['name' => 'شركة مصر للمحاصيل والتوابل والبهارات', 'company_name' => 'مصر للتوابل والمحوجات', 'phone' => '01099883344', 'address' => 'سوق الجملة للحبوب والتوابل، طنطا', 'current_balance' => '54000.000'],
                ['name' => 'الفيروز لمنتجات الألبان ومكملات المشروبات', 'company_name' => 'الفيروز ديري فودز', 'phone' => '01288776655', 'address' => 'مدينة السادات الصناعية', 'current_balance' => '18500.000'],
                ['name' => 'الشركة السويسرية لصوصات وسيروب الكافيهات', 'company_name' => 'سويس سيروبس إيجيبت', 'phone' => '01144556677', 'address' => 'القرية الذكية - طريق مصر إسكندرية الصحراوي', 'current_balance' => '32000.000'],
                ['name' => 'الرواد لقطع غيار ماكينات الإسبريسو والمطاحن', 'company_name' => 'الرواد تكنولوجي سبيرز', 'phone' => '01012398745', 'address' => 'شارع رمسيس - وسط البلد، القاهرة', 'current_balance' => '12500.000'],
                ['name' => 'العروبة لتجارة الشاي والأعشاب الطبيعية الفاخرة', 'company_name' => 'العروبة هيربس آند تي', 'phone' => '01234567890', 'address' => 'شارع المعز - القاهرة الفاطمية', 'current_balance' => '22000.000'],
            ];

            $suppliers = [];
            foreach ($suppliersData as $sup) {
                $suppliers[] = Supplier::updateOrCreate(['phone' => $sup['phone']], array_merge($sup, ['is_active' => true]));
            }

            // =========================================================================
            // 5. CUSTOMERS (العملاء والكافيهات وسلاسل التجزئة والمقاهي)
            // =========================================================================
            $defaultWalkIn = Customer::updateOrCreate(
                ['phone' => '01000000000'],
                [
                    'name' => 'عميل نقدي - نقطة البيع (POS Walk-in)',
                    'phone' => '01000000000',
                    'address' => 'نقطة البيع المباشرة',
                    'tax_number' => null,
                    'price_tier' => 'retail',
                    'current_balance' => '0.000',
                    'is_active' => true,
                ]
            );

            $customersData = [
                ['name' => 'مطاحن ومحمصة الأندلس الكبرى', 'phone' => '01099881122', 'address' => 'شارع الخليفة المأمون، كفر الشيخ', 'tax_number' => 'TR-44589', 'price_tier' => 'wholesale', 'current_balance' => '42500.000'],
                ['name' => 'كافيه ومحمصة البارون - التجمع الخامس', 'phone' => '01122334455', 'address' => 'شارع التسعين الشمالي، القاهرة الجديدة', 'tax_number' => 'TR-99214', 'price_tier' => 'special', 'current_balance' => '18400.000'],
                ['name' => 'محمصة الشرق الذهبية - طنطا', 'phone' => '01233445566', 'address' => 'شارع البحر، طنطا', 'tax_number' => 'TR-33120', 'price_tier' => 'wholesale', 'current_balance' => '26500.000'],
                ['name' => 'سلسلة كافيهات أروما لاونج (5 فروع)', 'phone' => '01055667788', 'address' => 'شارع الثورة - مصر الجديدة، القاهرة', 'tax_number' => 'TR-77890', 'price_tier' => 'wholesale', 'current_balance' => '58000.000'],
                ['name' => 'قهوة ومقهى المعلم رجب التراثي', 'phone' => '01066778899', 'address' => 'شارع شبرا مصر - دوران شبرا', 'tax_number' => null, 'price_tier' => 'retail', 'current_balance' => '5200.000'],
                ['name' => 'سوبر ماركت خير زمان - فرع الزقازيق', 'phone' => '01011447788', 'address' => 'شارع طلبة عويضة، الزقازيق', 'tax_number' => 'TR-11450', 'price_tier' => 'wholesale', 'current_balance' => '14200.000'],
                ['name' => 'مطعم وكافيه دي روما - الإسماعيلية', 'phone' => '01022338899', 'address' => 'شارع محمد علي - نمرة 6، الإسماعيلية', 'tax_number' => 'TR-66120', 'price_tier' => 'special', 'current_balance' => '8900.000'],
                ['name' => 'كافيه باريستا هب - المعادي', 'phone' => '01155661122', 'address' => 'شارع 9 - المعادي، القاهرة', 'tax_number' => 'TR-88190', 'price_tier' => 'special', 'current_balance' => '12600.000'],
                ['name' => 'محمصة وبن السلطان - المنصورة', 'phone' => '01277889944', 'address' => 'شارع المشاية السفلية، المنصورة', 'tax_number' => 'TR-22340', 'price_tier' => 'wholesale', 'current_balance' => '31000.000'],
                ['name' => 'هايبر ماركت المحلاوي - فرع العاشر', 'phone' => '01066554433', 'address' => 'المجاورة السادسة - سنتر الأردنية، العاشر', 'tax_number' => 'TR-55410', 'price_tier' => 'wholesale', 'current_balance' => '22500.000'],
                ['name' => 'كافيه جاردن فيو - نادي الصيد', 'phone' => '01033221199', 'address' => 'نادي الصيد - الدقي، الجيزة', 'tax_number' => null, 'price_tier' => 'special', 'current_balance' => '9400.000'],
                ['name' => 'كافتيريا مستشفى دار الفؤاد', 'phone' => '01188990011', 'address' => 'محور 26 يوليو - 6 أكتوبر', 'tax_number' => 'TR-99014', 'price_tier' => 'wholesale', 'current_balance' => '16800.000'],
                ['name' => 'مطاحن بن العروبة - الزقازيق', 'phone' => '01299001122', 'address' => 'شارع فاروق، الزقازيق', 'tax_number' => null, 'price_tier' => 'wholesale', 'current_balance' => '19500.000'],
                ['name' => 'كافيه ريفير سايد - الزمالك', 'phone' => '01044556611', 'address' => 'شارع 26 يوليو - الزمالك، القاهرة', 'tax_number' => 'TR-33980', 'price_tier' => 'special', 'current_balance' => '15200.000'],
                ['name' => 'د. حسام عبد الفتاح (عميل مميز)', 'phone' => '01011112233', 'address' => 'فيلا 14 - حي النرجس، التجمع الخامس', 'tax_number' => null, 'price_tier' => 'retail', 'current_balance' => '0.000'],
                ['name' => 'م. طارق الهواري (عميل منزلي سبيشالتي)', 'phone' => '01022223344', 'address' => 'كمبوند بالم هيلز، الشيخ زايد', 'tax_number' => null, 'price_tier' => 'retail', 'current_balance' => '0.000'],
                ['name' => 'أ. نهى الشناوي (طلبات أونلاين)', 'phone' => '01033334455', 'address' => 'عمارة 8 - شارع عباس العقاد، مدينة نصر', 'tax_number' => null, 'price_tier' => 'retail', 'current_balance' => '0.000'],
                ['name' => 'كافيه اسبريسو لاب - المهندسين', 'phone' => '01177665544', 'address' => 'شارع جامعة الدول العربية، المهندسين', 'tax_number' => 'TR-88421', 'price_tier' => 'special', 'current_balance' => '24000.000'],
                ['name' => 'محمصة بن النور - بنها', 'phone' => '01288997766', 'address' => 'شارع الأهرام، بنها، القليوبية', 'tax_number' => null, 'price_tier' => 'wholesale', 'current_balance' => '17500.000'],
                ['name' => 'كافيه لاونج 90 - السويس', 'phone' => '01055443322', 'address' => 'بورتوفيق - كورنيش السويس الجديد', 'tax_number' => null, 'price_tier' => 'retail', 'current_balance' => '7800.000'],
            ];

            $customers = [$defaultWalkIn];
            foreach ($customersData as $c) {
                $customers[] = Customer::updateOrCreate(['phone' => $c['phone']], array_merge($c, ['is_active' => true]));
            }

            // =========================================================================
            // 6. PURCHASES (فواتير المشتريات والتوريد مع حركات المخزون والمدفوعات)
            // =========================================================================
            $paymentMethods = ['bank_transfer', 'cash', 'instapay', 'check'];
            $startDate = Carbon::now()->subMonths(5);

            for ($p = 1; $p <= 45; $p++) {
                $purchaseDate = $startDate->copy()->addDays($p * 3)->addHours(rand(8, 16));
                $supplier = $suppliers[array_rand($suppliers)];
                $pNum = 'PUR-' . $purchaseDate->format('Ym') . '-' . str_pad($p, 4, '0', STR_PAD_LEFT);

                // Pick 2 to 5 random items
                $selectedItems = collect($items)->random(rand(2, 5));
                $subtotal = '0.000';
                $lineItemsData = [];

                foreach ($selectedItems as $item) {
                    $qty = sprintf('%.3f', rand(50, 400));
                    $unitCost = sprintf('%.3f', (float) $item->cost_price);
                    $lineTotal = bcmul($qty, $unitCost, 3);
                    $subtotal = bcadd($subtotal, $lineTotal, 3);

                    $lineItemsData[] = [
                        'item_id' => $item->id,
                        'quantity' => $qty,
                        'cost_price' => $unitCost,
                        'total_price' => $lineTotal,
                    ];
                }

                $discount = (rand(1, 10) > 7) ? sprintf('%.3f', rand(500, 3000)) : '0.000';
                $netTotal = bcsub($subtotal, $discount, 3);
                $isFullPaid = (rand(1, 10) > 3);
                $paidAmount = $isFullPaid ? $netTotal : sprintf('%.3f', floor(floatval($netTotal) * 0.6));
                $remainingAmount = bcsub($netTotal, $paidAmount, 3);

                $purchase = Purchase::updateOrCreate(
                    ['purchase_number' => $pNum],
                    [
                        'supplier_id' => $supplier->id,
                        'user_id' => $user->id,
                        'purchase_date' => $purchaseDate->toDateString(),
                        'status' => 'confirmed',
                        'payment_status' => ($remainingAmount == '0.000' ? 'paid' : ($paidAmount == '0.000' ? 'unpaid' : 'partially_paid')),
                        'subtotal' => $subtotal,
                        'discount_amount' => $discount,
                        'net_total' => $netTotal,
                        'paid_amount' => $paidAmount,
                        'remaining_amount' => $remainingAmount,
                        'supplier_invoice_ref' => 'SUP-INV-' . rand(10000, 99999),
                        'notes' => 'توريد شحنة بضائع وخامات للمخزن الرئيسي',
                        'created_at' => $purchaseDate,
                        'updated_at' => $purchaseDate,
                    ]
                );

                foreach ($lineItemsData as $line) {
                    PurchaseItem::updateOrCreate(
                        ['purchase_id' => $purchase->id, 'item_id' => $line['item_id']],
                        $line
                    );

                    StockMovement::create([
                        'item_id' => $line['item_id'],
                        'movement_type' => 'purchase_in',
                        'quantity' => $line['quantity'],
                        'stock_before' => '500.000',
                        'stock_after' => bcadd('500.000', $line['quantity'], 3),
                        'unit_cost' => $line['cost_price'],
                        'source_type' => Purchase::class,
                        'source_id' => $purchase->id,
                        'document_number' => $purchase->purchase_number,
                        'user_id' => $user->id,
                        'notes' => 'وارد توريد مشتريات فاتورة #' . $purchase->purchase_number,
                        'created_at' => $purchaseDate,
                    ]);
                }

                // If paid, create payment voucher
                if (floatval($paidAmount) > 0) {
                    Payment::create([
                        'payment_number' => 'PAY-OUT-' . $purchaseDate->format('Ym') . '-' . str_pad($p, 4, '0', STR_PAD_LEFT),
                        'supplier_id' => $supplier->id,
                        'purchase_id' => $purchase->id,
                        'user_id' => $user->id,
                        'amount' => $paidAmount,
                        'payment_date' => $purchaseDate->toDateString(),
                        'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                        'notes' => 'سداد دفعة توريد مشتريات فاتورة #' . $purchase->purchase_number,
                        'created_at' => $purchaseDate,
                    ]);
                }
            }

            // =========================================================================
            // 7. SALES & POS INVOICES (فواتير المبيعات ونقاط البيع اليومية)
            // =========================================================================
            $invStartDate = Carbon::now()->subDays(75);
            $totalInvoicesToCreate = 320;

            for ($i = 1; $i <= $totalInvoicesToCreate; $i++) {
                $daysOffset = floor(($i / $totalInvoicesToCreate) * 75);
                $hour = rand(8, 23);
                $minute = rand(0, 59);
                $invDate = $invStartDate->copy()->addDays($daysOffset)->setHour($hour)->setMinute($minute);
                
                $isPosSale = (rand(1, 10) <= 6); // 60% POS counter sales, 40% Wholesale / B2B delivery
                $customer = $isPosSale ? $defaultWalkIn : $customers[array_rand($customers)];
                $invNum = ($isPosSale ? 'POS-' : 'INV-') . $invDate->format('Ym') . '-' . str_pad($i, 4, '0', STR_PAD_LEFT);

                $selectedItems = collect($items)->random(rand(1, $isPosSale ? 3 : 6));
                $subtotal = '0.000';
                $totalCost = '0.000';
                $lineItemsData = [];

                foreach ($selectedItems as $item) {
                    $qty = $isPosSale ? sprintf('%.3f', rand(1, 4) * 0.5) : sprintf('%.3f', rand(5, 50));
                    $unitPrice = sprintf('%.3f', (float) $item->selling_price);
                    $unitCost = sprintf('%.3f', (float) $item->cost_price);
                    
                    $lineTotal = bcmul($qty, $unitPrice, 3);
                    $lineCost = bcmul($qty, $unitCost, 3);
                    
                    $subtotal = bcadd($subtotal, $lineTotal, 3);
                    $totalCost = bcadd($totalCost, $lineCost, 3);

                    $lineItemsData[] = [
                        'item_id' => $item->id,
                        'quantity' => $qty,
                        'cost_price' => $unitCost,
                        'unit_price' => $unitPrice,
                        'discount_amount' => '0.000',
                        'total_price' => $lineTotal,
                    ];
                }

                $discount = (rand(1, 10) > 8) ? sprintf('%.3f', rand(10, 100)) : '0.000';
                $netTotal = bcsub($subtotal, $discount, 3);

                $isFullPaid = $isPosSale || (rand(1, 10) > 3);
                $paidAmount = $isFullPaid ? $netTotal : sprintf('%.3f', floor(floatval($netTotal) * 0.5));
                $remainingAmount = bcsub($netTotal, $paidAmount, 3);

                $invoice = Invoice::updateOrCreate(
                    ['invoice_number' => $invNum],
                    [
                        'customer_id' => $customer->id,
                        'user_id' => $user->id,
                        'invoice_date' => $invDate->toDateString(),
                        'payment_type' => $isPosSale ? 'cash' : ($remainingAmount == '0.000' ? 'cash' : 'credit'),
                        'payment_method' => (rand(1, 10) > 3 ? 'cash' : 'instapay'),
                        'status' => 'confirmed',
                        'payment_status' => ($remainingAmount == '0.000' ? 'paid' : ($paidAmount == '0.000' ? 'unpaid' : 'partially_paid')),
                        'subtotal' => $subtotal,
                        'discount_type' => 'fixed',
                        'discount_value' => $discount,
                        'discount_amount' => $discount,
                        'net_total' => $netTotal,
                        'paid_amount' => $paidAmount,
                        'remaining_amount' => $remainingAmount,
                        'total_cost' => $totalCost,
                        'notes' => $isPosSale ? 'مبيعات كاشير نقطة بيع مباشرة' : 'فاتورة توريد وتوزيع جملة',
                        'created_at' => $invDate,
                        'updated_at' => $invDate,
                    ]
                );

                foreach ($lineItemsData as $line) {
                    InvoiceItem::updateOrCreate(
                        ['invoice_id' => $invoice->id, 'item_id' => $line['item_id']],
                        $line
                    );

                    StockMovement::create([
                        'item_id' => $line['item_id'],
                        'movement_type' => 'sales_out',
                        'quantity' => $line['quantity'],
                        'stock_before' => '1000.000',
                        'stock_after' => bcsub('1000.000', $line['quantity'], 3),
                        'unit_cost' => $line['cost_price'],
                        'source_type' => Invoice::class,
                        'source_id' => $invoice->id,
                        'document_number' => $invoice->invoice_number,
                        'user_id' => $user->id,
                        'notes' => 'صادر مبيعات فاتورة #' . $invoice->invoice_number,
                        'created_at' => $invDate,
                    ]);
                }

                if (floatval($paidAmount) > 0) {
                    Payment::create([
                        'payment_number' => 'PAY-IN-' . $invDate->format('Ym') . '-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                        'customer_id' => $customer->id,
                        'invoice_id' => $invoice->id,
                        'user_id' => $user->id,
                        'amount' => $paidAmount,
                        'payment_date' => $invDate->toDateString(),
                        'payment_method' => (rand(1, 10) > 3 ? 'cash' : 'instapay'),
                        'notes' => 'تحصيل قيمة مبيعات فاتورة #' . $invoice->invoice_number,
                        'created_at' => $invDate,
                    ]);
                }
            }

            // =========================================================================
            // 8. CASH SHIFTS (ورديات الكاشير وحركة الدرج)
            // =========================================================================
            for ($s = 1; $s <= 60; $s++) {
                $shiftDate = Carbon::now()->subDays(60 - $s);
                $store = $stores[array_rand($stores)];
                $openTime = $shiftDate->copy()->setHour(9)->setMinute(0);
                $closeTime = $shiftDate->copy()->setHour(23)->setMinute(30);

                $openingCash = '500.000';
                $cashSales = sprintf('%.3f', rand(3500, 14000));
                $creditSales = sprintf('%.3f', rand(1000, 6000));
                $collected = $cashSales;
                $refunds = (rand(1, 10) > 8) ? '150.000' : '0.000';
                
                $expected = bcsub(bcadd($openingCash, $collected, 3), $refunds, 3);
                $diff = (rand(1, 10) > 8) ? (rand(0, 1) ? '5.000' : '-3.000') : '0.000';
                $actual = bcadd($expected, $diff, 3);

                CashShift::create([
                    'user_id' => $user->id,
                    'shift_number' => 'SFT-' . $shiftDate->format('Ymd') . '-' . $s,
                    'status' => 'closed',
                    'opened_at' => $openTime,
                    'closed_at' => $closeTime,
                    'opening_cash_balance' => $openingCash,
                    'total_cash_sales' => $cashSales,
                    'total_credit_sales' => $creditSales,
                    'total_payments_collected' => $collected,
                    'total_refunds' => $refunds,
                    'expected_cash_balance' => $expected,
                    'actual_cash_balance' => $actual,
                    'cash_difference' => $diff,
                    'notes' => 'إغلاق وردية الكاشير المسائية - جرد مطابق',
                    'created_at' => $openTime,
                ]);
            }

            // Create ONE active open shift for today in the Main Store
            CashShift::create([
                'user_id' => $user->id,
                'shift_number' => 'SFT-' . Carbon::now()->format('Ymd') . '-LIVE',
                'status' => 'open',
                'opened_at' => Carbon::now()->startOfDay()->addHours(8),
                'closed_at' => null,
                'opening_cash_balance' => '1000.000',
                'total_cash_sales' => '4250.000',
                'total_credit_sales' => '1500.000',
                'total_payments_collected' => '4250.000',
                'total_refunds' => '0.000',
                'expected_cash_balance' => '5250.000',
                'actual_cash_balance' => '0.000',
                'cash_difference' => '0.000',
                'notes' => 'وردية اليوم الحية المفتوحة لنقطة البيع',
                'created_at' => Carbon::now(),
            ]);

            // =========================================================================
            // 9. EXPENSES (المصروفات التشغيلية ومراكز التكلفة)
            // =========================================================================
            $expenseTemplates = [
                ['title' => 'إيجار مقر ومطحنة فرع الزقازيق', 'category' => 'إيجارات', 'cost_center' => 'rent', 'amount' => '12000.000'],
                ['title' => 'إيجار فرع التجمع الخامس التجاري', 'category' => 'إيجارات', 'cost_center' => 'rent', 'amount' => '25000.000'],
                ['title' => 'إيجار مخزن العاشر من رمضان الرئيسي', 'category' => 'إيجارات', 'cost_center' => 'rent', 'amount' => '18000.000'],
                ['title' => 'فاتورة استهلاك كهرباء ومحامص البن', 'category' => 'مرافق', 'cost_center' => 'utilities', 'amount' => '4850.000'],
                ['title' => 'فاتورة مياه وفلاتر تنقية الفرع', 'category' => 'مرافق', 'cost_center' => 'utilities', 'amount' => '850.000'],
                ['title' => 'رواتب ومستحقات فريق الباريستا والعمال', 'category' => 'رواتب', 'cost_center' => 'salaries', 'amount' => '32000.000'],
                ['title' => 'سولار وبنزين سيارات التوزيع خط الدلتا', 'category' => 'سيارات ونقل', 'cost_center' => 'vehicles', 'amount' => '2400.000'],
                ['title' => 'صيانة دورية لمطحنة المحل وتغيير تروس الطحن', 'category' => 'صيانة', 'cost_center' => 'maintenance', 'amount' => '1650.000'],
                ['title' => 'كراتين شحن وأشرطة لاصقة وأكياس نايلون', 'category' => 'تعبئة', 'cost_center' => 'packaging', 'amount' => '1950.000'],
                ['title' => 'مستلزمات نظافة وتطهير ومايكروفايبر وبوفيه', 'category' => 'ضيافة ونظافة', 'cost_center' => 'hospitality', 'amount' => '680.000'],
                ['title' => 'حملة إعلانات ممولة على السوشيال ميديا', 'category' => 'تسويق', 'cost_center' => 'marketing', 'amount' => '3500.000'],
                ['title' => 'مصاريف شحن ونقل بضائع عبر شركة الشحن', 'category' => 'شحن وتوصيل', 'cost_center' => 'shipping', 'amount' => '1250.000'],
            ];

            for ($e = 1; $e <= 85; $e++) {
                $expDate = Carbon::now()->subDays(rand(1, 150));
                $tpl = $expenseTemplates[array_rand($expenseTemplates)];

                Expense::create([
                    'expense_number' => 'EXP-' . $expDate->format('Ym') . '-' . str_pad($e, 4, '0', STR_PAD_LEFT),
                    'category' => $tpl['category'],
                    'cost_center' => $tpl['cost_center'],
                    'title' => $tpl['title'],
                    'amount' => $tpl['amount'],
                    'expense_date' => $expDate->toDateString(),
                    'payment_method' => (rand(1, 10) > 3 ? 'cash' : 'bank_transfer'),
                    'user_id' => $user->id,
                    'notes' => 'سند صرف رسمي معتمد من الإدارة المالية',
                    'created_at' => $expDate,
                ]);
            }

            // =========================================================================
            // 10. STOCK TRANSFERS (التحويلات المخزنية بين الفروع)
            // =========================================================================
            for ($t = 1; $t <= 25; $t++) {
                $trDate = Carbon::now()->subDays(rand(2, 90));
                $fromStore = $mainStore;
                $toStore = $stores[rand(1, count($stores) - 1)];

                $transfer = StockTransfer::create([
                    'transfer_number' => 'TRF-' . $trDate->format('Ym') . '-' . str_pad($t, 4, '0', STR_PAD_LEFT),
                    'from_store_id' => $fromStore->id,
                    'to_store_id' => $toStore->id,
                    'user_id' => $user->id,
                    'transfer_date' => $trDate->toDateString(),
                    'status' => 'confirmed',
                    'notes' => 'إذن تحويل بضاعة لتغذية رصيد فرع ' . $toStore->name,
                    'created_at' => $trDate,
                ]);

                $trItems = collect($items)->random(rand(2, 4));
                foreach ($trItems as $trItem) {
                    $qty = sprintf('%.3f', rand(20, 80));
                    StockTransferItem::create([
                        'stock_transfer_id' => $transfer->id,
                        'item_id' => $trItem->id,
                        'quantity' => $qty,
                    ]);
                }
            }

            // =========================================================================
            // 11. RETURNS (المرتجعات والتسويات)
            // =========================================================================
            $sampleInvoices = Invoice::with('items')->inRandomOrder()->limit(15)->get();
            $retCounter = 1;
            foreach ($sampleInvoices as $sampleInv) {
                $retDate = Carbon::parse($sampleInv->invoice_date)->addDays(rand(1, 4));
                $retItem = $sampleInv->items->first();
                if (!$retItem) continue;

                $retQty = '1.000';
                $itemUnitPrice = sprintf('%.3f', (float) $retItem->unit_price);
                $retAmount = bcmul($retQty, $itemUnitPrice, 3);

                $retDoc = ReturnDocument::create([
                    'return_number' => 'RET-' . $retDate->format('Ym') . '-' . str_pad($retCounter++, 4, '0', STR_PAD_LEFT),
                    'return_type' => 'sales_return',
                    'invoice_id' => $sampleInv->id,
                    'customer_id' => $sampleInv->customer_id,
                    'user_id' => $user->id,
                    'total_amount' => $retAmount,
                    'return_date' => $retDate->toDateString(),
                    'reason' => 'استبدال درجة الطحن وتغيير للنعومة المناسبة للعميل',
                    'created_at' => $retDate,
                ]);

                ReturnItem::create([
                    'return_id' => $retDoc->id,
                    'item_id' => $retItem->item_id,
                    'quantity' => $retQty,
                    'unit_price' => $itemUnitPrice,
                    'total_price' => $retAmount,
                ]);
            }

            // =========================================================================
            // 12. ACTIVITY & AUDIT LOGS (سجلات الرقابة والعمليات)
            // =========================================================================
            for ($a = 1; $a <= 120; $a++) {
                $actDate = Carbon::now()->subDays(rand(1, 60))->subHours(rand(1, 20));
                ActivityLog::create([
                    'user_id' => $user->id,
                    'store_id' => $stores[array_rand($stores)]->id,
                    'action' => match (rand(1, 6)) {
                        1 => 'login_success',
                        2 => 'invoice_created',
                        3 => 'purchase_created',
                        4 => 'expense_created',
                        5 => 'customer_created',
                        default => 'settings_updated',
                    },
                    'module' => match (rand(1, 5)) {
                        1 => 'sales',
                        2 => 'purchases',
                        3 => 'inventory',
                        4 => 'shifts',
                        default => 'expenses',
                    },
                    'description' => 'تمت العملية بنجاح عبر لوحة تحكم ERP',
                    'ip_address' => '197.35.' . rand(10, 250) . '.' . rand(1, 250),
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) CloudERP/1.0.2',
                    'created_at' => $actDate,
                ]);
            }
        });
    }
}
