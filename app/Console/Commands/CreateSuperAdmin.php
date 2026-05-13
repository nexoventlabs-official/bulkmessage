<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create {--email=} {--password=} {--name=Super Admin}';
    protected $description = 'Create a super admin account';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Enter admin email');
        $password = $this->option('password') ?: $this->secret('Enter admin password');
        $name = $this->option('name');

        $admin = Admin::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        $this->info("Super admin created/updated: {$admin->email}");
        return 0;
    }
}
