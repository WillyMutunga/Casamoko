<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Models\ClientAccount;
use App\Modules\Accounts\Models\ResellerAccount;
use App\Modules\Messaging\Models\Carrier;
use App\Modules\Messaging\Models\Route;
use App\Modules\Messaging\Models\PricingRule;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // 1. Seed Supervisor Admin User
        User::updateOrCreate(
            ['email' => 'wmutunga003@gmail.com'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('William#20'),
                'role_tier' => 'SUPER_ADMIN',
                'password_changed_at' => now(),
                'email_verified_at' => now(),
                'is_active_user' => true,
            ]
        );

        // 2. Seed Reseller Account
        $reseller = ResellerAccount::updateOrCreate(
            ['name' => 'Casamoko Reseller North'],
            [
                'markup_percentage' => 10.00,
                'api_credits' => 5000.0000,
            ]
        );

        // 3. Seed Reseller User
        User::updateOrCreate(
            ['email' => 'reseller@casamoko.com'],
            [
                'name' => 'Casamoko Reseller Partner',
                'password' => Hash::make('Reseller123'),
                'role_tier' => 'RESELLER',
                'reseller_account_id' => $reseller->id,
                'password_changed_at' => now(),
                'email_verified_at' => now(),
                'is_active_user' => true,
            ]
        );

        // 4. Seed Client Account under Reseller
        $client = ClientAccount::updateOrCreate(
            ['name' => 'Acme Corporation Kenya'],
            [
                'reseller_account_id' => $reseller->id,
                'status' => 'APPROVED',
                'wallet_balance' => 147.2830,
                'credit_limit' => 50.0000,
            ]
        );

        // 5. Seed Client User
        User::updateOrCreate(
            ['email' => 'jane@acme.com'],
            [
                'client_account_id' => $client->id,
                'name' => 'Jane Doe Client',
                'password' => Hash::make('Secret123'),
                'role_tier' => 'CLIENT',
                'sub_role' => 'CLIENT_ADMIN',
                'password_changed_at' => now(),
                'email_verified_at' => now(),
                'is_active_user' => true,
            ]
        );

        // 6. Seed Default Carrier & Route for Safaricom SDP Integration
        $carrier = Carrier::updateOrCreate(['name' => 'Safaricom MNO']);
        Route::updateOrCreate(
            ['name' => 'Safaricom Primary Route'],
            [
                'carrier_id' => $carrier->id,
                'cost_per_sms' => 0.0030,
                'tps_limit' => 100,
                'priority' => 1,
                'is_active' => true,
                'destination_network' => 'SAFARICOM',
                'bind_type' => 'TRANSCEIVER',
                'window_size' => 10,
                'use_tls' => true,
                'delivery_rate_score' => 99.8,
                'latency_score' => 45,
            ]
        );

        // 7. Seed Pricing Rule
        PricingRule::updateOrCreate(
            ['destination_prefix' => '2547'],
            [
                'name' => 'Safaricom Local Network',
                'base_cost' => 0.0200,
                'reseller_markup' => 0.0050,
            ]
        );
    }
}
