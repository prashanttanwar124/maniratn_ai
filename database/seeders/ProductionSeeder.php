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
        // 0. Seed Spatie Permissions & Roles
        $permissions = [
            'manage_api_keys',
            'view_api_keys',
            'access_ai_playground',
            'manage_users',
            'manage_roles',
            'access_profile',
        ];

        foreach ($permissions as $perm) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $superAdminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdminRole->syncPermissions($permissions);

        $operatorRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'AI Operator', 'guard_name' => 'web']);
        $operatorRole->syncPermissions(['view_api_keys', 'access_ai_playground', 'access_profile']);

        $viewerRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Viewer', 'guard_name' => 'web']);
        $viewerRole->syncPermissions(['access_ai_playground', 'access_profile']);

        $this->command->info("✓ Roles & Permissions Seeded successfully.");

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

        if (! $admin->hasRole('Super Admin')) {
            $admin->assignRole('Super Admin');
        }

        $this->command->info("✓ Master Admin Seeded with Super Admin Role: {$admin->email}");

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
