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
| `routes.prefix` / `routes.middleware` | `api/access` / `['api','auth:sanctum']` | API routes; the middleware is your authentication (and session/CSRF) stack |
| `http.rate_limit` | `60` | Requests per minute per user (per IP for guests) through the `access` named limiter; `null` or `0` disables it. The legacy `'attempts,minutes'` string still parses. See Rate limiting |
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

The users module adds `$user->accessState()` (HasOne), `$user->accessStatus()` (a `UserStatus`; `Active` when the user has no row in `user_access_states`) and `$user->isAccessActive()`. These are additive: the `AccessUser` contract is unchanged. Existing installs run `php artisan roles:install`, `php artisan migrate` and `php artisan roles:backfill-users` once. Settings live under `roles.users` (`mfa_enabled_column`, `invitation_ttl_hours`, `default_status`, `accept_url`, `saved_view_pages`).

Never call Spatie's `assignRole`, `removeRole`, `syncRoles`, `givePermissionTo`, `revokePermissionTo` or `syncPermissions` directly; an architecture test enforces this inside the package.

## Guardrails

**Wired now:** `AssignRoleToUser`, `RemoveRoleFromUser` and `SyncUserRoles` enforce last owner, self role change, escalation and high-risk confirmation; `RestoreRole` enforces self change (you hold the trashed role, checked on the pivot), escalation (the role has a permission you lack, or is the Owner role) and high-risk confirmation (the Owner role or any role with a high-risk permission needs `confirm: true`); `CreateRole` with `permissions` and `UpdateRolePermissions` / `UpdateMatrix` (role-permission edits) enforce the owner lock, self change (editing a role you hold), escalation (adding a permission you lack) and high-risk confirmation. **Implemented and unit-tested, wired when the operation exists (Phase 3/4):** self destructive, system role (delete/rename), destructive confirmation, and last-owner for suspend/deactivate/delete user. Toggles: `prevent_self_escalation` controls the self role change rule and `prevent_self_destructive` controls the self destructive rule (independent).

Every user role change goes through `Guardrails::assert()` inside the Action's transaction. A violation throws `AccessException` (HTTP 403, rendered in the API envelope), changes nothing and writes no audit entry. Rules are listed in `roles.guardrails.rules`; each can be toggled in `roles.guardrails`. Guardrail rules and doctor checks merge additively: the package defaults are always applied first, then any extras your published config lists, so a config that omits `rules` (or lists only custom rules) still enforces all eight built-ins. To switch a rule off, list its class in `roles.guardrails.disabled` (doctor checks use `roles.doctor.disabled`). Disabling a built-in guardrail is a security decision; do it deliberately.

| Code | Meaning | Applies to system actor |
| --- | --- | --- |
| `guardrail.last_owner` | Fewer than `min_owners` owners would remain | yes |
| `guardrail.self_role_change` | You changed your own role, edited a role you hold, or restored a role you hold | no |
| `guardrail.self_destructive` | You suspended, deactivated or deleted yourself | no |
| `guardrail.escalation` | Granting or restoring a role with permissions you lack, or adding/removing/restoring the owner role as a non-owner | no |
| `guardrail.actor_required` | A guarded write ran outside console with no authenticated actor and no explicit `runAs(null)` | n/a |
| `guardrail.high_risk_confirm_required` | Adding or restoring a high-risk or owner role, or granting a high-risk permission, without `confirmed: true` | yes |
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

The Owner role can't be edited by anyone (`guardrail.owner_role_locked`), a non-owner can't edit a role they hold (`guardrail.self_role_change`) or add a permission they lack (`guardrail.escalation`), and adding a high-risk permission needs `confirm: true` (`guardrail.high_risk_confirm_required`, also for the system actor). Permission references are ids when they are JSON integers and names when they are strings, so form-encoded posts (everything is a string) can only use names. A matrix update may reference at most 5000 permissions in total. Editing the Owner role is refused even when the change would be a no-op, so clients must not send the owner column. A role can also be created with permissions in one atomic call (`POST roles` with `permissions`, see Endpoint reference).

## Endpoint reference

All paths are relative to `roles.routes.prefix` (default `api/access`) and run under `roles.routes.middleware` (set `['web', 'auth']` for session auth or `['api', 'auth:sanctum']` for tokens) plus the `throttle:access` limiter. Every route also carries a `can:` (or `access:`) middleware for the permission below, and the read controllers authorize again themselves, so a host that re-registers the routes without the middleware is still protected. Failures use the error envelope (see Error codes).

**Reference rules.** A role in a JSON body is an id when it is a JSON integer and a name when it is a string; a permission is likewise an id (integer) or a name (string). Form-encoded requests send everything as strings, so they can only use names. A string role reference is always a name, even when it is all digits (`"12"` is the role named `12`, never id 12). Route parameters (`{role}`, `{permission}`, `{user}`) are digits only (1 to 18 digits): uuid and ulid keys are not supported in 2.0-alpha.

**Lists.** Every list accepts `search`, `filter[key]`, `sort` (`-` prefix for descending), `page`, `per_page` (max `pagination.max`) and `include`. Unknown filters, sorts and includes are ignored. Responses carry `meta.pagination`.

| Method | Path | Permission | Notes |
| --- | --- | --- | --- |
| GET | `me` | any `access.*` permission (or Owner) | The current user's package permissions and abilities; see Me |
| GET | `roles` | `access.roles.view` | Paginated Role list. `search` matches `name` and the per-locale `label` and `description` values. Filters: `type` (`system`/`custom`), `status` (`trashed`: only trashed, `all`: live and trashed; default live only), `default` (boolean). Sorts: `name`, `members`, `permissions`, `updated`, `sort_order` (default). `include=permissions` |
| GET | `roles/overview` | `access.roles.view` | Counts; see Overview payloads |
| POST | `roles` | `access.roles.create` | Body: `name` (required, slug `[a-z0-9][a-z0-9._-]*`, 2 to 64 chars, unique among live roles), `label` (required, `{en, ar, ...}` with the default locale required), optional `description` map, `color` (`#rrggbb` or null), `is_default`, `sort_order` (default max + 1), `permissions` (list of ids or names, max 500) and `confirm` (acknowledges high-risk permissions). Always non-system. Permissions are planned and asserted in the same transaction (escalation, high-risk, deprecated, unknown): a refusal leaves no role and writes no audit entry. `is_default` is refused for the Owner role name (422 `role.default_not_allowed`). 201 with the Role |
| GET | `roles/{role}` | `access.roles.view` | Role with `permissions`; a trashed role is returned too |
| PUT/PATCH | `roles/{role}` | `access.roles.update` | Same fields as create (all optional, `permissions` is not accepted here). Replaces `label` / `description` wholesale. The Owner role can't be renamed; `is_default` is refused for the Owner role and system roles (422 `role.default_not_allowed`); a trashed role is 404 |
| DELETE | `roles/{role}` | `access.roles.delete` | Soft delete; `confirmation` (exact role name) required when it has members; system and Owner roles refused |
| POST | `roles/{role}/restore` | `access.roles.delete` | Optional `confirm`. Guardrails: you can't restore a role you hold (`guardrail.self_role_change`), a role with permissions you lack or the Owner role (`guardrail.escalation`), and the Owner role or a role with high-risk permissions needs `confirm: true` (`guardrail.high_risk_confirm_required`). 409 `role.not_trashed` for a live role |
| DELETE | `roles/{role}/force` | `access.roles.delete` | Trashed roles only; `confirmation` (exact name); 204 |
| GET | `roles/{role}/members` | `access.roles.view` + `access.users.view` | Paginated Member list; `search` (name, email); sorts `name`, `email` |
| PUT | `roles/{role}/permissions` | `access.roles.update` | `grant`, `revoke`, `confirm`; see Permission matrix |
| POST | `users/{user}/roles` | `access.roles.assign` | `role` (name or id), `confirm` for the Owner role or high-risk roles. Returns `{user, roles}` |
| PUT | `users/{user}/roles` | `access.roles.assign` | `roles: [...]` replaces the set (empty list allowed), `confirm`. Returns `{user, roles}` |
| DELETE | `users/{user}/roles/{role}` | `access.roles.assign` | Last-owner and self-change guardrails apply. Returns `{user, roles}` |
| GET | `permissions` | `access.permissions.view` | Paginated Permission list. `search` matches `name` and the per-locale `label` / `description`. Filters: `module`, `risk` (`low`/`medium`/`high`), `source` (`registry`/`manual`), `status` (`active` default, `deprecated`, `all`), `held` (`yes`/`no`: held by at least one role). Sorts: `name`, `module`, `risk`, `roles`, `updated`, `sort_order` (default) |
| GET | `permissions/overview` | `access.permissions.view` | Counts; see Overview payloads |
| GET | `permissions/modules` | `access.permissions.view` | `[{key, label, permissions_count}]` of active permissions |
| GET | `permissions/check` | `access.permissions.view` + `access.users.view` | `?user=<id>&permission=<name>`. `permission` must be an existing permission of the configured guard (422 `validation_failed` otherwise: arbitrary gate abilities are never evaluated); an unknown user is 404. Returns `{allowed, via, roles}`: `via` is `owner`, `role` (`roles` lists the granting role names), `direct` or `null` |
| POST | `permissions` | `access.permissions.sync` | Manual permission: `name` (slug, unique), `module`, `label`, optional `description`, `risk`, `sort_order`; `source` is always `manual` |
| GET | `permissions/{permission}` | `access.permissions.view` | |
| PUT/PATCH | `permissions/{permission}` | `access.permissions.sync` | Manual permissions only (registry ones are 409 `permission.registry_managed`). Lowering `risk` from `high` is refused with 409 `permission.in_use` while any role or user holds the permission, and is audited as a warning otherwise |
| DELETE | `permissions/{permission}` | `access.permissions.sync` | Manual only, unheld only (409 `permission.in_use`); `confirmation` (exact name); 204 |
| POST | `permissions/sync` | `access.permissions.sync` | `dry_run`, `prune`; returns a SyncResult; a dry run writes and audits nothing |
| GET | `matrix` | `access.permissions.view` | Matrix payload |
| PUT | `matrix` | `access.roles.update` | Atomic multi-role grant/revoke |

Each effective write produces exactly one audit entry (two roles changed in a matrix update produce two). Reads and no-op writes produce none. JSON requests only receive the locale cookie when the test or client sends credentials; in Laravel tests use `withCredentials()->withUnencryptedCookie('locale', 'ar')`.

### Payloads

Every success is `{success: true, message, data, meta}`; the shapes below are `data`. Timestamps are ISO 8601 strings. `*_text` fields are the value in the request locale with the fallback locale as fallback.

**Role** (list, show, create, update, trash, restore):

| Field | Type | Notes |
| --- | --- | --- |
| `id`, `name` | int, string | |
| `label`, `description` | map or null | Per-locale values; `label_text`, `description_text` are the request-locale values |
| `color` | string or null | `#rrggbb` |
| `is_system`, `is_default` | bool | |
| `sort_order` | int | |
| `members_count`, `permissions_count` | int | Present on list and detail responses |
| `permissions` | list or absent | `{id, name, label_text, risk, module}`; present on detail responses and with `include=permissions` |
| `deleted_at`, `created_at`, `updated_at` | string or null | |
| `is_owner` | bool | The role is the super role (`roles.super_role`) of the configured guard |
| `locked` | bool | `lock_reason` is not null |
| `lock_reason` | `owner`, `system`, `held` or null | `owner`: the super role; `system`: a system role; `held`: a role the current user holds and the user is not an Owner (trashed roles they hold included). Priority owner, then system, then held. A UI should disable the matching controls and show the reason |
| `abilities.create` | bool | The user may create roles |
| `abilities.update` | bool | The user may update roles. Label, description, color and sort order stay editable on locked roles |
| `abilities.rename` | bool | `update` and the role is not `owner` or `system`: only then may the UI send `name` (and `is_default`) |
| `abilities.update_permissions` | bool | `update` and `lock_reason` is neither `owner` nor `held` (the server refuses both) |
| `abilities.delete` | bool | The user may delete roles and the role is not `owner` or `system` |
| `abilities.assign` | bool | The user may assign roles, and the role is not the Owner role unless the user is an Owner |

These flags are computed without per-role queries (one query for the current user's roles, memoized per request). They mirror what the server would accept; the guardrails remain the authority.

**RoleLite** (the `roles` of assignment responses): `id`, `name`, `label`, `label_text`, `color`, `is_system`.

**Member** (the `user` of assignment responses and the members list): `id` always; `name` and `email` only when the current user holds `access.users.view` (the members list requires it).

**Permission:** `id`, `name`, `module`, `label`, `label_text`, `description`, `description_text`, `risk` (`low`/`medium`/`high`), `source` (`registry`/`manual`), `deprecated_at`, `is_deprecated`, `last_synced_at`, `sort_order`, `roles_count` (live roles of the configured guard holding it; present on list and detail), `created_at`, `updated_at`.

**SyncResult** (`POST permissions/sync`): `added`, `updated`, `restored`, `deprecated`, `pruned` (lists of permission names), `has_changes` (bool), `dry_run` (bool).

**Matrix** (`GET matrix`): `{modules, roles, summary}`.
- `modules`: `[{key, label, permissions: [{id, name, label_text, label, risk, deprecated}]}]`. Active permissions plus deprecated ones a live role still holds.
- `roles`: `[{id, name, label_text, color, is_system, is_owner, locked, permission_ids}]`; `locked` is true for the Owner role, a role you hold (unless you are an Owner) and every role when you may not update roles.
- `summary`: `{roles, permissions, grants}`.

**Me** (`GET me`): `{permissions, is_owner, role_ids, abilities}`. `permissions` are the package permission names (`access.*`) the user holds (all of them for an Owner); `role_ids` are the live roles the user holds; `abilities` is `{roles: {view, create, update, delete, assign}, permissions: {view, sync}, matrix: {view, update}}`. The route needs the `access:any` middleware (alias registered by the package): the user must be an Owner or hold at least one `access.*` permission, otherwise 403 `forbidden`.

**Overview payloads.**
- Roles (`GET roles/overview`): `total_roles` (includes trashed), `active_roles`, `system_roles`, `custom_roles`, `trashed_roles` (`active_roles` + `trashed_roles` = `total_roles`; `system_roles` + `custom_roles` = `active_roles`), `members_with_role`, `members_without_role` (users of `roles.user_model` holding at least one / no role of the configured guard), `avg_permissions_per_role`.
- Permissions (`GET permissions/overview`): `total`, `active`, `deprecated`, `high_risk`, `manual`, `modules_count`, `unused` (active permissions held by no live role), `total_grants`, `avg_per_role`, `by_module` (`[{module, count, high_risk}]`) and `owner_only`. `owner_only` lists up to 5 active permissions (ordered by `sort_order`, then name) that no live role other than the Owner role holds, as `{id, name, label_text, module, risk}`: the permissions only the Owner can use today.

All roles and permissions are scoped to `roles.guard`: rows of another guard are 404 on `{role}` / `{permission}` routes and never appear in lists, overviews, modules, the matrix or the access check.

### Error codes

Errors are `{success: false, message, code, errors}`. `errors` is a field map for validation failures and an empty object otherwise.

| Code | HTTP | When |
| --- | --- | --- |
| `validation_failed` | 422 | Request validation failed (`errors` lists the fields), including an unknown `permission` on the access check |
| `unauthenticated` | 401 | No authenticated user |
| `forbidden` | 403 | The user lacks the route permission |
| `not_found` | 404 | Unknown, trashed (on routes that exclude them) or other-guard role, permission or user; unknown route |
| `method_not_allowed` | 405 | Wrong HTTP method for a route |
| `session_expired` | 419 | CSRF token mismatch or expired session; see Clients |
| `conflict` | 409 | Another HTTP 409 raised outside the package codes |
| `too_many_requests` | 429 | The `access` rate limit was exceeded |
| `http_error` | other 4xx/5xx | Any other HTTP exception |
| `guardrail.last_owner` | 403 | Fewer than `min_owners` owners would remain |
| `guardrail.self_role_change` | 403 | Changing, or restoring, a role you hold; editing a role you hold |
| `guardrail.self_destructive` | 403 | Suspending, deactivating or deleting yourself |
| `guardrail.escalation` | 403 | Granting access above your own, or touching the Owner role as a non-owner (assign, remove, restore) |
| `guardrail.high_risk_confirm_required` | 403 | A high-risk grant, the Owner role or a high-risk restore without `confirm: true` |
| `guardrail.owner_role_locked` | 403 | Editing the Owner role's permissions |
| `guardrail.system_role` | 403 | Deleting or renaming a system role or the Owner role |
| `guardrail.confirmation_mismatch` | 403 | Type-to-confirm text missing or wrong |
| `guardrail.actor_required` | 403 | A guarded write with no authenticated actor outside the console |
| `role.not_trashed` | 409 | Restoring or force deleting a live role |
| `role.name_taken` | 409 | Restoring a role whose name is now used by another role |
| `role.name_taken_trashed` | 409 | Creating or renaming onto the name of a trashed role |
| `role.default_not_allowed` | 422 | `is_default` on the Owner role or a system role |
| `permission.not_found` | 404 | A referenced permission does not exist |
| `permission.deprecated` | 409 | Granting a deprecated permission |
| `permission.conflicting_changes` | 422 | The same permission in `grant` and `revoke` |
| `permission.registry_managed` | 409 | Editing or deleting a registry permission |
| `permission.in_use` | 409 | Deleting a held permission, or lowering the risk of a held high-risk one |
| `registry.invalid` | 422 | The registry file failed validation during `permissions/sync` |

### Rate limiting

Package routes use the named limiter `access` (`throttle:access`), registered by the package unless your app defines its own. The key is the user id, or the IP for guests; the limit is `roles.http.rate_limit` requests per minute (default 60; `null` disables the limiter). Exceeding it answers 429 `too_many_requests` with the usual `Retry-After` headers.

### Clients

With session authentication send the `X-XSRF-TOKEN` header (the value of the `XSRF-TOKEN` cookie) with `Accept: application/json` on every write. A CSRF mismatch or an expired session answers 419 `session_expired`: refresh the page or the CSRF cookie and retry once. Token clients (`auth:sanctum` tokens) are not subject to CSRF.

## Audit

Writes go to `enadstack/laravel-action-logger` (^1.1), module `roles` or `permissions`. Each effective write is one entry; no-ops, refusals and rollbacks write none.

| Event | Module | Severity | Subject | When |
| --- | --- | --- | --- | --- |
| `roles.created` | roles | `info` (`warning` when a high-risk permission was granted) | the role | `meta.permissions` lists the permissions granted at creation; `meta.default_cleared_from` when `is_default` moved |
| `roles.updated` | roles | `info` | the role | Changed attributes only (`old_values` / `new_values`); `meta.default_cleared_from` when `is_default` moved |
| `roles.trashed` | roles | `info` (`warning` when the role had members) | the role | `meta.members_count` |
| `roles.restored` | roles | `info` | the role | |
| `roles.deleted` | roles | `critical` | none | Force delete; `old_values` and `meta` carry the name |
| `roles.permissions_updated` | roles | `info` (`warning` when a high-risk permission was added) | the role | `old_values` / `new_values` hold `permissions`; `meta` holds `added` / `removed` |
| `roles.assigned` / `roles.removed` / `roles.synced` | roles | `critical` when the Owner role is added or removed, `warning` when a high-risk role is added, else `info` | the user | Only if the role set changed; `old_values` / `new_values` hold `roles`, `meta` holds `role` or `added` / `removed` |
| `roles.seeded` | roles | `info` | none | Roles were created or restored |
| `permissions.created` | permissions | `info` | the permission | |
| `permissions.updated` | permissions | `info` (`warning` when a high risk was lowered) | the permission | Changed attributes only |
| `permissions.deleted` | permissions | `warning` | none | `meta` holds `id` and `name` |
| `permissions.synced` | permissions | `info` (`warning` when permissions were deprecated or pruned) | none | A non-dry-run sync changed something; counts per change type plus names (capped at 200 per list) |

Role write events (`roles.created`, `roles.updated`, `roles.trashed`, `roles.restored`, `roles.deleted`) are written once per effective change; a no-op update writes nothing. Moving `is_default` records `default_cleared_from` in `meta`.

**Role endpoint notes.** `PUT`/`PATCH roles/{role}` replaces the `label` and `description` maps wholesale: sending only `en` drops `ar`. Renaming or creating onto the name of a trashed role answers 409 `role.name_taken_trashed` (restore it instead), except that renaming a system role is `guardrail.system_role` first.

**After-commit semantics.** Entries are written after the surrounding transaction commits: a rollback (including an outer transaction) writes nothing. A failing logger is reported via `report()` and never breaks the role change. Disable with `roles.audit.enabled` or `roles.modules.audit`.

**Immutability.** Set `ACTION_LOGGER_IMMUTABLE=true` to make log rows read-only (updates and deletes throw `ImmutableLogException`). Bulk query deletes bypass model events; restrict the table at the database level if you need stronger guarantees.

## Commands

| Command | Purpose |
|---|---|
| `roles:install` | Publish config, registry stub, Spatie assets |
| `roles:sync [--dry-run] [--prune]` | Registry file to database |
| `roles:seed` | Create default roles (idempotent) |
| `roles:owner {email}` | Make a user the owner |
| `roles:backfill-users [--dry-run]` | Create `active` access state rows for users without one (chunked, idempotent) |
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
