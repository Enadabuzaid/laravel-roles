# enadstack/laravel-roles (v2)

Access control for Laravel: a file-based permission registry, bilingual (AR/EN) role and permission labels, a safe `HasAccess` API on top of [spatie/laravel-permission](https://github.com/spatie/laravel-permission), a consistent API envelope and a `roles:doctor` health check.

> v2 is a rewrite. v1 (Vue UI, `/admin/acl` routes, services) lives on the `1.x` branch.

## Requirements

PHP ^8.3, Laravel ^13, spatie/laravel-permission ^8.

## Install

```bash
composer require enadstack/laravel-roles
php artisan roles:install            # publishes config/roles.php, access/permissions.php, Spatie config + migration
php artisan migrate
php artisan roles:sync               # registry file -> permissions table
php artisan roles:seed               # default roles (owner, admin, manager, viewer)
php artisan roles:owner you@example.com
```

`roles:install` accepts `--teams=1|0`, `--id-type=int|uuid|ulid` and `--force`. The options are only applied when `config/roles.php` is created (or with `--force`); otherwise a warning is printed.

### Your user model

The configured user model (`roles.user_model`) must BOTH `use HasAccess` AND `implements \Enadstack\LaravelRoles\Contracts\AccessUser`. Without both, owner detection is skipped. `php artisan roles:doctor` flags this (`UserModelCheck`).

```php
use Enadstack\LaravelRoles\Concerns\HasAccess;
use Enadstack\LaravelRoles\Contracts\AccessUser;

class User extends Authenticatable implements AccessUser
{
    use HasAccess;
}
```

## Configuration (`config/roles.php`)

| Key | Default | Purpose |
|---|---|---|
| `user_model` | `App\Models\User` (`ROLES_USER_MODEL`) | User model; needs `HasAccess` + `AccessUser` |
| `guard` | `web` (`ROLES_GUARD`) | Guard for roles and permissions |
| `teams` | `false` | Multi-tenant roles |
| `id_type` | `int` | `int`, `uuid` or `ulid` |
| `super_role` | `owner` | Role that bypasses permission checks |
| `default_locale` / `fallback_locale` / `locales` | `en` / `en` / `['en','ar']` | Label translations |
| `modules` | all `true` | Feature toggles for later phases |
| `routes.prefix` / `routes.middleware` | `api/access` / `['api','auth:sanctum']` | API routes (later phases) |
| `permissions_file` | `access/permissions.php` | Permission registry |
| `guardrails` | see file | Min owners, self-escalation, high-risk confirmation |
| `security` | see file | Lockout, MFA roles, login history |
| `pagination` | `default 15`, `max 100` | List defaults |
| `default_roles` | owner, admin, manager, viewer | Seeded by `roles:seed` |
| `doctor.checks` | 7 built-in checks | Classes implementing `Core\Doctor\Check` |

## Permission registry

`access/permissions.php` returns groups of permissions. A label can be given in three forms:

```php
return [
    'orders' => [
        'label' => ['en' => 'Orders', 'ar' => 'الطلبات'],
        'permissions' => [
            // 1. plain string
            'orders.export' => 'Export orders',
            // 2. bare locale map
            'orders.view' => ['en' => 'View orders', 'ar' => 'عرض الطلبات'],
            // 3. full form: label (+ description, risk: low|medium|high)
            'orders.refund' => [
                'label' => ['en' => 'Refund orders', 'ar' => 'استرداد الطلبات'],
                'description' => ['en' => 'Refund a paid order.', 'ar' => 'استرداد طلب مدفوع.'],
                'risk' => 'high',
            ],
        ],
    ],
];
```

`roles:sync` creates/updates permissions, marks removed ones deprecated, and never deletes a permission someone still holds (`--prune` deletes unheld deprecated ones, `--dry-run` shows the diff).

## HasAccess

Use the package API for role changes so guardrails, cache resets and owner rules apply:

```php
$user->assignAccessRole('admin');
$user->removeAccessRole('admin');
$user->syncAccessRoles(['manager', 'viewer']);
$user->isOwner();
```

Never call Spatie's `assignRole`, `removeRole`, `syncRoles`, `givePermissionTo`, `revokePermissionTo` or `syncPermissions` directly; an architecture test enforces this inside the package.

## Commands

| Command | Purpose |
|---|---|
| `roles:install` | Publish config, registry stub, Spatie assets |
| `roles:sync [--dry-run] [--prune]` | Registry file to database |
| `roles:seed` | Create default roles (idempotent) |
| `roles:owner {email}` | Make a user the owner |
| `roles:doctor` | Run health checks; exits non-zero on failure. A check that throws is reported as `FAIL`. |

## API envelope and list queries

```json
{ "success": true, "message": null, "data": [], "meta": { "pagination": { "current_page": 1, "per_page": 15, "total": 0, "last_page": 1 } } }
{ "success": false, "message": "...", "code": "validation_failed", "errors": {} }
```

List endpoints (`Support\ListQuery`) accept `search`, `filter[key]=value` (whitelisted keys), `sort=name` / `sort=-name` (whitelisted), `page`, `per_page` (clamped to `pagination.max`) and `include=a,b` (whitelisted). Anything not whitelisted is ignored.

## Testing

```bash
vendor/bin/pest                       # sqlite in-memory
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_USERNAME=postgres DB_PASSWORD= DB_DATABASE=roles_testing vendor/bin/pest
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint
```

## License

MIT
