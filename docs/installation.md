# Installation

This guide covers installing and configuring the Laravel Roles package.

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.2+ |
| Laravel | 12.0+ |
| spatie/laravel-permission | 6.0+ |

## Step 1: Install via Composer

```bash
composer require enadstack/laravel-roles
```

The package will auto-register its service provider.

## Step 2: Run Install Command

```bash
php artisan roles:install
```

This command:
- Publishes the config file to `config/roles.php`
- Publishes Spatie permission migrations (if not already present)
- Publishes package migrations for extended fields
- Runs all migrations

## Step 3: Configure the Package

Edit `config/roles.php` to match your application:

```php
return [
    'guard' => 'web',
    'tenancy' => [
        'mode' => 'single', // or 'team_scoped', 'multi_database'
    ],
    'i18n' => [
        'enabled' => false,
    ],
];
```

See [Configuration](configuration.md) for all options.

## Step 4: Sync Permissions

Define your permissions in the config and run:

```bash
php artisan roles:sync
```

This creates roles and permissions defined in the config.

## Step 5: Verify Installation

Run the doctor command to verify everything is configured correctly:

```bash
php artisan roles:doctor
```

Expected output:

```
Laravel Roles Doctor - v1.3.0
=============================
[OK] Configuration file exists
[OK] Database tables exist
[OK] Spatie permission tables exist
[OK] Guard 'web' is configured
[OK] Tenancy mode: single
[OK] Cache driver: array
=============================
All checks passed.
```

## Verification Checklist

After installation, verify:

- [ ] Config file exists at `config/roles.php`
- [ ] Migrations have run (check `roles` and `permissions` tables)
- [ ] `roles:doctor` shows no errors
- [ ] At least one role exists if using seeded config
- [ ] API routes are accessible at configured prefix

## Optional: Enable UI

If you want the Vue-based admin UI:

```php
// config/roles.php
'ui' => [
    'enabled' => true,
    'driver' => 'vue',
],
```

Then publish the Vue components:

```bash
php artisan vendor:publish --tag=laravel-roles-vue
```

See [Vue UI](ui-vue.md) for full setup instructions.

## Next Steps

- [Configuration Reference](configuration.md)
- [Setting Up Tenancy](tenancy.md)
- [Defining Permissions](permissions.md)
