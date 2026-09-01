<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

class TenThousandItemsSeeder extends Seeder
{
    public function run(): void
    {
        $mainStore = Store::first() ?: Store::create([
            'name' => 'الفرع الرئيسي',
            'code' => 'STR-01',
            'type' => 'retail',
            'is_main' => true,
            'is_active' => true,
        ]);

        $categoriesList = [
            ['name' => 'هواتف آيفون (Apple iPhone)', 'icon' => '🍏', 'sort_order' => 1],
            ['name' => 'هواتف سامسونج (Samsung Galaxy)', 'icon' => '📱', 'sort_order' => 2],
            ['name' => 'هواتف شاومي وريدمي (Xiaomi / Redmi)', 'icon' => '⚡', 'sort_order' => 3],
            ['name' => 'هواتف ريلمي وهونر (Realme & Honor)', 'icon' => '✨', 'sort_order' => 4],
            ['name' => 'سماعات وإيربودز لاسلكية (Earbuds & AirPods)', 'icon' => '🎧', 'sort_order' => 5],
            ['name' => 'سماعات رأس وسبيكرات (Headphones & Speakers)', 'icon' => '🔊', 'sort_order' => 6],
            ['name' => 'شواحن ورؤوس شحن سريعة (Wall Chargers)', 'icon' => '🔌', 'sort_order' => 7],
            ['name' => 'كابلات ووصلات شحن (Cables & Adapters)', 'icon' => '🧶', 'sort_order' => 8],
            ['name' => 'باور بنك وبطاريات متنقلة (Power Banks)', 'icon' => '🔋', 'sort_order' => 9],
            ['name' => 'ساعات وسوارات ذكية (Smartwatches & Bands)', 'icon' => '⌚', 'sort_order' => 10],
            ['name' => 'جرابات وكفرات حماية (Cases & Covers)', 'icon' => '🛡️', 'sort_order' => 11],
            ['name' => 'اسكرينات وشاشات حماية (Screen Protectors)', 'icon' => '📲', 'sort_order' => 12],
            ['name' => 'حوامل وإكسسوارات سيارات (Car Holders & Magsafe)', 'icon' => '🚗', 'sort_order' => 13],
            ['name' => 'فلاشات وكروت ميموري (Storage & Memory)', 'icon' => '💾', 'sort_order' => 14],
            ['name' => 'قطع غيار وصيانة (Spare Parts & Tools)', 'icon' => '🔧', 'sort_order' => 15],
            ['name' => 'ألعاب وإلكترونيات ذكية (Gaming & Smart Gadgets)', 'icon' => '🎮', 'sort_order' => 16],
        ];

        $categoryIds = [];
        foreach ($categoriesList as $cat) {
            $created = Category::updateOrCreate(
                ['name' => $cat['name']],
                [
                    'icon' => $cat['icon'],
                    'sort_order' => $cat['sort_order'],
                    'is_active' => true,
                ]
            );
            $categoryIds[$cat['name']] = $created->id;
        }

        $brands = ['Apple', 'Samsung', 'Xiaomi', 'Redmi', 'Realme', 'Honor', 'Huawei', 'Anker', 'Joyroom', 'Oraimo', 'Baseus', 'JBL', 'Sony', 'LDNIO', 'Ugreen', 'Hoco', 'Sandisk', 'Kingston', 'Remax', 'Vidvie'];
        $colors = ['Black', 'White', 'Blue', 'Titanium Natural', 'Desert Titanium', 'Deep Purple', 'Green', 'Silver', 'Gold', 'Graphite', 'Mint', 'Pink', 'Red', 'Space Gray'];
        $capacities = ['64GB', '128GB', '256GB', '512GB', '1TB', '20W', '30W', '45W', '65W', '100W', '10000mAh', '20000mAh', '30000mAh', '9D Glass', 'Ceramic Matte', 'MagSafe', 'Type-C', 'Lightning'];

        $productTemplates = [
            ['cat' => 'هواتف آيفون (Apple iPhone)', 'unit' => 'قطعة', 'prefix' => 'IPH', 'cost_min' => 12000, 'cost_max' => 62000, 'names' => ['iPhone 16 Pro Max', 'iPhone 16 Pro', 'iPhone 16 Plus', 'iPhone 16', 'iPhone 15 Pro Max', 'iPhone 15 Pro', 'iPhone 15 Plus', 'iPhone 15', 'iPhone 14 Pro Max', 'iPhone 14 Pro', 'iPhone 14 Plus', 'iPhone 14', 'iPhone 13 Pro Max', 'iPhone 13 Pro', 'iPhone 13', 'iPhone 12 Pro', 'iPhone 12', 'iPhone 11 Pro', 'iPhone 11']],
            ['cat' => 'هواتف سامسونج (Samsung Galaxy)', 'unit' => 'قطعة', 'prefix' => 'SAM', 'cost_min' => 4000, 'cost_max' => 58000, 'names' => ['Galaxy S24 Ultra', 'Galaxy S24 Plus', 'Galaxy S24', 'Galaxy S23 Ultra', 'Galaxy S23 FE', 'Galaxy S23', 'Galaxy Z Fold 6', 'Galaxy Z Flip 6', 'Galaxy A55 5G', 'Galaxy A54', 'Galaxy A35 5G', 'Galaxy A34', 'Galaxy A25', 'Galaxy A15 5G', 'Galaxy A15 4G', 'Galaxy A05s', 'Galaxy A05', 'Galaxy M54', 'Galaxy M34']],
            ['cat' => 'هواتف شاومي وريدمي (Xiaomi / Redmi)', 'unit' => 'قطعة', 'prefix' => 'MI', 'cost_min' => 4500, 'cost_max' => 46000, 'names' => ['Xiaomi 14 Ultra', 'Xiaomi 14 Pro', 'Xiaomi 14', 'Xiaomi 13T Pro', 'Xiaomi 13T', 'Poco F6 Pro', 'Poco F6', 'Poco X6 Pro', 'Poco X6', 'Poco M6 Pro', 'Redmi Note 13 Pro Plus', 'Redmi Note 13 Pro', 'Redmi Note 13 5G', 'Redmi Note 13 4G', 'Redmi 13', 'Redmi 13C', 'Redmi A3']],
            ['cat' => 'هواتف ريلمي وهونر (Realme & Honor)', 'unit' => 'قطعة', 'prefix' => 'RLM', 'cost_min' => 5000, 'cost_max' => 42000, 'names' => ['Realme 12 Pro Plus 5G', 'Realme 12 Plus 5G', 'Realme 12 5G', 'Realme 11 Pro Plus', 'Realme C67', 'Realme C53', 'Realme C51', 'Realme Note 50', 'Honor Magic 6 Pro', 'Honor 200 Pro', 'Honor 200', 'Honor 200 Lite', 'Honor X9b 5G', 'Honor X8b', 'Honor X7b', 'Honor X6a']],
            ['cat' => 'سماعات وإيربودز لاسلكية (Earbuds & AirPods)', 'unit' => 'قطعة', 'prefix' => 'EAR', 'cost_min' => 350, 'cost_max' => 9500, 'names' => ['AirPods Pro 2 USB-C', 'AirPods 3 Magsafe', 'AirPods 2', 'Galaxy Buds 2 Pro', 'Galaxy Buds FE', 'Sony WF-1000XM5 ANC', 'Anker Liberty 4 NC', 'Anker Soundcore R50i Extra Bass', 'Anker Life P2i', 'Joyroom JR-T03S Pro ANC', 'Joyroom JR-T03S Plus', 'Joyroom JR-PB2', 'Oraimo FreePods 4 ANC', 'Oraimo FreePods Lite 40H', 'JBL Wave Flex', 'JBL Tune Beam', 'Baseus Bowie M2 Plus', 'Baseus Bowie E16', 'Redmi Buds 5 Pro', 'Redmi Buds 5', 'Realme Buds Air 5 Pro', 'Realme Buds T110']],
            ['cat' => 'سماعات رأس وسبيكرات (Headphones & Speakers)', 'unit' => 'قطعة', 'prefix' => 'SPK', 'cost_min' => 600, 'cost_max' => 24000, 'names' => ['Sony WH-1000XM5 Hi-Res', 'AirPods Max Smart Case', 'Anker Space Q45 ANC', 'Anker Life Q30 Hybrid ANC', 'Anker Life Q20 Plus', 'JBL Tune 770NC Over-Ear', 'JBL Tune 520BT Wireless', 'JBL Charge 5 Waterproof', 'JBL Flip 6 Eco', 'JBL Go 4 Camo', 'JBL Clip 4 Portable', 'Anker Soundcore Motion Plus 30W', 'Anker Soundcore 2 12W', 'Oraimo SoundView LED Clock', 'Joyroom JR-MS01 RGB 15W', 'Redragon H510 Zeus X 7.1']],
            ['cat' => 'شواحن ورؤوس شحن سريعة (Wall Chargers)', 'unit' => 'قطعة', 'prefix' => 'CHG', 'cost_min' => 150, 'cost_max' => 1600, 'names' => ['Apple 20W USB-C Power Adapter Original', 'Samsung 45W Super Fast Charger EP-T4510', 'Samsung 25W USB-C Fast Charger EP-TA800', 'Anker 313 Ace 45W GaN', 'Anker 511 Nano 3 30W Foldable', 'Anker PowerPort III 20W Cube', 'Anker 735 GaNPrime 65W 3-Port', 'Joyroom 20W Mini PD Fast Wall Charger', 'Joyroom 30W PD+QC Dual Port', 'Joyroom 65W GaN Dual Type-C + USB', 'Oraimo PowerHub 65W Desktop Charger', 'Oraimo Firefly 20W Dual Output', 'Ugreen Nexode 65W GaN 3-Port', 'Ugreen 30W GaN Robot Fast Charger', 'Baseus GaN5 Pro 65W Fast Charger', 'Baseus Compact 20W Dual Port', 'Xiaomi 67W SonicCharge Turbo', 'Xiaomi 33W Fast Charger Dual Port', 'LDNIO 20W PD+QC Charger', 'LDNIO 65W Desktop Station 6-Port']],
            ['cat' => 'كابلات ووصلات شحن (Cables & Adapters)', 'unit' => 'قطعة', 'prefix' => 'CBL', 'cost_min' => 30, 'cost_max' => 600, 'names' => ['Original USB-C to USB-C Woven Cable 1m', 'Original USB-C to Lightning Cable 1m', 'Braided USB-C to USB-C 60W 1m', 'Bio-Braided USB-C to USB-C 140W 2m', 'PowerLine II USB-C to Lightning MFi', 'Braided 3A Fast Lightning Cable 1.2m', 'Braided 3A Fast Type-C Cable 1.2m', '100W PD Type-C to Type-C Silicone Soft', '4-in-1 Multi Fast Charging Cable 65W', '3-in-1 Heavy Duty Multi-Charging Cable', 'SpeedPro 60W Type-C to Type-C Durable', 'Cafule Braided USB to Lightning 2.4A', 'Tungsten Gold 100W Type-C Fast Cable', 'Type-C to 3.5mm Headphone Jack DAC Adapter', 'Lightning to 3.5mm Audio Adapter', '2-in-1 Dual Type-C Splitter 60W Audio', '100W Fast USB-C to USB-C Braided', 'Braided USB to Micro-USB 1m', '3A Fast Silicone Type-C 1m', 'Type-C to Type-C 5A 100W Cable']],
            ['cat' => 'باور بنك وبطاريات متنقلة (Power Banks)', 'unit' => 'قطعة', 'prefix' => 'PBK', 'cost_min' => 380, 'cost_max' => 4500, 'names' => ['737 Power Bank 24K 140W Output', '533 Power Bank 30W 10000mAh', '325 Power Bank 15W 20000mAh', '622 Magnetic Battery MagGo 5000mAh Stand', '20W Magnetic Wireless Power Bank 10000mAh', '10000mAh 12W Dual USB Power Bank', '20000mAh 15W High Capacity Power Bank', '30000mAh 15W Monster Power Bank', 'Toast 10 Byte 10000mAh Ultra-Slim', 'Traveler 3 Byte 27000mAh Triple Output', '22.5W Power Bank 10000mAh Fast Charge', '50W Power Bank 20000mAh Laptop Charging', 'Blade 100W Ultra Thin HD Display 20000mAh', 'Magnetic Mini 20W 6000mAh Wireless', '20000mAh 22.5W Fast Charging LED Screen']],
            ['cat' => 'ساعات وسوارات ذكية (Smartwatches & Bands)', 'unit' => 'قطعة', 'prefix' => 'WAT', 'cost_min' => 700, 'cost_max' => 36000, 'names' => ['Apple Watch Ultra 2 GPS + Cellular 49mm', 'Apple Watch Series 9 GPS 45mm', 'Apple Watch SE 2nd Gen GPS 44mm', 'Galaxy Watch 6 Classic 47mm Bluetooth', 'Galaxy Watch 6 44mm Graphite', 'Galaxy Fit 3 Activity Tracker', 'Huawei Watch GT 4 46mm Stainless Steel', 'Huawei Band 9 Ultra-Thin Smart Band', 'Xiaomi Watch S3 Bluetooth Calling 1.43', 'Xiaomi Smart Band 8 Pro AMOLED', 'Xiaomi Smart Band 8 Metallic Frame', 'Joyroom JR-FC2 Classic Smartwatch BT Call', 'Joyroom JR-FT5 Smart Watch HD 1.83', 'Oraimo Watch 4 Plus 2.01-inch HD Screen']],
            ['cat' => 'جرابات وكفرات حماية (Cases & Covers)', 'unit' => 'قطعة', 'prefix' => 'COV', 'cost_min' => 45, 'cost_max' => 600, 'names' => ['Original Silicone Case with MagSafe', 'Clear Hybrid Shockproof Case MagSafe', 'Leather Wallet Case with Stand MagSafe', 'Luxury Matte Magnetic Armor Case', 'Clear Transparent Anti-Yellowing Slim Cover', 'Electroplated Luxury MagSafe Case', 'Liquid Silicone Soft Touch Microfiber Lining', 'Heavy Duty Rugged Armor Kickstand Case', 'Smart View Wallet Cover', 'Magnetic Frosted Shockproof Case', 'Protective Standing Cover Navy', 'Armor Carbon Fiber Matte Silicone Case', 'Soft Clear Air-Cushion TPU Cover', 'Hybrid Shockproof Matte Color Case', 'Slide Camera Lens Protection Case', 'Candy Color Silicone Slim Case', 'Rugged Anti-Drop Bumper Cover', 'Armor Protective Case with Keychain Clip', 'Cute Cartoon Silicone Shockproof Case', 'Carbon Fiber Pattern Hard Protective Case']],
            ['cat' => 'اسكرينات وشاشات حماية (Screen Protectors)', 'unit' => 'قطعة', 'prefix' => 'SCR', 'cost_min' => 35, 'cost_max' => 160, 'names' => ['Privacy Anti-Spy 9D Full Tempered Glass', '9D Curved Full Edge Screen Protector', 'Anti-Static Matte Gaming Glass Protector', 'Individual Titanium Camera Lens Protectors', '9D Full Glue High Clarity Tempered Glass', 'Privacy Matte Ceramic Flexible Film', '9D Tempered Glass Screen Protector', 'Full Cover Tempered Glass 2.5D Edge', 'UV Liquid Curved Full Adhesive Glass', 'Full Cover Tempered Glass + Camera Glass', 'UV Glue Curved Tempered Glass Protector', '9D Full Coverage Tempered Glass', 'High Definition Clear Screen Protector', 'Privacy Anti-Peep Tempered Glass Screen', '9D Tempered Glass with Installation Tray']],
            ['cat' => 'حوامل وإكسسوارات سيارات (Car Holders & Magsafe)', 'unit' => 'قطعة', 'prefix' => 'CAR', 'cost_min' => 65, 'cost_max' => 700, 'names' => ['15W MagSafe Wireless Car Charger Mount', 'Electric Auto-Clamping Car Air Vent Holder', 'Dual Coil 15W Auto Clamping Dashboard Mount', '323 52.5W High Speed 2-Port Car Charger', 'PowerDrive 2 Alloy 24W Metal Dual USB', '65W Digital Display QC+PD Car Charger', 'MagPro Series Magnetic Car Phone Holder', 'Highway 22.5W Fast Charging Car Charger', 'Universal 360 Rotation Metal Phone Stand', 'Foldable Aluminum Pocket Smartphone Cradle', 'Selfie Stick Tripod with Bluetooth Remote', 'Stylus Touch Pen for iPad & Capacitive Screens']],
            ['cat' => 'فلاشات وكروت ميموري (Storage & Memory)', 'unit' => 'قطعة', 'prefix' => 'MEM', 'cost_min' => 150, 'cost_max' => 850, 'names' => ['DataTraveler MicroDuo 3C 64GB Dual USB Type-A/C', 'DataTraveler MicroDuo 3C 128GB Dual Type-C', 'Ultra Dual Drive Luxe 128GB Metal Type-C OTG', 'Ultra Dual Drive Luxe 256GB Metal Type-C OTG', 'MicroSDXC SanDisk Extreme 128GB 190MB/s 4K', 'MicroSDXC SanDisk Extreme 256GB 190MB/s 4K', 'MicroSDXC Kingston Canvas Select Plus 64GB', 'MicroSDXC Kingston Canvas Select Plus 128GB', 'High Speed USB 3.2 Flash Drive 64GB Metal', 'High Speed USB 3.2 Flash Drive 128GB Metal']],
            ['cat' => 'قطع غيار وصيانة (Spare Parts & Tools)', 'unit' => 'قطعة', 'prefix' => 'PRT', 'cost_min' => 80, 'cost_max' => 4500, 'names' => ['OLED Original Screen Replacement', 'Incell Premium Display Screen Assembly', 'High Capacity Battery Replacement 100% Health', 'Original Charging Port Flex Cable PCB', 'Camera Glass Lens Replacement Set', 'Rear Glass Housing Back Cover Replacement', 'Precision Screwdriver Repair Kit 115-in-1', 'B-7000 Multi-Purpose Waterproof Frame Glue', 'LCD Screen OCA Vacuum Laminating OCA Sheet', 'Anti-Static Heat Resistant Silicone Work Mat']],
            ['cat' => 'ألعاب وإلكترونيات ذكية (Gaming & Smart Gadgets)', 'unit' => 'قطعة', 'prefix' => 'GMG', 'cost_min' => 120, 'cost_max' => 2800, 'names' => ['Mobile Gaming Controller Triggers RGB L1 R1', 'Semiconductor Phone Cooler Magnetic Radiator', 'RGB Wireless Gaming Mouse 7-Buttons 7200DPI', 'Mechanical Gaming Keyboard 60% RGB Hot-Swap', 'Smart WiFi LED Strip Light RGBIC 5m App Sync', 'Smart Security WiFi IP Camera 360 2K Night Vision', 'Smart Plug 16A Energy Monitoring WiFi', 'Mini Portable Thermal Receipt & Label Printer BT']],
        ];

        $targetTotal = 10000;
        $itemsToInsert = [];
        $stocksToInsert = [];
        $now = now()->toDateTimeString();

        $itemIndex = 1;

        // Loop until we reach 10,000 items
        while ($itemIndex <= $targetTotal) {
            foreach ($productTemplates as $tmpl) {
                if ($itemIndex > $targetTotal) break;

                $catName = $tmpl['cat'];
                $catId = $categoryIds[$catName] ?? null;
                $prefix = $tmpl['prefix'];
                $unit = $tmpl['unit'];

                foreach ($tmpl['names'] as $baseName) {
                    if ($itemIndex > $targetTotal) break;

                    $color = $colors[($itemIndex * 3 + 7) % count($colors)];
                    $capacity = $capacities[($itemIndex * 5 + 11) % count($capacities)];
                    $brand = $brands[($itemIndex * 2 + 5) % count($brands)];

                    // Generate distinct title
                    if (str_contains($baseName, 'iPhone') || str_contains($baseName, 'Galaxy') || str_contains($baseName, 'Xiaomi') || str_contains($baseName, 'Realme') || str_contains($baseName, 'Honor') || str_contains($baseName, 'Poco') || str_contains($baseName, 'Redmi')) {
                        $itemName = "{$baseName} {$capacity} ({$color})";
                    } elseif (str_contains($baseName, 'Case') || str_contains($baseName, 'Cover') || str_contains($baseName, 'Protector') || str_contains($baseName, 'Screen')) {
                        $targetPhone = $tmpl['names'][($itemIndex + 3) % count($tmpl['names'])];
                        $itemName = "{$baseName} for {$targetPhone} [{$color}]";
                    } else {
                        $itemName = "{$brand} {$baseName} - {$capacity} [{$color}]";
                    }

                    $code = sprintf("%s-%05d", $prefix, $itemIndex);

                    // Financial Logic: Clean integer prices without fractions
                    $costBase = ($tmpl['cost_min'] + (($itemIndex * 137) % ($tmpl['cost_max'] - $tmpl['cost_min'] + 1)));
                    $costPrice = (float) round($costBase);
                    $margin = 1.15 + ((($itemIndex * 19) % 25) / 100); // 15% to 40% margin
                    $sellingPrice = (float) round($costPrice * $margin);
                    $minSellingPrice = (float) round($costPrice * ($margin * 0.93)); // wholesale discount
                    $qty = 5 + (($itemIndex * 7) % 95); // 5 to 100 stock

                    $itemsToInsert[] = [
                        'code' => $code,
                        'name' => $itemName,
                        'category' => $catName,
                        'category_id' => $catId,
                        'unit' => $unit,
                        'cost_price' => $costPrice,
                        'weighted_avg_cost' => $costPrice,
                        'selling_price' => $sellingPrice,
                        'min_selling_price' => $minSellingPrice,
                        'min_stock_level' => 5,
                        'current_stock' => $qty,
                        'is_active' => 1,
                        'notes' => "Automatic high-speed generation #{$itemIndex}",
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $itemIndex++;

                    // Chunk insert every 1000 items
                    if (count($itemsToInsert) >= 1000) {
                        $this->flushChunk($itemsToInsert, $mainStore->id);
                        $itemsToInsert = [];
                    }
                }
            }
        }

        if (!empty($itemsToInsert)) {
            $this->flushChunk($itemsToInsert, $mainStore->id);
        }

        echo "Successfully generated and seeded 10,000 distinct items into tenant database!\n";
    }

    private function flushChunk(array $itemsChunk, int $storeId): void
    {
        DB::table('items')->upsert(
            $itemsChunk,
            ['code'],
            ['name', 'category', 'category_id', 'unit', 'cost_price', 'weighted_avg_cost', 'selling_price', 'min_selling_price', 'current_stock', 'is_active', 'updated_at']
        );

        // Fetch inserted IDs to sync with store_stocks
        $codes = array_column($itemsChunk, 'code');
        $savedItems = DB::table('items')->whereIn('code', $codes)->get(['id', 'code', 'current_stock']);

        $stockRows = [];
        $now = now()->toDateTimeString();
        foreach ($savedItems as $it) {
            $stockRows[] = [
                'store_id' => $storeId,
                'item_id' => $it->id,
                'quantity' => $it->current_stock,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('store_stocks')->upsert(
            $stockRows,
            ['store_id', 'item_id'],
            ['quantity', 'updated_at']
        );
    }
}