<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Super Admin
        Admin::updateOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'admin@bulkcampaign.com')],
            [
                'name' => 'Super Admin',
                'email' => env('SUPER_ADMIN_EMAIL', 'admin@bulkcampaign.com'),
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'admin123456')),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        // Seed assemblies
        $this->call(AssemblySeeder::class);
    }
}
