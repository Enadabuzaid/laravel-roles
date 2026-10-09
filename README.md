# enadstack/laravel-roles (v2)

Access control for Laravel: a file-based permission registry, bilingual (AR/EN) role and permission labels, a safe `HasAccess` API on top of [spatie/laravel-permission](https://github.com/spatie/laravel-permission), a consistent API envelope and a `roles:doctor` health check.

> v2 is a rewrite. v1 (Vue UI, `/admin/acl` routes, services) lives on the `1.x` branch.

## Requirements

PHP ^8.3, Laravel ^13, spatie/laravel-permission ^8, and the audit store:

```bash
composer require enadstack/laravel-action-logger   # ^1.1
```

## Install

```bash
composer require enadstack/laravel-roles
php artisan roles:install            # publishes config/roles.php, access/permissions.php, Spatie config + migrations, then the extension migrations
php artisan migrate
php artisan roles:sync               # registry file -> permissions table
php artisan roles:seed               # default roles (owner, admin, manager, viewer)
php artisan roles:owner you@example.com
```

`roles:install` accepts `--force`. It also accepts `--teams=0` and `--id-type=int`, but any other value is rejected with a non-zero exit: teams, `uuid` and `ulid` keys are not supported in 2.0-alpha and arrive in a later phase. `roles:doctor` fails if `roles.teams` / `permission.teams` is enabled or `roles.id_type` is not `int`.

### Migrations are published, not auto-loaded

The package ships two migration stubs (`extend_roles_table`, `extend_permissions_table`) that add columns to Spatie's `roles` and `permissions` tables. They are published with install-time timestamps (`php artisan vendor:publish --tag=roles-migrations`, done for you by `roles:install`) instead of being auto-loaded, so they always sort after Spatie's `create_permission_tables` migration regardless of when that was published. Republishing never creates duplicates. If the Spatie tables are missing, the migration fails with a clear message rather than a raw SQL error.

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
| `teams` | `false` | Multi-tenant roles (unsupported in 2.0-alpha; must stay `false`) |
| `id_type` | `int` | Must stay `int` in 2.0-alpha (`uuid` / `ulid` planned for a later phase) |
| `super_role` | `owner` | Role that bypasses permission checks |
| `default_locale` / `fallback_locale` / `locales` | `en` / `en` / `['en','ar']` | Label translations |
| `modules` | all `true` | Feature toggles for later phases |
| `routes.prefix` / `routes.middleware` | `api/access` / `['api','auth:sanctum']` | API routes (later phases) |
| `permissions_file` | `access/permissions.php` | Permission registry |
| `guardrails` | see file | Reserved: enforced from Phase 2, not yet active |
| `security` | see file | Lockout, MFA roles, login history |
| `pagination` | `default 15`, `max 100` | List defaults |
| `default_roles` | owner, admin, manager, viewer | Seeded by `roles:seed` |
| `doctor.checks` | 9 built-in checks (incl. `audit-ready`) | Classes implementing `Core\Doctor\Check` |

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

Use the package API for role changes so cache resets, guardrails, audit logging and the owner gate apply. `assignAccessRole` and `syncAccessRoles` take a trailing `bool $confirmed` (see Guardrails):

```php
$user->assignAccessRole('admin');
$user->removeAccessRole('admin');
$user->syncAccessRoles(['manager', 'viewer']);
$user->isOwner();
```

Never call Spatie's `assignRole`, `removeRole`, `syncRoles`, `givePermissionTo`, `revokePermissionTo` or `syncPermissions` directly; an architecture test enforces this inside the package.

## Guardrails

Every role change goes through `Guardrails::assert()` inside the Action's transaction. A violation throws `AccessException` (HTTP 403, rendered in the API envelope), changes nothing and writes no audit entry. Rules are listed in `roles.guardrails.rules`; each can be toggled in `roles.guardrails`. Guardrail rules and doctor checks merge additively: the package defaults are always applied first, then any extras your published config lists, so a config that omits `rules` (or lists only custom rules) still enforces all seven built-ins. To switch a rule off, list its class in `roles.guardrails.disabled` (doctor checks use `roles.doctor.disabled`). Disabling a built-in guardrail is a security decision; do it deliberately.

| Code | Meaning | Applies to system actor |
| --- | --- | --- |
| `guardrail.last_owner` | Fewer than `min_owners` owners would remain | yes |
| `guardrail.self_role_change` | You changed your own role | no |
| `guardrail.self_destructive` | You suspended, deactivated or deleted yourself | no |
| `guardrail.escalation` | Granting a role with permissions you lack, or adding/removing the owner role as a non-owner | no |
| `guardrail.high_risk_confirm_required` | Adding a high-risk or owner role without `confirmed: true` | yes |
| `guardrail.system_role` | Deleting or renaming a system role (or the super role) | yes |
| `guardrail.confirmation_mismatch` | Type-to-confirm text missing or wrong (an empty expected value, such as a user without an email, always mismatches) | yes |

**Actor context.** The actor is `ActorContext::actor()`: the authenticated user on the `roles.guard`, or `null` (system/console). Force it with `runAs`:

```php
app(ActorContext::class)->runAs($admin, fn () => $user->assignAccessRole('manager'));
app(ActorContext::class)->runAs(null, fn () => $user->assignAccessRole('viewer')); // system, even if someone is logged in
```

`roles:seed`, `roles:owner` and `roles:sync` always run as the system actor. State-based rules (last owner, high-risk confirmation, system role, destructive confirmation) still apply to it, so console code that grants the owner role must pass the flag: `$user->assignAccessRole('owner', confirmed: true)`.

**Confirmation flags.** `confirmed: true` acknowledges a high-risk or owner role. Re-assigning a role the user already holds is a silent no-op (no exception, no audit entry).

**Concurrency.** Assign/remove/sync lock the super-role row (`SELECT ... FOR UPDATE`) before the guardrails run, so two concurrent owner removals serialize and the last-owner rule cannot be raced.

## Audit

Writes go to `enadstack/laravel-action-logger` (^1.1), module `roles` or `permissions`:

| Event | Subject | When |
| --- | --- | --- |
| `roles.assigned` / `roles.removed` / `roles.synced` | the user | only if the role set changed; `old_values`/`new_values` hold `roles`, `meta` holds `role` or `added`/`removed` |
| `roles.seeded` | none | roles were created or restored |
| `permissions.synced` | none | a non-dry-run sync changed something; counts per change type plus names (capped at 200 per list) |

Severity: `critical` when the owner role is added or removed, `warning` when a high-risk role is added (or a sync deprecated/pruned permissions), otherwise `info`.

**After-commit semantics.** Entries are written after the surrounding transaction commits: a rollback (including an outer transaction) writes nothing. A failing logger is reported via `report()` and never breaks the role change. Disable with `roles.audit.enabled` or `roles.modules.audit`.

**Immutability.** Set `ACTION_LOGGER_IMMUTABLE=true` to make log rows read-only (updates and deletes throw `ImmutableLogException`). Bulk query deletes bypass model events; restrict the table at the database level if you need stronger guarantees.

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
