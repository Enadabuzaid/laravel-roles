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
| `guardrails` | see file | Rule list, toggles (`min_owners`, `prevent_self_escalation`, `prevent_self_destructive`, `prevent_escalation`, `confirm_high_risk`, `type_to_confirm_destructive`) and `disabled` rule classes. Enforced; see Guardrails |
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

**Wired now:** `AssignRoleToUser`, `RemoveRoleFromUser` and `SyncUserRoles` enforce last owner, self role change, escalation and high-risk confirmation; `UpdateRolePermissions` and `UpdateMatrix` (role-permission edits) enforce the owner lock, self change (editing a role you hold), escalation (adding a permission you lack) and high-risk confirmation. **Implemented and unit-tested, wired when the operation exists (Phase 3/4):** self destructive, system role (delete/rename), destructive confirmation, and last-owner for suspend/deactivate/delete user. Toggles: `prevent_self_escalation` controls the self role change rule and `prevent_self_destructive` controls the self destructive rule (independent).

Every user role change goes through `Guardrails::assert()` inside the Action's transaction. A violation throws `AccessException` (HTTP 403, rendered in the API envelope), changes nothing and writes no audit entry. Rules are listed in `roles.guardrails.rules`; each can be toggled in `roles.guardrails`. Guardrail rules and doctor checks merge additively: the package defaults are always applied first, then any extras your published config lists, so a config that omits `rules` (or lists only custom rules) still enforces all eight built-ins. To switch a rule off, list its class in `roles.guardrails.disabled` (doctor checks use `roles.doctor.disabled`). Disabling a built-in guardrail is a security decision; do it deliberately.

| Code | Meaning | Applies to system actor |
| --- | --- | --- |
| `guardrail.last_owner` | Fewer than `min_owners` owners would remain | yes |
| `guardrail.self_role_change` | You changed your own role | no |
| `guardrail.self_destructive` | You suspended, deactivated or deleted yourself | no |
| `guardrail.escalation` | Granting a role with permissions you lack, or adding/removing the owner role as a non-owner | no |
| `guardrail.actor_required` | A guarded write ran outside console with no authenticated actor and no explicit `runAs(null)` | n/a |
| `guardrail.high_risk_confirm_required` | Adding a high-risk or owner role without `confirmed: true` | yes |
| `guardrail.owner_role_locked` | Editing the Owner role's permissions (always on, no toggle) | yes |
| `guardrail.system_role` | Deleting or renaming a system role (or the super role) | yes |
| `guardrail.confirmation_mismatch` | Type-to-confirm text missing or wrong (an empty expected value, such as a user without an email, always mismatches) | yes |

**Actor context.** The actor is resolved in this order: an explicit `set`/`runAs` actor, then the user of the default guard (the one your route middleware selected, e.g. `auth:sanctum`), then the user of `roles.guard`, otherwise none. "No actor" means the system only in console (commands, queued jobs, seeders, tinker) or inside an explicit `runAs(null, ...)`. An HTTP request without an authenticated user is never treated as the system: guarded writes fail with `guardrail.actor_required` (403) and change nothing. Force an actor with `runAs`:

```php
app(ActorContext::class)->runAs($admin, fn () => $user->assignAccessRole('manager'));
app(ActorContext::class)->runAs(null, fn () => $user->assignAccessRole('viewer')); // system, even if someone is logged in
```

`roles:seed`, `roles:owner` and `roles:sync` always run as the system actor. State-based rules (last owner, high-risk confirmation, system role, destructive confirmation) still apply to it, so console code that grants the owner role must pass the flag: `$user->assignAccessRole('owner', confirmed: true)`.

**Confirmation flags.** `confirmed: true` acknowledges a high-risk or owner role. Re-assigning a role the user already holds is a silent no-op (no exception, no audit entry).

**Concurrency.** Assign/remove/sync lock the super-role row (`SELECT ... FOR UPDATE`) before the guardrails run, so two concurrent owner removals serialize and the last-owner rule cannot be raced.

**Known limitations.**
- Suspended owners are still counted by the last-owner rule.
- The last-owner lock relies on `SELECT ... FOR UPDATE`; on MySQL under REPEATABLE READ the snapshot read can still race. PostgreSQL and SQLite are supported.
- Spatie's `permission:*` artisan commands and raw `HasRoles` mutators (`assignRole`, `syncRoles`, `givePermissionTo`, ...) bypass guardrails and audit. Use the `HasAccess` methods; `roles:doctor` warns about the gap.
- `syncAccessRoles` replaces roles of every guard (Spatie semantics): other-guard roles count as removed.

## Permission matrix

Role-permission edits go through `Roles\Actions\UpdateRolePermissions` (one role) and `UpdateMatrix` (many roles, one transaction). Both fail closed without a known actor, lock the super-role row, and run the guardrails (`Operation` type `UpdateRolePermissions`) before any write. `UpdateMatrix` plans every role and asserts every guardrail before the first write, so a violation on any role changes nothing and writes no audit entry. A change that grants and revokes nothing writes nothing. Deprecated permissions can be revoked but not granted (`permission.deprecated`, 409); unknown ones are `permission.not_found` (404); the same permission in `grant` and `revoke` is `permission.conflicting_changes` (422). The permission cache is cleared after every effective change; each changed role writes one `roles.permissions_updated` audit entry (severity `warning` when a high-risk permission was added).

| Endpoint | Permission | Notes |
| --- | --- | --- |
| `GET matrix` | `access.permissions.view` | `{modules, roles, summary}` in three queries; each role has `permission_ids`, `is_owner` and `locked` (owner role, a role you hold unless you are an owner, or every role if you may not update roles) |
| `PUT matrix` | `access.roles.update` | Body `{changes: {"<roleId>": {grant: [...], revoke: [...]}}, confirm?: bool}`; max 200 roles and 500 entries per list. Returns `{roles: {"<roleId>": {added, removed}}, summary: {granted, revoked, roles_changed, high_risk_granted}}` |
| `PUT roles/{role}/permissions` | `access.roles.update` | Body `{grant?: [...], revoke?: [...], confirm?: bool}`; entries are permission ids (integers) or names (strings). Returns `{role, added, removed}`; a trashed role is 404 |

The Owner role can't be edited by anyone (`guardrail.owner_role_locked`), a non-owner can't edit a role they hold (`guardrail.self_role_change`) or add a permission they lack (`guardrail.escalation`), and adding a high-risk permission needs `confirm: true` (`guardrail.high_risk_confirm_required`, also for the system actor). Permission references are ids when they are JSON integers and names when they are strings, so form-encoded posts (everything is a string) can only use names. A matrix update may reference at most 5000 permissions in total. Editing the Owner role is refused even when the change would be a no-op, so clients must not send the owner column. Roles are created without permissions; use `PUT roles/{role}/permissions` afterwards.

## Endpoint reference

All paths are relative to `roles.routes.prefix` (default `api/access`) and run under `roles.routes.middleware` (set `['web', 'auth']` for session auth or `['api', 'auth:sanctum']` for tokens). Every route also carries a `can:` middleware for the permission below; failures use the error envelope (401 `unauthenticated`, 403 `forbidden`, 404 `not_found`, 422 `validation_failed`, plus the stable `guardrail.*` codes).

| Method | Path | Permission | Notes |
| --- | --- | --- | --- |
| GET | `roles` | `access.roles.view` | Paginated list; `search`, `filter[type]` (system/custom), `filter[status]` (trashed/all), `filter[default]`, `sort`, `include` |
| GET | `roles/overview` | `access.roles.view` | Counts for dashboards |
| POST | `roles` | `access.roles.create` | `name`, `label{en,...}`, optional `description`, `color`, `is_default`, `sort_order`; always non-system |
| GET | `roles/{role}` | `access.roles.view` | `label` maps plus `label_text` in the request locale |
| PUT/PATCH | `roles/{role}` | `access.roles.update` | Replaces `label`/`description` wholesale; the Owner role can't be renamed |
| DELETE | `roles/{role}` | `access.roles.delete` | Soft delete; `confirmation` (exact role name) required when it has members; system and Owner roles refused |
| POST | `roles/{role}/restore` | `access.roles.delete` | 409 `role.not_trashed` for a live role |
| DELETE | `roles/{role}/force` | `access.roles.delete` | Trashed roles only; `confirmation` (exact name); 204 |
| GET | `roles/{role}/members` | `access.roles.view` + `access.users.view` | Paginated members |
| PUT | `roles/{role}/permissions` | `access.roles.update` | `grant`, `revoke`, `confirm`; see Permission matrix |
| POST | `users/{user}/roles` | `access.roles.assign` | `role` (name or id), `confirm` for the Owner role or high-risk roles |
| PUT | `users/{user}/roles` | `access.roles.assign` | `roles: [...]` replaces the set (empty list allowed), `confirm` |
| DELETE | `users/{user}/roles/{role}` | `access.roles.assign` | Last-owner and self-change guardrails apply |
| GET | `permissions` | `access.permissions.view` | Paginated list with `source`, `risk`, `deprecated` |
| GET | `permissions/overview` | `access.permissions.view` | Totals, per-module counts, owner-only permissions |
| GET | `permissions/modules` | `access.permissions.view` | Module list |
| GET | `permissions/check` | `access.permissions.view` | `?user=<id>&permission=<name>`: does the user hold the permission? |
| POST | `permissions` | `access.permissions.sync` | Manual permission (`name`, `module`, `label`, `risk`); `source` is always `manual` |
| GET | `permissions/{permission}` | `access.permissions.view` | |
| PUT/PATCH | `permissions/{permission}` | `access.permissions.sync` | Manual permissions only; registry ones are 409 `permission.registry_managed` |
| DELETE | `permissions/{permission}` | `access.permissions.sync` | Manual only; `confirmation` (exact name) |
| POST | `permissions/sync` | `access.permissions.sync` | `dry_run`, `prune`; dry run writes and audits nothing |
| GET | `matrix` | `access.permissions.view` | Modules, roles with `permission_ids`, summary |
| PUT | `matrix` | `access.roles.update` | Atomic multi-role grant/revoke |

Each effective write produces exactly one audit entry (two roles changed in a matrix update produce two). JSON requests only receive the locale cookie when the test or client sends credentials; in Laravel tests use `withCredentials()->withUnencryptedCookie('locale', 'ar')`.

## Audit

Writes go to `enadstack/laravel-action-logger` (^1.1), module `roles` or `permissions`:

| Event | Subject | When |
| --- | --- | --- |
| `roles.assigned` / `roles.removed` / `roles.synced` | the user | only if the role set changed; `old_values`/`new_values` hold `roles`, `meta` holds `role` or `added`/`removed` |
| `roles.seeded` | none | roles were created or restored |
| `permissions.synced` | none | a non-dry-run sync changed something; counts per change type plus names (capped at 200 per list) |

Role write events (`roles.created`, `roles.updated`, `roles.trashed`, `roles.restored`, `roles.deleted`) are written once per effective change; a no-op update writes nothing. Moving `is_default` records `default_cleared_from` in `meta`.

**Role endpoint notes.** `PUT`/`PATCH roles/{role}` replaces the `label` and `description` maps wholesale: sending only `en` drops `ar`. Renaming or creating onto the name of a trashed role answers 409 `role.name_taken_trashed` (restore it instead).

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

Roles overview (`GET roles/overview`): `total_roles` includes trashed roles (`active_roles` + `trashed_roles` = `total_roles`); `system_roles` + `custom_roles` = `active_roles`. `members_with_role` / `members_without_role` count users of the configured user model. Role search matches the per-locale values of `label` and `description` (locales from `roles.locales`), never the JSON keys.

## Testing

```bash
vendor/bin/pest                       # sqlite in-memory
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_USERNAME=postgres DB_PASSWORD= DB_DATABASE=roles_testing vendor/bin/pest
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint
```

## License

MIT
