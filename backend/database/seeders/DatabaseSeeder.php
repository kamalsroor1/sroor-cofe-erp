<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Store;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Setup Roles & Permissions Matrix & SaaS Plans
        $this->call(PermissionsSeeder::class);
        $this->call(PlansAndFeaturesSeeder::class);
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        // 2. Super Admin 1: كمال سرور (01012316954 / password)
        $admin1 = User::firstOrCreate(
            ['phone' => '01012316954'],
            [
                'name'      => 'كمال سرور - المدير العام',
                'email'     => '01012316954@sroor.com',
                'password'  => bcrypt('password'),
                'is_active' => true,
            ]
        );
        $admin1->syncRoles([$superAdminRole, $adminRole]);

        // 3. Super Admin 2: المدير العام 2 (01558088841 / 123456789)
        $admin2 = User::firstOrCreate(
            ['phone' => '01558088841'],
            [
                'name'      => 'المدير العام 2',
                'email'     => '01558088841@sroor.com',
                'password'  => bcrypt('123456789'),
                'is_active' => true,
            ]
        );
        $admin2->syncRoles([$superAdminRole, $adminRole]);

        // 4. Base Main Warehouse (المخزن الرئيسي الأساسي)
        Store::firstOrCreate(
            ['code' => 'MAIN-01'],
            [
                'name'      => 'المخزن والفرع الرئيسي',
                'type'      => 'main_warehouse',
                'is_main'   => true,
                'is_active' => true,
            ]
        );

        // 5. Rich Multi-Store Demo Data (Branches, Coffee Items, Shifts, Customers, Suppliers, Ledgers)
        $this->call(RichDemoDataSeeder::class);
    }
}
