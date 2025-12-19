# Seeders Quick Reference

## Available Seeders

### 1. RolesSeeder
**File:** `database/seeders/RolesSeeder.php`

**Purpose:** Seeds roles and permissions

**Roles Created:**
- super-admin (all permissions)
- admin (users.*, roles.*, permissions.*)
- user (basic permissions)
- manager (from config)

**Permissions Created:**
- roles.* (list, create, show, update, delete, restore, force-delete)
- users.* (list, create, show, update, delete, restore, force-delete)
- permissions.* (list, show)

**Run:**
```bash
php artisan db:seed --class="\Enadstack\LaravelRoles\Database\Seeders\RolesSeeder"
```

---

### 2. SuperAdminSeeder
**File:** `database/seeders/SuperAdminSeeder.php`

**Purpose:** Creates super admin user with super-admin role

**Configuration (config/roles.php):**
```php
'super_admin' => [
    'email' => env('SUPER_ADMIN_EMAIL', 'superadmin@example.com'),
    'password' => env('SUPER_ADMIN_PASSWORD', 'password'),
    'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
]
```

**Environment Variables (.env):**
```env
SUPER_ADMIN_EMAIL=superadmin@yourdomain.com
SUPER_ADMIN_PASSWORD=your-secure-password
SUPER_ADMIN_NAME="Super Administrator"
```

**Run:**
```bash
php artisan db:seed --class="\Enadstack\LaravelRoles\Database\Seeders\SuperAdminSeeder"
```

**Creates:**
- User with configured email
- Assigns super-admin role
- Grants all permissions (*)

---

### 3. AdminSeeder
**File:** `database/seeders/AdminSeeder.php`

**Purpose:** Creates admin user with admin role

**Configuration (config/roles.php):**
```php
'admin' => [
    'email' => env('ADMIN_EMAIL', 'admin@example.com'),
    'password' => env('ADMIN_PASSWORD', 'password'),
    'name' => env('ADMIN_NAME', 'Admin'),
]
```

**Environment Variables (.env):**
```env
ADMIN_EMAIL=admin@yourdomain.com
ADMIN_PASSWORD=your-secure-password
ADMIN_NAME="Administrator"
```

**Run:**
```bash
php artisan db:seed --class="\Enadstack\LaravelRoles\Database\Seeders\AdminSeeder"
```

**Creates:**
- User with configured email
- Assigns admin role
- Grants admin permissions (users.*, roles.*, permissions.*)

---

## Run All Seeders

### Option 1: Individual Commands
```bash
php artisan db:seed --class="\Enadstack\LaravelRoles\Database\Seeders\RolesSeeder"
php artisan db:seed --class="\Enadstack\LaravelRoles\Database\Seeders\SuperAdminSeeder"
php artisan db:seed --class="\Enadstack\LaravelRoles\Database\Seeders\AdminSeeder"
```

### Option 2: Create DatabaseSeeder
Add to your `database/seeders/DatabaseSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            \Enadstack\LaravelRoles\Database\Seeders\RolesSeeder::class,
            \Enadstack\LaravelRoles\Database\Seeders\SuperAdminSeeder::class,
            \Enadstack\LaravelRoles\Database\Seeders\AdminSeeder::class,
        ]);
    }
}
```

Then run:
```bash
php artisan db:seed
```

---

## Seeder Configuration in Config

**File:** `config/roles.php`

```php
'seed' => [
    // Seeder classes to run (in order)
    'seeders' => [
        \Enadstack\LaravelRoles\Database\Seeders\RolesSeeder::class,
        \Enadstack\LaravelRoles\Database\Seeders\SuperAdminSeeder::class,
        \Enadstack\LaravelRoles\Database\Seeders\AdminSeeder::class,
    ],
    
    // Super Admin User Configuration
    'super_admin' => [
        'email' => env('SUPER_ADMIN_EMAIL', 'superadmin@example.com'),
        'password' => env('SUPER_ADMIN_PASSWORD', 'password'),
        'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
    ],
    
    // Admin User Configuration
    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_PASSWORD', 'password'),
        'name' => env('ADMIN_NAME', 'Admin'),
    ],
    
    // Additional configuration...
]
```

---

## Seeder Features

### ✅ Safe Execution
- Checks if user already exists
- Won't create duplicate users
- Checks for column existence before accessing

### ✅ Flexible Configuration
- Environment-based credentials
- Configurable user details
- Customizable roles and permissions

### ✅ Smart Detection
- Auto-detects User model
- Works with different User model locations:
  - `config('auth.providers.users.model')`
  - `App\Models\User`
  - `App\User`

### ✅ Informative Output
```bash
Super Admin user created: superadmin@example.com
Super Admin role assigned.

# Or if already exists:
Super Admin user already exists: superadmin@example.com
Super Admin already has super-admin role.
```

---

## Complete Setup Example

### 1. Configure Environment
```env
# .env file
SUPER_ADMIN_EMAIL=superadmin@myapp.com
SUPER_ADMIN_PASSWORD=SecurePass123!
SUPER_ADMIN_NAME="Super Administrator"

ADMIN_EMAIL=admin@myapp.com
ADMIN_PASSWORD=SecurePass456!
ADMIN_NAME="System Administrator"
```

### 2. Run Migrations
```bash
php artisan migrate
```

### 3. Run Seeders
```bash
php artisan db:seed --class="\Enadstack\LaravelRoles\Database\Seeders\RolesSeeder"
php artisan db:seed --class="\Enadstack\LaravelRoles\Database\Seeders\SuperAdminSeeder"
php artisan db:seed --class="\Enadstack\LaravelRoles\Database\Seeders\AdminSeeder"
```

### 4. Login
- **Super Admin:** superadmin@myapp.com / SecurePass123!
- **Admin:** admin@myapp.com / SecurePass456!

---

## Testing Seeders

### Check Users Created
```php
use App\Models\User;

$superAdmin = User::where('email', 'superadmin@example.com')->first();
$admin = User::where('email', 'admin@example.com')->first();

// Check roles
$superAdmin->hasRole('super-admin'); // true
$admin->hasRole('admin'); // true

// Check permissions
$superAdmin->can('users.create'); // true (has all permissions)
$admin->can('users.create'); // true (has users.*)
```

---

## Troubleshooting

### Issue: "User model not found"
**Solution:** Make sure you have a User model defined in your Laravel app. The seeder checks:
- `config('auth.providers.users.model')`
- `App\Models\User`
- `App\User`

### Issue: "User already exists"
**Solution:** This is normal. The seeder checks for existing users and won't create duplicates.

### Issue: "Column not found"
**Solution:** The seeder safely checks for column existence. If your users table doesn't have certain columns (like `email_verified_at`), they'll be skipped.

---

## Best Practices

1. **Change Default Passwords:** Always use strong, unique passwords in production
2. **Use Environment Variables:** Never commit credentials to version control
3. **Run Once:** Seeders are idempotent but should only be run during setup
4. **Verify Permissions:** Test that roles have correct permissions after seeding
5. **Backup Before Re-seeding:** If re-running, backup your database first

---

## Summary

| Seeder | Creates | Role | Permissions |
|--------|---------|------|-------------|
| RolesSeeder | Roles & Permissions | - | - |
| SuperAdminSeeder | 1 User | super-admin | * (all) |
| AdminSeeder | 1 User | admin | users.*, roles.*, permissions.* |

**Total:** 2 admin users ready to use! 🎉

