<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Expense;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\CashShift;
use App\Models\AdditionalExpense;
use App\Services\InvoiceService;
use App\Services\PurchaseService;
use App\Services\PaymentService;
use App\Services\CustomerBalanceService;
use App\Services\SupplierBalanceService;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

final class PopulateRealisticTenantDataCommand extends Command
{
    protected $signature = 'tenant:populate-realistic-data 
                            {tenant=2m : The tenant slug or ID (default 2m)}
                            {--fresh : Wipe existing operational data first}';

    protected $description = 'Wipe and generate authentic 1-year operational dataset for tenant 2M using real domain Services and Actions';

    public function handle(
        InvoiceService $invoiceService,
        PurchaseService $purchaseService,
        PaymentService $paymentService,
        CustomerBalanceService $customerBalanceService,
        SupplierBalanceService $supplierBalanceService,
        StockService $stockService
    ): int {
        $tenantIdentifier = (string)$this->argument('tenant');
        $this->info("Starting realistic 1-year data generation for tenant: [{$tenantIdentifier}]");

        // 1. Resolve or Create Tenant
        $tenant = Tenant::where('id', $tenantIdentifier)
            ->orWhere('slug', $tenantIdentifier)
            ->orWhereHas('domains', function ($q) use ($tenantIdentifier) {
                $q->where('domain', $tenantIdentifier)
                  ->orWhere('domain', 'like', "{$tenantIdentifier}.%");
            })
            ->first();

        if (!$tenant) {
            $this->warn("Tenant [{$tenantIdentifier}] not found in database. Creating workspace '{$tenantIdentifier}'...");
            $tenant = Tenant::create([
                'id'                   => $tenantIdentifier,
                'name'                 => 'مؤسسة 2M لاكسسوارات وسماعات وشواحن المحمول',
                'slug'                 => $tenantIdentifier,
                'email'                => 'info@2m.com',
                'phone'                => '01002003004',
                'status'               => 'active',
                'trial_ends_at'        => now()->addYear(),
                'subscription_ends_at' => now()->addYear(),
                'settings'             => [
                    'company_name'     => 'مؤسسة 2M لاكسسوارات وسماعات وشواحن المحمول',
                    'company_subtitle' => 'الوكيل الأول لسماعات الإيربودز، السبيكرات، الشواحن السريعة، وكابلات الهواتف الذكية',
                    'theme_preference' => 'dark',
                    'currency'         => 'ج.م',
                ],
            ]);

            $tenant->domains()->create([
                'domain' => '2m.baraa-solutions.com',
            ]);
            $tenant->domains()->create([
                'domain' => '2m.localhost',
            ]);
        }

        // Ensure database exists and is migrated
        try {
            if (!$tenant->database()->manager()->databaseExists($tenant->database()->getName())) {
                $this->info("Creating tenant database: [" . $tenant->database()->getName() . "]...");
                $tenant->database()->manager()->createDatabase($tenant);
            }
        } catch (\Throwable $e) {
            $this->warn("Database check: " . $e->getMessage());
        }

        // Initialize Tenancy
        tenancy()->initialize($tenant);
        $this->info("Tenancy initialized successfully on database: [" . config('database.connections.tenant.database') . "]");

        // Run migrations on tenant database to ensure all tables exist
        $this->callSilent('tenants:migrate', ['--tenants' => [$tenant->id], '--force' => true]);
        $this->callSilent(\Database\Seeders\PermissionsSeeder::class);

        // 2. Wipe operational data cleanly
        $this->info("Wiping previous operational data...");
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA busy_timeout = 15000;');
            DB::statement('PRAGMA journal_mode = WAL;');
            DB::statement('PRAGMA foreign_keys = OFF;');
        }
        
        InvoiceItem::truncate();
        Payment::truncate();
        AdditionalExpense::truncate();
        Invoice::truncate();
        PurchaseItem::truncate();
        Purchase::truncate();
        StockMovement::truncate();
        Expense::truncate();
        CashShift::truncate();
        StoreStock::truncate();
        Item::truncate();
        Category::truncate();
        Customer::truncate();
        Supplier::truncate();
        DB::table('activity_logs')->truncate();
        
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }
        $this->info("Database cleaned.");

        // 3. Ensure Staff Users
        $adminUser = User::updateOrCreate(
            ['phone' => '01012316954'],
            [
                'name'     => 'كمال سرور (المدير العام)',
                'email'    => 'admin@2m.com',
                'password' => Hash::make('password'),
                'is_active'=> true,
            ]
        );
        $adminUser->assignRole('admin');

        $cashierMorning = User::updateOrCreate(
            ['phone' => '01122334455'],
            [
                'name'     => 'أحمد محمود (مسؤول المبيعات الصباحي)',
                'email'    => 'cashier1@2m.com',
                'password' => Hash::make('password'),
                'is_active'=> true,
            ]
        );
        $cashierMorning->assignRole('cashier');

        $cashierEvening = User::updateOrCreate(
            ['phone' => '01233445566'],
            [
                'name'     => 'كريم فتحي (مسؤول المبيعات المسائي)',
                'email'    => 'cashier2@2m.com',
                'password' => Hash::make('password'),
                'is_active'=> true,
            ]
        );
        $cashierEvening->assignRole('cashier');

        Auth::login($adminUser);

        // 4. Ensure Stores & Branches
        $mainStore = Store::firstOrCreate(
            ['code' => 'STR-MAIN'],
            [
                'name'      => 'معرض 2M الرئيسي ومخزن التوزيع',
                'address'   => 'شارع التحرير الرئيسي، مول التكنولوجيا، وسط البلد',
                'phone'     => '01002003004',
                'is_active' => true,
                'is_main'   => true,
            ]
        );

        $branchStore = Store::firstOrCreate(
            ['code' => 'STR-BR01'],
            [
                'name'      => 'فرع المهندسين للاكسسوارات والسماعات',
                'address'   => 'شارع شهاب، المهندسين، الجيزة',
                'phone'     => '01002003005',
                'is_active' => true,
                'is_main'   => false,
            ]
        );

        $vanStore = Store::firstOrCreate(
            ['code' => 'STR-VAN1'],
            [
                'name'      => 'سيارة شحن وتوزيع المحلات (فان 1)',
                'address'   => 'خط توزيع محلات الموبايل بالقاهرة والجيزة',
                'phone'     => '01002003006',
                'is_active' => true,
                'is_main'   => false,
            ]
        );

        // 5. Create Categories for Mobile, Audio & Chargers
        $this->info("Creating Mobile & Audio Categories...");
        $categoriesData = [
            ['name' => 'سماعات إيربودز وبلوتوث (Earbuds)', 'code' => 'CAT-EAR', 'icon' => 'Headphones', 'color' => '#8B5CF6', 'color_light' => '#EDE9FE', 'sort' => 1],
            ['name' => 'سماعات سبيكر ومكبرات صوت (Speakers)', 'code' => 'CAT-SPK', 'icon' => 'Volume2', 'color' => '#F59E0B', 'color_light' => '#FEF3C7', 'sort' => 2],
            ['name' => 'شواحن ورؤوس شحن سريع (Chargers)', 'code' => 'CAT-CHG', 'icon' => 'Zap', 'color' => '#10B981', 'color_light' => '#D1FAE5', 'sort' => 3],
            ['name' => 'كابلات ووصلات شحن معتمدة (Cables)', 'code' => 'CAT-CBL', 'icon' => 'Cable', 'color' => '#3B82F6', 'color_light' => '#DBEAFE', 'sort' => 4],
            ['name' => 'باور بنك وبطاريات متنقلة (Power Banks)', 'code' => 'CAT-PWR', 'icon' => 'BatteryCharging', 'color' => '#EF4444', 'color_light' => '#FEE2E2', 'sort' => 5],
            ['name' => 'حوامل واكسسوارات وساعات ذكية', 'code' => 'CAT-ACC', 'icon' => 'Smartphone', 'color' => '#EC4899', 'color_light' => '#FCE7F3', 'sort' => 6],
        ];

        $categories = [];
        foreach ($categoriesData as $c) {
            $categories[$c['code']] = Category::create([
                'name'        => $c['name'],
                'code'        => $c['code'],
                'icon'        => $c['icon'],
                'color'       => $c['color'],
                'color_light' => $c['color_light'],
                'sort_order'  => $c['sort'],
                'is_active'   => true,
            ]);
        }

        // 6. Create Realistic Items for Mobile, Headphones, Speakers, Chargers
        $this->info("Creating Realistic Products Catalogue...");
        $itemsData = [
            // 🎧 سماعات إيربودز وبلوتوث
            ['name' => 'سماعة Joyroom JR-T03S Pro الأصلية عزل ضوضاء ANC', 'code' => 'EAR-JOY-T03SP', 'cat' => 'CAT-EAR', 'unit' => 'قطعة', 'cost' => '650.000', 'retail' => '950.000', 'wholesale' => '820.000', 'min_price' => '780.000', 'min_stock' => 15],
            ['name' => 'سماعة Anker Soundcore Life P2i بلوتوث صوت نقي', 'code' => 'EAR-ANK-P2I', 'cat' => 'CAT-EAR', 'unit' => 'قطعة', 'cost' => '750.000', 'retail' => '1150.000', 'wholesale' => '980.000', 'min_price' => '920.000', 'min_stock' => 12],
            ['name' => 'سماعة Anker Soundcore Space A40 عزل تكيفي 50 ساعة', 'code' => 'EAR-ANK-A40', 'cat' => 'CAT-EAR', 'unit' => 'قطعة', 'cost' => '1800.000', 'retail' => '2600.000', 'wholesale' => '2300.000', 'min_price' => '2200.000', 'min_stock' => 8],
            ['name' => 'سماعة AirPods Pro 2 طبق الأصل جودة عالية ANC', 'code' => 'EAR-AIR-PRO2C', 'cat' => 'CAT-EAR', 'unit' => 'قطعة', 'cost' => '420.000', 'retail' => '700.000', 'wholesale' => '580.000', 'min_price' => '540.000', 'min_stock' => 20],
            ['name' => 'سماعة Oraimo FreePods 4 عزل نشط بيز قوي', 'code' => 'EAR-ORA-FP4', 'cat' => 'CAT-EAR', 'unit' => 'قطعة', 'cost' => '820.000', 'retail' => '1250.000', 'wholesale' => '1080.000', 'min_price' => '1020.000', 'min_stock' => 10],
            ['name' => 'سماعة Baseus Bowie M2 Plus عزل ذكي 42dB', 'code' => 'EAR-BAS-M2P', 'cat' => 'CAT-EAR', 'unit' => 'قطعة', 'cost' => '920.000', 'retail' => '1400.000', 'wholesale' => '1200.000', 'min_price' => '1150.000', 'min_stock' => 10],
            ['name' => 'سماعة رأس بلوتوث JBL Tune 510BT بيز قوي 40 ساعة', 'code' => 'EAR-JBL-510BT', 'cat' => 'CAT-EAR', 'unit' => 'قطعة', 'cost' => '1150.000', 'retail' => '1750.000', 'wholesale' => '1500.000', 'min_price' => '1420.000', 'min_stock' => 10],
            ['name' => 'سماعة رأس Joyroom JR-HL1 ستيريو مريحة للألعاب', 'code' => 'EAR-JOY-HL1', 'cat' => 'CAT-EAR', 'unit' => 'قطعة', 'cost' => '380.000', 'retail' => '600.000', 'wholesale' => '500.000', 'min_price' => '470.000', 'min_stock' => 15],

            // 🔊 سماعات سبيكر ومكبرات صوت
            ['name' => 'مكبر صوت JBL Flip 6 مقاوم للماء صوت محيطي أصلي', 'code' => 'SPK-JBL-FLP6', 'cat' => 'CAT-SPK', 'unit' => 'قطعة', 'cost' => '3200.000', 'retail' => '4500.000', 'wholesale' => '3900.000', 'min_price' => '3750.000', 'min_stock' => 6],
            ['name' => 'مكبر صوت JBL Go 3 بلوتوث ميني ضد الصدمات', 'code' => 'SPK-JBL-GO3', 'cat' => 'CAT-SPK', 'unit' => 'قطعة', 'cost' => '1100.000', 'retail' => '1650.000', 'wholesale' => '1400.000', 'min_price' => '1350.000', 'min_stock' => 15],
            ['name' => 'سبيكر Anker Soundcore 2 قوة 12W بطارية 24 ساعة', 'code' => 'SPK-ANK-SC2', 'cat' => 'CAT-SPK', 'unit' => 'قطعة', 'cost' => '1300.000', 'retail' => '1950.000', 'wholesale' => '1680.000', 'min_price' => '1600.000', 'min_stock' => 10],
            ['name' => 'سبيكر مضيء Joyroom RGB مصحوب بمايكروفون كاريوكي', 'code' => 'SPK-JOY-RGBM', 'cat' => 'CAT-SPK', 'unit' => 'قطعة', 'cost' => '850.000', 'retail' => '1350.000', 'wholesale' => '1150.000', 'min_price' => '1080.000', 'min_stock' => 10],
            ['name' => 'سبيكر خشبي كلاسيك Vidvie ستيريو بلوتوث وراديو FM', 'code' => 'SPK-VID-WOOD', 'cat' => 'CAT-SPK', 'unit' => 'قطعة', 'cost' => '620.000', 'retail' => '980.000', 'wholesale' => '820.000', 'min_price' => '780.000', 'min_stock' => 12],

            // ⚡ شواحن ورؤوس شحن سريع
            ['name' => 'رأس شاحن أنكر 20W PD Nano الأصلي فائق الصغر', 'code' => 'CHG-ANK-20W', 'cat' => 'CAT-CHG', 'unit' => 'قطعة', 'cost' => '320.000', 'retail' => '490.000', 'wholesale' => '420.000', 'min_price' => '390.000', 'min_stock' => 30],
            ['name' => 'شاحن أبل 20W USB-C أصلي معتمد لأجهزة آيفون', 'code' => 'CHG-APL-20W', 'cat' => 'CAT-CHG', 'unit' => 'قطعة', 'cost' => '480.000', 'retail' => '750.000', 'wholesale' => '640.000', 'min_price' => '600.000', 'min_stock' => 25],
            ['name' => 'شاحن سامسونج 25W Super Fast Charging أصلي تايب سي', 'code' => 'CHG-SAM-25W', 'cat' => 'CAT-CHG', 'unit' => 'قطعة', 'cost' => '290.000', 'retail' => '450.000', 'wholesale' => '380.000', 'min_price' => '360.000', 'min_stock' => 30],
            ['name' => 'رأس شاحن جويروم 30W مخرجين (Type-C + USB-A)', 'code' => 'CHG-JOY-30W', 'cat' => 'CAT-CHG', 'unit' => 'قطعة', 'cost' => '240.000', 'retail' => '390.000', 'wholesale' => '320.000', 'min_price' => '300.000', 'min_stock' => 35],
            ['name' => 'شاحن جان LDNIO 65W GaN مكتبي 4 مخارج فائق السرعة', 'code' => 'CHG-LDN-65W', 'cat' => 'CAT-CHG', 'unit' => 'قطعة', 'cost' => '680.000', 'retail' => '1050.000', 'wholesale' => '890.000', 'min_price' => '840.000', 'min_stock' => 12],
            ['name' => 'شاحن سيارة Joyroom 45W معدني مخرجين شحن سريع', 'code' => 'CHG-CAR-45W', 'cat' => 'CAT-CHG', 'unit' => 'قطعة', 'cost' => '160.000', 'retail' => '280.000', 'wholesale' => '230.000', 'min_price' => '210.000', 'min_stock' => 25],

            // 🔌 كابلات ووصلات شحن
            ['name' => 'كابل أنكر PowerLine III قماش Type-C to Type-C 60W (1m)', 'code' => 'CBL-ANK-CC1M', 'cat' => 'CAT-CBL', 'unit' => 'قطعة', 'cost' => '170.000', 'retail' => '280.000', 'wholesale' => '230.000', 'min_price' => '210.000', 'min_stock' => 40],
            ['name' => 'كابل أنكر PowerLine II Type-C to Lightning معتمد MFi (1m)', 'code' => 'CBL-ANK-CL1M', 'cat' => 'CAT-CBL', 'unit' => 'قطعة', 'cost' => '240.000', 'retail' => '380.000', 'wholesale' => '320.000', 'min_price' => '290.000', 'min_stock' => 35],
            ['name' => 'كابل جويروم Joyroom قماش 3A سريع Type-C (1.2m)', 'code' => 'CBL-JOY-TC12', 'cat' => 'CAT-CBL', 'unit' => 'قطعة', 'cost' => '55.000', 'retail' => '110.000', 'wholesale' => '85.000', 'min_price' => '75.000', 'min_stock' => 60],
            ['name' => 'كابل جويروم Joyroom قماش 3A سريع Lightning آيفون (1.2m)', 'code' => 'CBL-JOY-LGT12', 'cat' => 'CAT-CBL', 'unit' => 'قطعة', 'cost' => '60.000', 'retail' => '120.000', 'wholesale' => '90.000', 'min_price' => '80.000', 'min_stock' => 60],
            ['name' => 'كابل باسيوس Baseus 3 في 1 (Type-C + Lightning + Micro)', 'code' => 'CBL-BAS-3IN1', 'cat' => 'CAT-CBL', 'unit' => 'قطعة', 'cost' => '130.000', 'retail' => '240.000', 'wholesale' => '190.000', 'min_price' => '170.000', 'min_stock' => 30],
            ['name' => 'وصلة تحويل Type-C إلى AUX 3.5mm محول صوت DAC نقي', 'code' => 'CBL-AUX-DAC', 'cat' => 'CAT-CBL', 'unit' => 'قطعة', 'cost' => '85.000', 'retail' => '160.000', 'wholesale' => '130.000', 'min_price' => '115.000', 'min_stock' => 25],

            // 🔋 باور بنك وبطاريات متنقلة
            ['name' => 'باور بنك Anker PowerCore 20,000mAh شحن سريع 20W PD', 'code' => 'PWR-ANK-20K', 'cat' => 'CAT-PWR', 'unit' => 'قطعة', 'cost' => '1150.000', 'retail' => '1750.000', 'wholesale' => '1490.000', 'min_price' => '1420.000', 'min_stock' => 12],
            ['name' => 'باور بنك Joyroom 10,000mAh MagSafe لاسلكي مغناطيسي', 'code' => 'PWR-JOY-MAG10', 'cat' => 'CAT-PWR', 'unit' => 'قطعة', 'cost' => '620.000', 'retail' => '990.000', 'wholesale' => '840.000', 'min_price' => '790.000', 'min_stock' => 15],
            ['name' => 'باور بنك Remax 30,000mAh كابلات مدمجة شاشة ديجيتال', 'code' => 'PWR-RMX-30K', 'cat' => 'CAT-PWR', 'unit' => 'قطعة', 'cost' => '880.000', 'retail' => '1390.000', 'wholesale' => '1180.000', 'min_price' => '1100.000', 'min_stock' => 10],
            ['name' => 'باور بنك Baseus Blade 100W فائق النحافة للابتوب والموبايل', 'code' => 'PWR-BAS-100W', 'cat' => 'CAT-PWR', 'unit' => 'قطعة', 'cost' => '2400.000', 'retail' => '3500.000', 'wholesale' => '3000.000', 'min_price' => '2850.000', 'min_stock' => 5],

            // 📱 حوامل واكسسوارات وساعات
            ['name' => 'حامل سيارة Joyroom MagSafe مغناطيسي قوي لفتحة التكييف', 'code' => 'ACC-JOY-HLD', 'cat' => 'CAT-ACC', 'unit' => 'قطعة', 'cost' => '190.000', 'retail' => '340.000', 'wholesale' => '280.000', 'min_price' => '260.000', 'min_stock' => 20],
            ['name' => 'ستاند مكتبي معدني قابل للطي للهواتف والتابلت', 'code' => 'ACC-DSK-STN', 'cat' => 'CAT-ACC', 'unit' => 'قطعة', 'cost' => '95.000', 'retail' => '180.000', 'wholesale' => '145.000', 'min_price' => '130.000', 'min_stock' => 25],
            ['name' => 'ساعة ذكية Smart Watch Ultra 9 شاشة AMOLED مع 3 ستراب', 'code' => 'ACC-WAT-ULT9', 'cat' => 'CAT-ACC', 'unit' => 'قطعة', 'cost' => '720.000', 'retail' => '1200.000', 'wholesale' => '990.000', 'min_price' => '940.000', 'min_stock' => 15],
        ];

        $items = [];
        foreach ($itemsData as $row) {
            $cat = $categories[$row['cat']] ?? null;
            $items[$row['code']] = Item::create([
                'name'               => $row['name'],
                'code'               => $row['code'],
                'category'           => $cat?->name,
                'category_id'        => $cat?->id,
                'unit'               => $row['unit'],
                'current_stock'      => '0.000',
                'cost_price'         => $row['cost'],
                'selling_price'      => $row['retail'],
                'min_selling_price'  => $row['min_price'],
                'price_retail'       => $row['retail'],
                'price_wholesale'    => $row['wholesale'],
                'weighted_avg_cost'  => $row['cost'],
                'min_stock_level'    => $row['min_stock'],
                'is_active'          => true,
                'is_pos_pinned'      => in_array($row['code'], ['TRK-SP-MID', 'TRK-PLN-LGT', 'TRK-ROYAL', 'SPC-BRZ-SAN', 'SP-CARD-JMB']),
                'pos_sales_count'    => 0,
            ]);
        }

        // 7. Create Suppliers
        $this->info("Creating Verified Electronics Suppliers...");
        $suppliersData = [
            ['name' => 'شركة دلتا مصر لاستيراد وتوزيع أجهزة أنكر وساوندكور', 'phone' => '01099887766', 'contact' => 'م. طارق رضوان', 'address' => 'المنطقة الحرة، ميناء الإسكندرية / مخازن العبور'],
            ['name' => 'التوكيل المصري لاكسسوارات جويروم وريماكس وباسيوس', 'phone' => '01288776655', 'contact' => 'الحاج جلال التاجوري', 'address' => 'مول البستان، باب اللوق، وسط البلد، القاهرة'],
            ['name' => 'الشركة المصرية الدولية للصوتيات ومكبرات JBL وسوني', 'phone' => '01177665544', 'contact' => 'أ. حسام البنا', 'address' => 'شارع عبدالعزيز، العتبة، القاهرة'],
            ['name' => 'مؤسسة النور لحلول الشحن السريع وكابلات LDNIO', 'phone' => '01566554433', 'contact' => 'د. وائل سلامة', 'address' => 'شارع الهرم، الجيزة'],
        ];

        $suppliers = [];
        foreach ($suppliersData as $s) {
            $suppliers[] = Supplier::create([
                'name'            => $s['name'],
                'phone'           => $s['phone'],
                'contact_person'  => $s['contact'],
                'address'         => $s['address'],
                'current_balance' => '0.000',
                'is_active'       => true,
            ]);
        }

        // 8. Create Customers (Walk-in, Tech Shops, Online Resellers)
        $this->info("Creating Customers Database...");
        $customersData = [
            ['name' => 'عميل نقدي عام (معرض وصالة)', 'phone' => '0000000000', 'tier' => 'retail', 'limit' => 0],
            ['name' => 'محل آبل ستور المهندسين (أ. وليد)', 'phone' => '01011112222', 'tier' => 'wholesale', 'limit' => 50000],
            ['name' => 'محل تكنو فون الدقي (م. هشام)', 'phone' => '01022223333', 'tier' => 'wholesale', 'limit' => 40000],
            ['name' => 'مكتبة وخدمات التجمع الخامس', 'phone' => '01033334444', 'tier' => 'wholesale', 'limit' => 60000],
            ['name' => 'مكتب المستشار القانوني خالد عزمي (شواحن وكابلات)', 'phone' => '01044445555', 'tier' => 'retail', 'limit' => 5000],
            ['name' => 'شركة برمجيات كلاود إنتل (اكسسوارات موظفين)', 'phone' => '01055556666', 'tier' => 'retail', 'limit' => 10000],
            ['name' => 'محل رنين فون شبرا (أ. رامي فوزي)', 'phone' => '01066667777', 'tier' => 'wholesale', 'limit' => 35000],
            ['name' => 'مؤسسة النيل بلازا للفنادق (سبيكرات وشواحن غرف)', 'phone' => '01077778888', 'tier' => 'wholesale', 'limit' => 100000],
            ['name' => 'معرض الصفا للإلكترونيات والموبايل (جملة)', 'phone' => '01088889999', 'tier' => 'wholesale', 'limit' => 80000],
            ['name' => 'د. سمير عبدالعزيز (ايربودز برو وسبيكر)', 'phone' => '01099990000', 'tier' => 'retail', 'limit' => 3000],
            ['name' => 'م. أيمن الشناوي (شاحن أنكر وباور بنك)', 'phone' => '01112345678', 'tier' => 'retail', 'limit' => 2000],
            ['name' => 'أ. نادية الحسيني (كابلات وشواحن منزلية)', 'phone' => '01223456789', 'tier' => 'retail', 'limit' => 1500],
        ];

        $customers = [];
        foreach ($customersData as $c) {
            $customers[] = Customer::create([
                'name'            => $c['name'],
                'phone'           => $c['phone'],
                'price_tier'      => $c['tier'],
                'credit_limit'    => $c['limit'],
                'current_balance' => '0.000',
                'is_active'       => true,
            ]);
        }

        // 9. Execute 1-Year Purchases History (via PurchaseService to establish genuine stock movements and moving average costs)
        $this->info("Simulating 12 Months of Supply Chain & Purchases via PurchaseService...");
        $startDate = Carbon::now()->subYear()->startOfMonth();

        // 12 Purchase Invoices spread over 12 months
        for ($m = 0; $m < 12; $m++) {
            $pDate = $startDate->copy()->addMonths($m)->addDays(rand(2, 6))->toDateString();
            
            // Purchase Green Coffee & Packaging from Suppliers
            $sup = $suppliers[$m % count($suppliers)];
            
            $purchaseLines = [];
            $selectedItemKeys = array_rand($items, rand(6, 9));
            foreach ((array)$selectedItemKeys as $key) {
                $it = $items[$key];
                $qty = rand(80, 200); // Large stock quantity
                $purchaseLines[] = [
                    'item_id'    => $it->id,
                    'quantity'   => (string)$qty,
                    'cost_price' => $it->cost_price,
                ];
            }

            $purchaseData = [
                'supplier_id'               => $sup->id,
                'store_id'                  => $mainStore->id,
                'purchase_date'             => $pDate,
                'purchase_number'           => 'PUR-' . date('Ymd', strtotime($pDate)) . '-' . str_pad((string)($m + 1), 4, '0', STR_PAD_LEFT),
                'supplier_invoice_ref'      => 'INV-SUP-' . rand(1000, 9999),
                'discount_amount'           => '0.000',
                'items'                     => $purchaseLines,
                'additional_expenses'       => [
                    [
                        'title'             => 'شحن ونقل وتعتيق شيكارات',
                        'amount'            => (string)rand(300, 800),
                        'paid_by'           => 'supplier_account',
                        'allocation_method' => 'by_quantity',
                    ]
                ],
            ];

            $purch = $purchaseService->createPurchase($purchaseData);
            
            // Record payment for purchase (85% paid, 15% credit)
            $paidAmt = bcmul((string)$purch->net_total, '0.85', 3);
            if (bccomp($paidAmt, '0.000', 3) > 0) {
                Payment::create([
                    'payment_number' => 'PAY-PUR-' . strtoupper(uniqid()),
                    'supplier_id'    => $sup->id,
                    'purchase_id'    => $purch->id,
                    'user_id'        => $adminUser->id,
                    'amount'         => $paidAmt,
                    'payment_date'   => $pDate,
                    'payment_method' => 'bank_transfer',
                    'notes'          => 'سداد تحويل بنكي لفاتورة مشتريات',
                ]);
                $purch->update([
                    'paid_amount'      => $paidAmt,
                    'remaining_amount' => bcsub((string)$purch->net_total, $paidAmt, 3),
                    'payment_status'   => 'partially_paid',
                ]);
                $supplierBalanceService->updateBalance($sup->id);
            }
        }

        // Distribute some initial stock to branch and van
        $this->info("Distributing Initial Stock to Branch & Distribution Van...");
        foreach ($items as $it) {
            $mainStock = StoreStock::where('store_id', $mainStore->id)->where('item_id', $it->id)->first();
            if ($mainStock && (float)$mainStock->quantity > 30) {
                $transferQty = number_format((float)$mainStock->quantity * 0.25, 3, '.', '');
                StoreStock::firstOrCreate(
                    ['store_id' => $branchStore->id, 'item_id' => $it->id],
                    ['quantity' => $transferQty, 'min_stock' => $it->min_stock_level]
                );
            }
        }

        // 10. Simulate 1-Year Daily Operations (Shifts, Invoices, POS Checkouts, Expenses, Payments)
        $this->info("Simulating 365 Days of Authentic Sales & POS Transactions...");
        $totalInvoicesCount = 0;
        $totalSalesAmount = '0.000';

        $curDay = $startDate->copy();
        $today = Carbon::now();
        $dayIndex = 0;

        while ($curDay->lte($today)) {
            $dateStr = $curDay->toDateString();
            $dayIndex++;

            // Shift for the day
            $shift = CashShift::create([
                'user_id'               => ($dayIndex % 2 === 0) ? $cashierMorning->id : $cashierEvening->id,
                'store_id'              => $mainStore->id,
                'shift_number'          => 'SHF-' . $curDay->format('ymd') . '-01',
                'status'                => 'closed',
                'opened_at'             => $curDay->copy()->setTime(8, 30, 0),
                'closed_at'             => $curDay->copy()->setTime(23, 30, 0),
                'opening_cash_balance'  => '500.000',
                'total_cash_sales'      => '0.000',
                'total_credit_sales'    => '0.000',
                'total_payments_collected'=> '0.000',
                'total_refunds'         => '0.000',
                'expected_cash_balance' => '500.000',
                'actual_cash_balance'   => '500.000',
                'cash_difference'       => '0.000',
                'notes'                 => 'يومية عمل منتظمة ومقفلة بالكامل',
            ]);

            // Daily Invoices: 2 to 5 realistic orders per day
            $dailyOrdersCount = rand(2, 5);
            $shiftCashSales = '0.000';
            $shiftCreditSales = '0.000';

            for ($o = 0; $o < $dailyOrdersCount; $o++) {
                // Random customer (70% cash walk-in, 30% named customer)
                $cust = (rand(1, 100) <= 70) ? $customers[0] : $customers[rand(1, count($customers) - 1)];

                // Pick 1 to 3 items for the cart
                $orderItems = [];
                $pickedItems = array_rand($items, rand(1, 3));
                foreach ((array)$pickedItems as $pKey) {
                    $it = $items[$pKey];
                    // Quantities for roastery: e.g. 0.250kg, 0.500kg, 1kg, 2kg, or 1 unit
                    $q = (in_array($it->unit, ['كجم']))
                        ? [0.250, 0.500, 1.000, 1.500, 2.000][rand(0, 4)]
                        : (float)rand(1, 3);

                    $price = ($cust->price_tier === 'wholesale') ? (float)$it->price_wholesale : (float)$it->selling_price;
                    $orderItems[] = [
                        'item_id'    => $it->id,
                        'quantity'   => $q,
                        'unit_price' => $price,
                    ];
                }

                // Determine Payment mode
                $payRoll = rand(1, 100);
                if ($cust->id !== $customers[0]->id && $payRoll <= 20) {
                    // Credit for registered customers
                    $pType = 'credit';
                    $pMethod = 'cash';
                    $paymentsArray = null;
                } elseif ($payRoll <= 35) {
                    // Instapay
                    $pType = 'cash';
                    $pMethod = 'instapay';
                    $paymentsArray = null;
                } elseif ($payRoll <= 45) {
                    // Smart Wallet
                    $pType = 'cash';
                    $pMethod = 'e_wallet';
                    $paymentsArray = null;
                } elseif ($payRoll <= 55) {
                    // Multi-payment split!
                    $pType = 'cash';
                    $pMethod = 'cash';
                    $paymentsArray = [
                        ['method' => 'cash', 'amount' => 150.000],
                        ['method' => 'instapay', 'amount' => 200.000],
                    ];
                } else {
                    // Standard Cash
                    $pType = 'cash';
                    $pMethod = 'cash';
                    $paymentsArray = null;
                }

                // Extra expenses on some delivery orders
                $orderExpenses = [];
                if (rand(1, 10) === 1) {
                    $orderExpenses[] = [
                        'title'   => 'مصاريف توصيل وشحن سريع',
                        'amount'  => '40.000',
                        'paid_by' => 'customer_account',
                    ];
                }

                $invoiceData = [
                    'customer_id'         => $cust->id,
                    'store_id'            => $mainStore->id,
                    'invoice_date'        => $dateStr,
                    'payment_type'        => $pType,
                    'payment_method'      => $pMethod,
                    'discount_type'       => 'percentage',
                    'discount_value'      => (rand(1, 10) === 1) ? 5.0 : 0.0,
                    'paid_amount'         => 0.0,
                    'notes'               => 'طلب كاشير سريع - وردية ' . $shift->shift_number,
                    'items'               => $orderItems,
                    'additional_expenses' => $orderExpenses,
                    'payments'            => $paymentsArray,
                ];

                try {
                    $inv = $invoiceService->confirmInvoice($invoiceData);
                    $totalInvoicesCount++;
                    $totalSalesAmount = bcadd($totalSalesAmount, (string)$inv->net_total, 3);

                    if ($pType === 'cash') {
                        $shiftCashSales = bcadd($shiftCashSales, (string)$inv->paid_amount, 3);
                    } else {
                        $shiftCreditSales = bcadd($shiftCreditSales, (string)$inv->net_total, 3);
                    }
                } catch (\Throwable $e) {
                    // Continue gracefully if any line had stock shortage
                }
            }

            // Monthly Operational Expense
            if ($curDay->day === 1) {
                Expense::create([
                    'expense_number' => 'EXP-' . $curDay->format('Ym') . '-RENT',
                    'store_id'       => $mainStore->id,
                    'user_id'        => $adminUser->id,
                    'category'       => 'rent',
                    'title'          => 'إيجار معرض ومخزن 2M للاكسسوارات لشهر ' . $curDay->format('F Y'),
                    'amount'         => '12000.000',
                    'expense_date'   => $dateStr,
                    'payment_method' => 'bank_transfer',
                    'notes'          => 'سداد إيجار المعرض التجاري',
                ]);
            }
            if ($curDay->day === 15) {
                Expense::create([
                    'expense_number' => 'EXP-' . $curDay->format('Ymd') . '-UTIL',
                    'store_id'       => $mainStore->id,
                    'user_id'        => $adminUser->id,
                    'category'       => 'utilities',
                    'title'          => 'فاتورة كهرباء وتكييف المعرض والإنارة',
                    'amount'         => '2800.000',
                    'expense_date'   => $dateStr,
                    'payment_method' => 'cash',
                    'notes'          => 'سداد كاش من الخزينة',
                ]);
            }

            // Reconcile and close shift
            $expectedCash = bcadd('500.000', $shiftCashSales, 3);
            $shift->update([
                'total_cash_sales'      => $shiftCashSales,
                'total_credit_sales'    => $shiftCreditSales,
                'expected_cash_balance' => $expectedCash,
                'actual_cash_balance'   => $expectedCash,
                'cash_difference'       => '0.000',
            ]);

            $curDay->addDay();
        }

        // 11. Customer Debt Settlements over the year
        $this->info("Simulating Customer Debt Settlements & Receipts...");
        $allCustomers = Customer::where('id', '>', 1)->get();
        foreach ($allCustomers as $c) {
            try {
                DB::transaction(function () use ($c, $customerBalanceService, $paymentService) {
                    $customerBalanceService->updateBalance($c->id);
                    $c->refresh();

                    // If customer has debt, pay 75% of it
                    if (bccomp((string)$c->current_balance, '0.000', 3) > 0) {
                        $payAmt = bcmul((string)$c->current_balance, '0.75', 3);
                        if (bccomp($payAmt, '0.000', 3) > 0) {
                            $paymentService->recordCustomerPayment([
                                'customer_id'    => $c->id,
                                'amount'         => $payAmt,
                                'payment_date'   => now()->subDays(rand(1, 15))->toDateString(),
                                'payment_method' => 'instapay',
                                'notes'          => 'سداد دفعة من الحساب الجاري عبر إنستاباي',
                            ]);
                        }
                    }
                });
            } catch (\Throwable $e) {
                // continue gracefully
            }
        }

        // 12. Final Balances Recomputation
        foreach (Customer::all() as $c) {
            try {
                DB::transaction(fn() => $customerBalanceService->updateBalance($c->id));
            } catch (\Throwable $e) {}
        }
        foreach (Supplier::all() as $s) {
            try {
                DB::transaction(fn() => $supplierBalanceService->updateBalance($s->id));
            } catch (\Throwable $e) {}
        }

        $this->newLine();
        $this->info("=========================================================");
        $this->info("1-YEAR REALISTIC DATASET GENERATION COMPLETE!");
        $this->info("=========================================================");
        $this->info("Tenant Workspace:       {$tenant->name} ({$tenant->id})");
        $this->info("Products Created:        " . Item::count() . " active products");
        $this->info("Categories:              " . Category::count() . " categories");
        $this->info("Suppliers:               " . Supplier::count() . " suppliers");
        $this->info("Customers:               " . Customer::count() . " customers");
        $this->info("Purchase Invoices:       " . Purchase::count() . " confirmed shipments");
        $this->info("Sales & POS Invoices:    " . Invoice::count() . " genuine invoices");
        $this->info("Total 1-Year Sales:      " . number_format((float)$totalSalesAmount, 2) . " EGP");
        $this->info("Cash Shifts Closed:      " . CashShift::count() . " reconciled shifts");
        $this->info("Payment Receipts:        " . Payment::count() . " payments logged");
        $this->info("Inventory Movements:     " . StockMovement::count() . " audit movements");
        $this->info("=========================================================");

        return Command::SUCCESS;
    }
}
