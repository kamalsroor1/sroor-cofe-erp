<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use Illuminate\Support\Facades\DB;

class PhoneAndAccessoriesSeeder extends Seeder
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

        $categoriesData = [
            [
                'name' => 'هواتف آيفون (Apple iPhone)',
                'icon' => '🍏',
                'sort_order' => 1,
                'items' => [
                    ['Apple iPhone 16 Pro Max 256GB Desert Titanium', 'IPH-16PM-256', 64000, 68500, 66500, 15],
                    ['Apple iPhone 16 Pro 256GB Black Titanium', 'IPH-16P-256', 58000, 62000, 60000, 12],
                    ['Apple iPhone 16 128GB Ultramarine Blue', 'IPH-16-128', 42000, 45500, 44000, 18],
                    ['Apple iPhone 15 Pro Max 256GB Natural Titanium', 'IPH-15PM-256', 52000, 56000, 54500, 20],
                    ['Apple iPhone 15 Pro Max 512GB Blue Titanium', 'IPH-15PM-512', 59000, 63500, 61500, 8],
                    ['Apple iPhone 15 Pro 128GB White Titanium', 'IPH-15P-128', 45000, 48500, 47000, 15],
                    ['Apple iPhone 15 Pro 256GB Black Titanium', 'IPH-15P-256', 48500, 52000, 50500, 14],
                    ['Apple iPhone 15 128GB Black', 'IPH-15-128-BK', 34500, 37500, 36200, 25],
                    ['Apple iPhone 15 128GB Pink', 'IPH-15-128-PK', 34500, 37500, 36200, 18],
                    ['Apple iPhone 15 Plus 128GB Blue', 'IPH-15PL-128', 38000, 41000, 39800, 10],
                    ['Apple iPhone 14 Pro Max 128GB Deep Purple', 'IPH-14PM-128', 42000, 45500, 44000, 10],
                    ['Apple iPhone 14 Pro 128GB Space Black', 'IPH-14P-128', 37000, 40000, 38800, 12],
                    ['Apple iPhone 14 128GB Midnight Black', 'IPH-14-128-BK', 29000, 31500, 30500, 22],
                    ['Apple iPhone 14 128GB Starlight White', 'IPH-14-128-WH', 29000, 31500, 30500, 18],
                    ['Apple iPhone 13 128GB Midnight Black', 'IPH-13-128-BK', 25500, 27800, 26900, 30],
                    ['Apple iPhone 13 128GB Blue', 'IPH-13-128-BL', 25500, 27800, 26900, 24],
                    ['Apple iPhone 13 128GB Pink', 'IPH-13-128-PK', 25500, 27800, 26900, 16],
                    ['Apple iPhone 12 128GB Black', 'IPH-12-128-BK', 20500, 22500, 21800, 15],
                    ['Apple iPhone 11 128GB Black (Refurbished)', 'IPH-11-128-BK', 14500, 16200, 15500, 20],
                    ['Apple iPhone 11 64GB White (Refurbished)', 'IPH-11-64-WH', 12500, 14000, 13400, 15],
                ]
            ],
            [
                'name' => 'هواتف سامسونج (Samsung Galaxy)',
                'icon' => '📱',
                'sort_order' => 2,
                'items' => [
                    ['Samsung Galaxy S24 Ultra 256GB Titanium Gray', 'SAM-S24U-256', 49000, 53500, 51800, 16],
                    ['Samsung Galaxy S24 Ultra 512GB Titanium Black', 'SAM-S24U-512', 56000, 60500, 58500, 10],
                    ['Samsung Galaxy S24 Plus 256GB Onyx Black', 'SAM-S24P-256', 38000, 41500, 40000, 12],
                    ['Samsung Galaxy S24 256GB Marble Gray', 'SAM-S24-256', 31000, 34000, 32800, 18],
                    ['Samsung Galaxy S23 Ultra 256GB Phantom Black', 'SAM-S23U-256', 39000, 42500, 41000, 14],
                    ['Samsung Galaxy S23 FE 256GB Mint', 'SAM-S23FE-256', 23500, 25800, 24900, 20],
                    ['Samsung Galaxy Z Fold 6 256GB Silver Shadow', 'SAM-ZF6-256', 68000, 74000, 71500, 6],
                    ['Samsung Galaxy Z Flip 6 256GB Blue', 'SAM-ZFL6-256', 41000, 44500, 43000, 8],
                    ['Samsung Galaxy A55 5G 256GB Awesome Navy', 'SAM-A55-256', 17200, 18900, 18200, 35],
                    ['Samsung Galaxy A55 5G 128GB Awesome IceBlue', 'SAM-A55-128', 15600, 17200, 16500, 28],
                    ['Samsung Galaxy A35 5G 128GB Awesome Lilac', 'SAM-A35-128', 12400, 13800, 13200, 30],
                    ['Samsung Galaxy A35 5G 256GB Awesome Navy', 'SAM-A35-256', 13800, 15200, 14600, 25],
                    ['Samsung Galaxy A25 5G 128GB Blue Black', 'SAM-A25-128', 9200, 10300, 9800, 40],
                    ['Samsung Galaxy A15 128GB 6GB RAM Blue', 'SAM-A15-128-6', 6700, 7500, 7150, 50],
                    ['Samsung Galaxy A15 128GB 4GB RAM Black', 'SAM-A15-128-4', 6100, 6850, 6500, 45],
                    ['Samsung Galaxy A05s 128GB Black', 'SAM-A05S-128', 5100, 5750, 5450, 40],
                    ['Samsung Galaxy A05 64GB Silver', 'SAM-A05-64', 4100, 4650, 4400, 50],
                ]
            ],
            [
                'name' => 'هواتف شاومي وريلمي (Xiaomi & Realme)',
                'icon' => '⚡',
                'sort_order' => 3,
                'items' => [
                    ['Xiaomi 14 Ultra 512GB Black Titanium', 'MI-14U-512', 48000, 52500, 50800, 6],
                    ['Xiaomi 14 512GB White', 'MI-14-512', 33000, 36000, 34800, 10],
                    ['Poco F6 Pro 512GB 16GB RAM Black', 'POCO-F6P-512', 23500, 25800, 24800, 18],
                    ['Poco X6 Pro 512GB 12GB RAM Yellow', 'POCO-X6P-512', 15800, 17400, 16700, 30],
                    ['Redmi Note 13 Pro Plus 5G 512GB Midnight Black', 'RED-N13PP-512', 17800, 19500, 18700, 25],
                    ['Redmi Note 13 Pro 4G 256GB Forest Green', 'RED-N13P-256', 11400, 12600, 12100, 35],
                    ['Redmi Note 13 4G 128GB 6GB RAM Mint Green', 'RED-N13-128', 7600, 8450, 8100, 45],
                    ['Redmi 13 128GB 6GB RAM Midnight Black', 'RED-13-128', 5800, 6500, 6200, 40],
                    ['Redmi 13C 128GB 4GB RAM Navy Blue', 'RED-13C-128', 4900, 5500, 5250, 55],
                    ['Realme 12 Pro Plus 5G 512GB Submarine Blue', 'RLM-12PP-512', 17500, 19200, 18500, 20],
                    ['Realme 12 Plus 5G 256GB Pioneer Green', 'RLM-12P-256', 12300, 13600, 13000, 25],
                    ['Realme C67 256GB 8GB RAM Black Rock', 'RLM-C67-256', 7400, 8250, 7900, 35],
                    ['Realme C53 128GB 6GB RAM Champion Gold', 'RLM-C53-128', 5600, 6300, 6000, 40],
                    ['Honor Magic 6 Pro 512GB Epi Green', 'HON-M6P-512', 43000, 47000, 45500, 8],
                    ['Honor 200 Pro 512GB Ocean Cyan', 'HON-200P-512', 26000, 28500, 27400, 15],
                    ['Honor X9b 5G 256GB Sunrise Orange', 'HON-X9B-256', 13800, 15200, 14600, 28],
                ]
            ],
            [
                'name' => 'سماعات وإيربودز لاسلكية (Earbuds & AirPods)',
                'icon' => '🎧',
                'sort_order' => 4,
                'items' => [
                    ['Apple AirPods Pro (2nd Generation) Type-C MagSafe', 'AUD-APP2-USBC', 9800, 11200, 10600, 25],
                    ['Apple AirPods 3rd Generation with Lightning Case', 'AUD-AP3-LGT', 6700, 7650, 7250, 20],
                    ['Apple AirPods 2nd Generation with Charging Case', 'AUD-AP2-STD', 4800, 5500, 5200, 30],
                    ['Samsung Galaxy Buds 2 Pro Graphite (ANC)', 'AUD-BUDS2P-GR', 5200, 6000, 5650, 22],
                    ['Samsung Galaxy Buds FE White (ANC)', 'AUD-BUDSFE-WH', 2900, 3450, 3200, 35],
                    ['Sony WF-1000XM5 Industry Leading Noise Canceling', 'AUD-SONY-XM5', 9200, 10500, 9900, 12],
                    ['Anker Soundcore Liberty 4 NC Wireless Earbuds', 'AUD-ANK-LIB4NC', 3400, 3950, 3700, 40],
                    ['Anker Soundcore Life P2i True Wireless Black', 'AUD-ANK-P2I-BK', 950, 1200, 1100, 60],
                    ['Anker Soundcore R50i True Wireless Earbuds Extra Bass', 'AUD-ANK-R50I', 750, 950, 870, 80],
                    ['Joyroom JR-T03S Pro ANC True Wireless Earbuds', 'AUD-JOY-T03SPRO', 850, 1100, 1000, 75],
                    ['Joyroom JR-T03S Plus Bluetooth 5.3 Pop-Up Window', 'AUD-JOY-T03SPL', 620, 800, 730, 90],
                    ['Joyroom JR-PB2 TWS Earbuds 28h Playtime', 'AUD-JOY-PB2', 450, 600, 540, 70],
                    ['Oraimo FreePods 4 Active Noise Cancellation TWS', 'AUD-ORA-FP4', 1150, 1450, 1320, 50],
                    ['Oraimo FreePods Lite 40-Hour Playtime HeavyBass', 'AUD-ORA-FPLITE', 680, 870, 790, 65],
                    ['JBL Wave Flex True Wireless Earbuds Deep Bass', 'AUD-JBL-WAVEFLX', 1850, 2250, 2080, 35],
                    ['JBL Tune Beam ANC Wireless Earbuds Black', 'AUD-JBL-TBEAM', 2600, 3100, 2890, 25],
                    ['Baseus Bowie M2 Plus ANC Wireless Earbuds 48dB', 'AUD-BAS-M2P', 1400, 1750, 1600, 35],
                    ['Baseus Bowie E16 True Wireless 30H Battery', 'AUD-BAS-E16', 580, 750, 680, 50],
                    ['Redmi Buds 5 Pro Hi-Res Audio 52dB ANC', 'AUD-RED-B5PRO', 2100, 2550, 2380, 30],
                    ['Redmi Buds 5 46dB Hybrid ANC White', 'AUD-RED-B5-WH', 1300, 1600, 1480, 40],
                    ['Realme Buds Air 5 Pro 50dB Active Noise Cancellation', 'AUD-RLM-BA5P', 2700, 3200, 2980, 20],
                    ['Realme Buds T110 AI ENC 38H Playback', 'AUD-RLM-T110', 690, 890, 800, 55],
                ]
            ],
            [
                'name' => 'سماعات رأس وسبيكرات (Headphones & Speakers)',
                'icon' => '🔊',
                'sort_order' => 5,
                'items' => [
                    ['Sony WH-1000XM5 Wireless Noise Canceling Headphones', 'HDP-SONY-WHXM5', 14500, 16800, 15800, 8],
                    ['Apple AirPods Max Space Gray with Smart Case', 'HDP-AP-MAX-GR', 25000, 28500, 27000, 5],
                    ['Anker Soundcore Space Q45 Adaptive ANC Headphones', 'HDP-ANK-Q45', 4600, 5300, 5000, 18],
                    ['Anker Soundcore Life Q30 Hybrid Active Noise Canceling', 'HDP-ANK-Q30', 2750, 3250, 3050, 25],
                    ['Anker Soundcore Life Q20 Plus ANC Black', 'HDP-ANK-Q20P', 1950, 2350, 2180, 30],
                    ['JBL Tune 770NC Wireless Over-Ear NC Headphones', 'HDP-JBL-770NC', 3400, 3950, 3720, 20],
                    ['JBL Tune 520BT Wireless On-Ear Pure Bass Sound', 'HDP-JBL-520BT', 1600, 1950, 1800, 35],
                    ['JBL Charge 5 Waterproof Portable Bluetooth Speaker', 'SPK-JBL-CHG5', 6200, 7200, 6800, 15],
                    ['JBL Flip 6 Eco-Friendly Portable Waterproof Speaker', 'SPK-JBL-FLP6', 4400, 5150, 4800, 20],
                    ['JBL Go 4 Ultra-Compact Bluetooth Speaker Camo', 'SPK-JBL-GO4', 1650, 1980, 1850, 40],
                    ['Anker Soundcore Motion Plus 30W Hi-Res Speaker', 'SPK-ANK-MOTP', 3500, 4100, 3850, 22],
                    ['Anker Soundcore 2 Portable 12W Bluetooth Speaker', 'SPK-ANK-SC2', 1350, 1650, 1520, 45],
                    ['Oraimo SoundView Bluetooth Speaker with LED Clock', 'SPK-ORA-SNDV', 850, 1100, 990, 30],
                    ['Joyroom JR-MS01 RGB Bluetooth Speaker 15W', 'SPK-JOY-MS01', 650, 850, 770, 35],
                    ['Redragon H510 Zeus X RGB 7.1 Gaming Headset', 'HDP-RED-H510X', 1750, 2150, 1980, 25],
                ]
            ],
            [
                'name' => 'شواحن ورؤوس شحن سريعة (Wall Chargers)',
                'icon' => '🔌',
                'sort_order' => 6,
                'items' => [
                    ['Apple 20W USB-C Power Adapter (Original)', 'CHG-APP-20W', 850, 1150, 1020, 80],
                    ['Samsung 45W Super Fast Charging 2.0 Adapter EP-T4510', 'CHG-SAM-45W', 950, 1250, 1120, 65],
                    ['Samsung 25W USB-C Fast Charger EP-TA800 (Original)', 'CHG-SAM-25W', 550, 750, 660, 100],
                    ['Anker 313 Charger (Ace 45W) Samsung Super Fast Charging', 'CHG-ANK-45W', 780, 990, 890, 50],
                    ['Anker 511 Charger (Nano 3 30W) Foldable GaN Charger', 'CHG-ANK-30W', 620, 820, 730, 60],
                    ['Anker PowerPort III 20W Cube USB-C Fast Wall Charger', 'CHG-ANK-20W', 420, 580, 510, 120],
                    ['Anker 735 GaNPrime 65W 3-Port Fast Charger (2C1A)', 'CHG-ANK-65W3P', 1450, 1800, 1650, 30],
                    ['Joyroom L-QP207 20W Mini PD Fast Wall Charger', 'CHG-JOY-20W', 220, 320, 280, 150],
                    ['Joyroom JR-TCF06 30W PD+QC3.0 Dual Port Fast Charger', 'CHG-JOY-30W2P', 320, 440, 390, 100],
                    ['Joyroom JR-TCF02 65W GaN Dual Type-C + USB Fast Charger', 'CHG-JOY-65W', 750, 980, 880, 45],
                    ['Oraimo PowerHub 65W GaN Multi-Port Desktop Charger', 'CHG-ORA-65W', 1100, 1400, 1280, 25],
                    ['Oraimo Firefly 20W PD Dual Output Wall Charger', 'CHG-ORA-20W', 280, 390, 340, 90],
                    ['Ugreen Nexode 65W GaN 3-Port Wall Charger USB-C', 'CHG-UGR-65W', 1250, 1580, 1450, 35],
                    ['Ugreen 30W GaN Fast Charger Robot Design', 'CHG-UGR-30WR', 580, 750, 680, 45],
                    ['Baseus GaN5 Pro 65W Fast Charger (2C+1U) Black', 'CHG-BAS-65W', 980, 1250, 1140, 40],
                    ['Baseus Compact Quick Charger 20W Dual Port', 'CHG-BAS-20W', 290, 400, 350, 85],
                    ['Xiaomi 67W SonicCharge 3.0 Fast Turbo Charger Combo', 'CHG-MI-67W', 720, 950, 850, 50],
                    ['Xiaomi 33W Fast Charger Type-A + Type-C Dual Port', 'CHG-MI-33W', 420, 560, 500, 60],
                    ['LDNIO A2318M 20W PD+QC3.0 LED Display Charger', 'CHG-LDN-20W', 190, 280, 240, 110],
                    ['LDNIO 65W Multi-Port Desktop Charging Station 6-Ports', 'CHG-LDN-65W6P', 680, 890, 800, 40],
                ]
            ],
            [
                'name' => 'كابلات ووصلات شحن (Cables & Adapters)',
                'icon' => '🧶',
                'sort_order' => 7,
                'items' => [
                    ['Apple Original USB-C to USB-C Woven Cable (1m)', 'CBL-APP-CC1M', 650, 850, 760, 60],
                    ['Apple Original USB-C to Lightning Cable (1m)', 'CBL-APP-CL1M', 580, 780, 690, 70],
                    ['Anker 322 Braided USB-C to USB-C 60W Cable (0.9m)', 'CBL-ANK-CC09', 180, 260, 225, 120],
                    ['Anker 543 Bio-Braided USB-C to USB-C 140W (1.8m)', 'CBL-ANK-CC140W', 340, 480, 420, 60],
                    ['Anker PowerLine II USB-C to Lightning MFi Cable (1m)', 'CBL-ANK-CL1M', 280, 390, 340, 80],
                    ['Joyroom S-1230K4 Braided 3A Fast Lightning Cable (1.2m)', 'CBL-JOY-LGT12', 75, 120, 100, 200],
                    ['Joyroom S-1230K3 Braided 3A Fast Type-C Cable (1.2m)', 'CBL-JOY-TC12', 70, 110, 95, 220],
                    ['Joyroom 100W PD Type-C to Type-C Silicone Soft Cable (1.2m)', 'CBL-JOY-CC100W', 120, 180, 155, 150],
                    ['Joyroom 4-in-1 Multi Fast Charging Cable 65W (1.2m)', 'CBL-JOY-4IN1', 190, 280, 240, 100],
                    ['Oraimo 3-in-1 Heavy Duty Braided Multi-Charging Cable', 'CBL-ORA-3IN1', 140, 210, 180, 130],
                    ['Oraimo SpeedPro 60W Type-C to Type-C Ultra-Durable (1m)', 'CBL-ORA-CC60W', 95, 150, 130, 160],
                    ['Baseus Cafule Braided USB to Lightning 2.4A Cable (2m)', 'CBL-BAS-LGT2M', 110, 170, 145, 120],
                    ['Baseus Tungsten Gold 100W Type-C to Type-C Fast Cable', 'CBL-BAS-CC100W', 160, 240, 210, 110],
                    ['Ugreen Type-C to 3.5mm Headphone Jack DAC Adapter', 'ADP-UGR-C35', 180, 260, 230, 80],
                    ['Apple Lightning to 3.5mm Headphone Jack Adapter', 'ADP-APP-LGT35', 380, 520, 460, 50],
                    ['Joyroom 2-in-1 Dual Type-C Splitter (Audio + 60W Charge)', 'ADP-JOY-DUALC', 140, 210, 180, 75],
                    ['LDNIO 100W Fast USB-C to USB-C Braided Cable (1m)', 'CBL-LDN-100W', 85, 135, 115, 150],
                    ['Hoco X14 Braided USB to Micro-USB Cable (1m)', 'CBL-HOC-MIC1M', 35, 65, 52, 180],
                    ['Hoco X88 Type-C 3A Fast Silicone Cable 1m', 'CBL-HOC-TC1M', 45, 75, 60, 200],
                    ['Samsung Type-C to Type-C 5A 100W Cable (1m)', 'CBL-SAM-5ACC', 190, 280, 245, 90],
                ]
            ],
            [
                'name' => 'باور بنك وبطاريات متنقلة (Power Banks)',
                'icon' => '🔋',
                'sort_order' => 8,
                'items' => [
                    ['Anker 737 Power Bank (PowerCore 24K) 140W Output', 'PB-ANK-737', 4800, 5600, 5300, 10],
                    ['Anker 533 Power Bank (PowerCore 30W) 10000mAh', 'PB-ANK-10K30W', 1750, 2150, 1980, 30],
                    ['Anker 325 Power Bank (PowerCore 20K) 15W 20000mAh', 'PB-ANK-20K15W', 1450, 1780, 1650, 40],
                    ['Anker 622 Magnetic Battery (MagGo) 5000mAh Foldable Stand', 'PB-ANK-MAG5K', 1600, 1950, 1820, 35],
                    ['Joyroom JR-W020 20W Magnetic Wireless Power Bank 10000mAh', 'PB-JOY-MAG10K', 850, 1100, 990, 50],
                    ['Joyroom JR-T012 10000mAh 12W Dual USB Power Bank', 'PB-JOY-10K12W', 420, 560, 490, 80],
                    ['Joyroom JR-T014 20000mAh 15W High Capacity Power Bank', 'PB-JOY-20K15W', 680, 880, 790, 60],
                    ['Joyroom JR-T015 30000mAh 15W Monster Power Bank', 'PB-JOY-30K15W', 950, 1250, 1120, 35],
                    ['Oraimo Toast 10 Byte 10000mAh 12W Ultra-Slim Power Bank', 'PB-ORA-10KSLM', 460, 600, 540, 70],
                    ['Oraimo Traveler 3 Byte 27000mAh 12W Triple Output', 'PB-ORA-27K', 880, 1150, 1040, 40],
                    ['Xiaomi 22.5W Power Bank 10000mAh Fast Charge Type-C', 'PB-MI-10K22W', 620, 800, 720, 60],
                    ['Xiaomi 50W Power Bank 20000mAh Laptop Charging', 'PB-MI-20K50W', 1650, 1980, 1850, 25],
                    ['Baseus Blade 100W Ultra Thin HD Display Power Bank 20000mAh', 'PB-BAS-BLD100W', 2900, 3450, 3250, 15],
                    ['Baseus Magnetic Mini 20W 6000mAh Wireless Power Bank', 'PB-BAS-MAG6K', 890, 1150, 1040, 45],
                    ['LDNIO 20000mAh 22.5W Fast Charging Power Bank LED Screen', 'PB-LDN-20K22W', 590, 780, 690, 55],
                ]
            ],
            [
                'name' => 'ساعات وسوارات ذكية (Smartwatches & Bands)',
                'icon' => '⌚',
                'sort_order' => 9,
                'items' => [
                    ['Apple Watch Ultra 2 GPS + Cellular 49mm Titanium Case', 'WAT-APP-ULT2', 38000, 42500, 40800, 6],
                    ['Apple Watch Series 9 GPS 45mm Midnight Aluminum', 'WAT-APP-S9-45', 18500, 21000, 20000, 10],
                    ['Apple Watch SE (2nd Gen) GPS 44mm Starlight', 'WAT-APP-SE-44', 11800, 13500, 12800, 15],
                    ['Samsung Galaxy Watch 6 Classic 47mm Bluetooth Black', 'WAT-SAM-W6C47', 12500, 14200, 13500, 12],
                    ['Samsung Galaxy Watch 6 44mm Graphite', 'WAT-SAM-W6-44', 8900, 10200, 9600, 18],
                    ['Samsung Galaxy Fit 3 Activity Tracker Dark Gray', 'WAT-SAM-FIT3', 2100, 2500, 2350, 35],
                    ['Huawei Watch GT 4 46mm Stainless Steel Black Strap', 'WAT-HUA-GT4', 8200, 9400, 8900, 15],
                    ['Huawei Band 9 Ultra-Thin Health Monitoring Smart Band', 'WAT-HUA-BND9', 1450, 1750, 1620, 40],
                    ['Xiaomi Watch S3 Bluetooth Calling 1.43 AMOLED', 'WAT-MI-S3', 4400, 5100, 4800, 20],
                    ['Xiaomi Smart Band 8 Pro Large AMOLED Display', 'WAT-MI-BND8P', 2250, 2650, 2480, 35],
                    ['Xiaomi Smart Band 8 Metallic Frame 60Hz', 'WAT-MI-BND8', 1200, 1480, 1360, 50],
                    ['Joyroom JR-FC2 Classic Smartwatch BT Call IP68', 'WAT-JOY-FC2', 1150, 1450, 1320, 45],
                    ['Joyroom JR-FT5 Smart Watch HD 1.83-inch Bluetooth Call', 'WAT-JOY-FT5', 850, 1100, 990, 55],
                    ['Oraimo Watch 4 Plus 2.01-inch HD Screen Wireless Call', 'WAT-ORA-W4P', 1050, 1350, 1220, 40],
                    ['Oraimo Smart Clipper Multi-Functional Trimmer', 'ACC-ORA-CLIP', 680, 890, 800, 30],
                ]
            ],
            [
                'name' => 'جرابات وكفرات حماية (Cases & Covers)',
                'icon' => '🛡️',
                'sort_order' => 10,
                'items' => [
                    ['iPhone 16 Pro Max Original Silicone Case with MagSafe', 'COV-IP16PM-MAG', 650, 950, 820, 50],
                    ['iPhone 16 Pro Clear Hybrid Shockproof Case MagSafe', 'COV-IP16P-CLR', 280, 420, 360, 60],
                    ['iPhone 15 Pro Max Leather Wallet Case with Stand MagSafe', 'COV-IP15PM-LTH', 450, 650, 570, 45],
                    ['iPhone 15 Pro Max Luxury Matte Magnetic Armor Case', 'COV-IP15PM-MAT', 220, 350, 290, 80],
                    ['iPhone 15 Clear Transparent Anti-Yellowing Slim Cover', 'COV-IP15-CLR', 110, 180, 150, 120],
                    ['iPhone 14 Pro Max Electroplated Luxury MagSafe Case', 'COV-IP14PM-PLT', 180, 280, 240, 75],
                    ['iPhone 13 Liquid Silicone Soft Touch Microfiber Lining Case', 'COV-IP13-SIL', 120, 190, 160, 100],
                    ['iPhone 11 Heavy Duty Rugged Armor Kickstand Case', 'COV-IP11-ARM', 140, 220, 185, 90],
                    ['Samsung S24 Ultra Smart View Wallet Cover', 'COV-S24U-SMTV', 550, 780, 680, 35],
                    ['Samsung S24 Ultra Magnetic Frosted Shockproof Case', 'COV-S24U-MAG', 240, 360, 310, 60],
                    ['Samsung S23 Ultra Protective Standing Cover Navy', 'COV-S23U-STND', 320, 480, 410, 45],
                    ['Samsung A55 5G Armor Carbon Fiber Matte Silicone Case', 'COV-A55-CRB', 85, 140, 115, 110],
                    ['Samsung A35 5G Soft Clear Air-Cushion TPU Cover', 'COV-A35-TPU', 65, 110, 90, 130],
                    ['Samsung A15 4G/5G Hybrid Shockproof Matte Color Case', 'COV-A15-HYB', 75, 125, 105, 140],
                    ['Redmi Note 13 Pro 4G Slide Camera Lens Protection Case', 'COV-RN13P-CAM', 90, 150, 125, 95],
                    ['Redmi 13C Candy Color Silicone Slim Case', 'COV-R13C-SIL', 55, 95, 80, 150],
                    ['Poco X6 Pro Rugged Anti-Drop Bumper Cover', 'COV-PX6P-RUG', 110, 175, 150, 80],
                    ['AirPods Pro 2 Armor Protective Case with Keychain Clip', 'COV-APP2-ARM', 95, 150, 125, 90],
                    ['AirPods 3 Cute Cartoon Silicone Shockproof Case', 'COV-AP3-CRT', 65, 110, 90, 100],
                    ['AirPods 2 Carbon Fiber Pattern Hard Protective Case', 'COV-AP2-CRB', 75, 120, 100, 80],
                ]
            ],
            [
                'name' => 'اسكرينات وشاشات حماية (Screen Protectors)',
                'icon' => '📲',
                'sort_order' => 11,
                'items' => [
                    ['iPhone 16 Pro Max Privacy Anti-Spy 9D Full Tempered Glass', 'SCR-IP16PM-PRV', 120, 200, 165, 120],
                    ['iPhone 16 Pro 9D Curved Full Edge Screen Protector', 'SCR-IP16P-9D', 85, 140, 115, 150],
                    ['iPhone 15 Pro Max Anti-Static Matte Gaming Glass Protector', 'SCR-IP15PM-MAT', 110, 180, 150, 130],
                    ['iPhone 15 Pro Individual Titanium Camera Lens Protectors', 'SCR-IP15P-CAM', 95, 160, 130, 140],
                    ['iPhone 15 9D Full Glue High Clarity Tempered Glass', 'SCR-IP15-9D', 70, 120, 100, 200],
                    ['iPhone 14 Pro Max Privacy Matte Ceramic Flexible Film', 'SCR-IP14PM-CER', 65, 110, 90, 180],
                    ['iPhone 13 / 13 Pro 9D Tempered Glass Screen Protector', 'SCR-IP13-9D', 55, 95, 80, 220],
                    ['iPhone 11 9D Full Cover Tempered Glass 2.5D Edge', 'SCR-IP11-9D', 45, 80, 65, 250],
                    ['Samsung S24 Ultra UV Liquid Curved Full Adhesive Glass', 'SCR-S24U-UV', 180, 290, 240, 80],
                    ['Samsung S24 Ultra Full Cover Tempered Glass + Camera Glass', 'SCR-S24U-COMBO', 140, 230, 190, 90],
                    ['Samsung S23 Ultra UV Glue Curved Tempered Glass Protector', 'SCR-S23U-UV', 160, 260, 215, 75],
                    ['Samsung A55 5G 9D Full Coverage Tempered Glass', 'SCR-A55-9D', 50, 90, 75, 200],
                    ['Samsung A35 5G High Definition Clear Screen Protector', 'SCR-A35-9D', 45, 80, 68, 200],
                    ['Samsung A15 Privacy Anti-Peep Tempered Glass Screen', 'SCR-A15-PRV', 65, 110, 90, 180],
                    ['Redmi Note 13 Pro 9D Tempered Glass with Installation Tray', 'SCR-RN13P-TRY', 60, 105, 85, 190],
                ]
            ],
            [
                'name' => 'حوامل وإكسسوارات سيارات (Car Holders & Extras)',
                'icon' => '🚗',
                'sort_order' => 12,
                'items' => [
                    ['Joyroom JR-ZS240 15W MagSafe Wireless Car Charger Mount', 'CAR-JOY-MAG15W', 680, 890, 790, 40],
                    ['Joyroom JR-ZS288 Electric Auto-Clamping Car Air Vent Holder', 'CAR-JOY-AUTCLP', 420, 560, 490, 55],
                    ['Joyroom JR-ZS246 Dual Coil 15W Auto Clamping Dashboard Mount', 'CAR-JOY-DSH15W', 750, 980, 870, 30],
                    ['Anker 323 52.5W High Speed 2-Port USB-C Car Charger', 'CAR-ANK-52W', 480, 640, 570, 45],
                    ['Anker PowerDrive 2 Alloy 24W Metal Dual USB Car Charger', 'CAR-ANK-24W', 260, 360, 310, 70],
                    ['Baseus 65W Digital Display QC+PD Dual Port Car Charger', 'CAR-BAS-65W', 540, 720, 630, 40],
                    ['Baseus MagPro Series Magnetic Car Phone Holder Dashboard', 'CAR-BAS-MAGPRO', 310, 430, 380, 60],
                    ['Oraimo Highway 22.5W Fast Charging Car Charger with Type-C', 'CAR-ORA-22W', 210, 295, 260, 80],
                    ['Universal 360 Rotation Metal Desktop Phone & Tablet Stand', 'ACC-STD-DESK', 120, 190, 160, 120],
                    ['Foldable Aluminum Pocket Smartphone Desk Cradle', 'ACC-STD-FOLD', 75, 125, 105, 150],
                    ['Selfie Stick Tripod with Bluetooth Remote Shutter 1.3m', 'ACC-TRP-BLT', 190, 290, 245, 60],
                    ['Kingston DataTraveler MicroDuo 3C 64GB Dual USB Type-A/C', 'MEM-KNG-64GC', 280, 380, 335, 70],
                    ['SanDisk Ultra Dual Drive Luxe 128GB All-Metal Type-C OTG', 'MEM-SND-128GC', 440, 580, 515, 60],
                    ['MicroSDXC SanDisk Extreme 128GB 190MB/s 4K Ultra HD A2', 'MEM-SND-128SD', 380, 510, 450, 50],
                    ['Joyroom Stylus Touch Pen for iPad & Capacitive Touchscreens', 'ACC-JOY-STYL', 290, 410, 360, 45],
                ]
            ],
        ];

        $totalItemsCount = 0;

        foreach ($categoriesData as $catData) {
            $category = Category::updateOrCreate(
                ['name' => $catData['name']],
                [
                    'icon' => $catData['icon'],
                    'sort_order' => $catData['sort_order'],
                    'is_active' => true,
                ]
            );

            foreach ($catData['items'] as $itemArr) {
                [$name, $code, $costPrice, $sellingPrice, $minSellingPrice, $qty] = $itemArr;

                $item = Item::updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'category' => $category->name,
                        'category_id' => $category->id,
                        'unit' => 'قطعة',
                        'cost_price' => $costPrice,
                        'selling_price' => $sellingPrice,
                        'min_selling_price' => $minSellingPrice,
                        'min_stock_level' => 5,
                        'current_stock' => $qty,
                        'is_active' => true,
                    ]
                );

                if ($mainStore) {
                    StoreStock::updateOrCreate(
                        [
                            'store_id' => $mainStore->id,
                            'item_id' => $item->id,
                        ],
                        [
                            'quantity' => $qty,
                        ]
                    );
                }

                $totalItemsCount++;
            }
        }

        echo "Seeded {$totalItemsCount} phone & accessory items across " . count($categoriesData) . " categories successfully!\n";
    }
}