<?php

namespace Enadstack\LaravelRoles\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates a super admin user with super-admin role and all permissions.
     */
    public function run(): void
    {
        $guard = config('roles.guard', 'web');

        // Check if User model exists
        $userModel = $this->getUserModel();
        if (!$userModel) {
            $this->command->warn('User model not found. Skipping SuperAdminSeeder.');
            return;
        }

        // Get or create super-admin role
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super-admin', 'guard_name' => $guard],
            ['name' => 'super-admin', 'guard_name' => $guard]
        );

        // Get super admin configuration
        $email = config('roles.seed.super_admin.email', 'superadmin@example.com');
        $password = config('roles.seed.super_admin.password', 'password');
        $name = config('roles.seed.super_admin.name', 'Super Admin');

        // Check if super admin user already exists
        $superAdmin = $userModel::where('email', $email)->first();

        if ($superAdmin) {
            $this->command->info("Super Admin user already exists: {$email}");
        } else {
            // Create super admin user
            $userData = [
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ];

            // Add email_verified_at if column exists
            if ($this->columnExists('users', 'email_verified_at')) {
                $userData['email_verified_at'] = now();
            }

            $superAdmin = $userModel::create($userData);

            $this->command->info("Super Admin user created: {$email}");
        }

        // Assign super-admin role
        if (!$superAdmin->hasRole('super-admin')) {
            $superAdmin->assignRole($superAdminRole);
            $this->command->info('Super Admin role assigned.');
        } else {
            $this->command->info('Super Admin already has super-admin role.');
        }

        // Give all permissions (super-admin should have *)
        $superAdmin->syncPermissions($superAdminRole->permissions);
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

