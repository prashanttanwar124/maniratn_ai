<?php

namespace Database\Seeders;

use App\Models\AiApiKey;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductionSeeder extends Seeder
{
    /**
     * Run the production database seeds for Maniratn AI Central Hub.
     */
    public function run(): void
    {
        // 1. Create / Update Master Admin User
        $adminEmail = env('ADMIN_DEFAULT_EMAIL', 'admin@maniratn.ai');
        $adminPassword = env('ADMIN_DEFAULT_PASSWORD', 'admin123');

        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => env('ADMIN_DEFAULT_NAME', 'Master Administrator'),
                'password' => Hash::make($adminPassword),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info("✓ Master Admin Seeded: {$admin->email}");

        // 2. Seed Default Live Production Showroom Key
        $mainStoreKey = env('INITIAL_STORE_KEY', 'mn_live_d8f4e2a1c90b6732e45a89f0');

        $apiKey = AiApiKey::firstOrCreate(
            ['key' => $mainStoreKey],
            [
                'business_name' => 'Maniratn Jewellers (Main Showroom)',
                'contact_email' => 'contact@maniratnjewellers.com',
                'contact_phone' => '+91 98765 43210',
                'type' => 'live',
                'plan' => 'enterprise',
                'is_active' => true,
                'voice_enabled' => true,
                'query_count' => 0,
                'last_used_at' => now(),
            ]
        );

        $this->command->info("✓ Production Store Token Seeded: {$apiKey->business_name} ({$apiKey->key})");
    }
}
