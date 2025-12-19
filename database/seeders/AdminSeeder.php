<?php

namespace Enadstack\LaravelRoles\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates an admin user with admin role and specific permissions.
     */
    public function run(): void
    {
        $guard = config('roles.guard', 'web');

        // Check if User model exists
        $userModel = $this->getUserModel();
        if (!$userModel) {
            $this->command->warn('User model not found. Skipping AdminSeeder.');
            return;
        }

        // Get or create admin role
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => $guard],
            ['name' => 'admin', 'guard_name' => $guard]
        );

        // Get admin configuration
        $email = config('roles.seed.admin.email', 'admin@example.com');
        $password = config('roles.seed.admin.password', 'password');
        $name = config('roles.seed.admin.name', 'Admin');

        // Check if admin user already exists
        $admin = $userModel::where('email', $email)->first();

        if ($admin) {
            $this->command->info("Admin user already exists: {$email}");
        } else {
            // Create admin user
            $userData = [
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ];

            // Add email_verified_at if column exists
            if ($this->columnExists('users', 'email_verified_at')) {
                $userData['email_verified_at'] = now();
            }

            $admin = $userModel::create($userData);

            $this->command->info("Admin user created: {$email}");
        }

        // Assign admin role
        if (!$admin->hasRole('admin')) {
            $admin->assignRole($adminRole);
            $this->command->info('Admin role assigned.');
        } else {
            $this->command->info('Admin already has admin role.');
        }

        // Sync permissions from role
        $admin->syncPermissions($adminRole->permissions);
    }

    /**
     * Get the User model class
     */
    protected function getUserModel(): ?string
    {
        // Try to get User model from various locations
        $possibleModels = [
            config('auth.providers.users.model'),
            'App\\Models\\User',
            'App\\User',
        ];

        foreach ($possibleModels as $model) {
            if ($model && class_exists($model)) {
                return $model;
            }
        }

        return null;
    }

    /**
     * Check if a column exists in a table
     */
    protected function columnExists(string $table, string $column): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }
}

