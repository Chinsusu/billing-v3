<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'admin@billing.test'],
            ['name' => 'Billing Admin', 'password' => Hash::make('Password123!')]
        );
        $admin->assignRole('super_admin');

        $customer = User::firstOrCreate(
            ['email' => 'customer@billing.test'],
            ['name' => 'Billing Customer', 'password' => Hash::make('Password123!')]
        );
        $customer->assignRole('customer');
        Wallet::firstOrCreate(
            ['user_id' => $customer->id, 'currency' => 'VND'],
            ['balance_amount' => 0]
        );
        Invoice::firstOrCreate(
            ['invoice_number' => 'INV-20260524-0001'],
            [
                'user_id' => $customer->id,
                'status' => 'open',
                'total_amount' => 99000,
                'currency' => 'VND',
                'description' => 'Seed invoice for Sprint 2 wallet payment validation.',
                'lines' => [
                    ['description' => 'Vietnam Proxy 30 Days', 'amount' => 99000],
                ],
            ]
        );

        Product::firstOrCreate(
            ['code' => 'proxy-vn-30d'],
            [
                'name' => 'Vietnam Proxy 30 Days',
                'type' => 'proxy',
                'status' => 'active',
                'price_amount' => 99000,
                'currency' => 'VND',
                'duration_days' => 30,
                'description' => 'Starter proxy plan for Sprint 1 catalog validation.',
                'config' => [],
            ]
        );

        Product::firstOrCreate(
            ['code' => 'vps-basic-30d'],
            [
                'name' => 'Basic VPS 30 Days',
                'type' => 'vps',
                'status' => 'active',
                'price_amount' => 199000,
                'currency' => 'VND',
                'duration_days' => 30,
                'description' => 'Starter VPS plan for Sprint 1 catalog validation.',
                'config' => [],
            ]
        );
    }
}
