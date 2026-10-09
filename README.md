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
| `guardrails` | see file | Rule list, toggles (`min_owners`, `prevent_self_escalation`, `prevent_self_destructive`, `prevent_target_above_actor`, `prevent_escalation`, `confirm_high_risk`, `type_to_confirm_destructive`) and `disabled` rule classes. Enforced; see Guardrails |
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

The users module adds `$user->accessState()` (HasOne), `$user->accessStatus()` (a `UserStatus`; `Active` when the user has no row in `user_access_states`, whatever `roles.users.default_status` says: that key only seeds rows created by `roles:backfill-users`) and `$user->isAccessActive()`. These are additive: the `AccessUser` contract is unchanged. Existing installs run `php artisan roles:install`, `php artisan migrate` and `php artisan roles:backfill-users` once. Settings live under `roles.users` (`mfa_enabled_column`, `invitation_ttl_hours`, `default_status`, `accept_url`, `saved_view_pages`).

Never call Spatie's `assignRole`, `removeRole`, `syncRoles`, `givePermissionTo`, `revokePermissionTo` or `syncPermissions` directly; an architecture test enforces this inside the package.

## Guardrails

**Wired now:** `AssignRoleToUser`, `RemoveRoleFromUser` and `SyncUserRoles` enforce last owner, self role change, escalation and high-risk confirmation; `RestoreRole` enforces self change (you hold the trashed role, checked on the pivot), escalation (the role has a permission you lack, or is the Owner role) and high-risk confirmation (the Owner role or any role with a high-risk permission needs `confirm: true`); `CreateRole` with `permissions` and `UpdateRolePermissions` / `UpdateMatrix` (role-permission edits) enforce the owner lock, self change (editing a role you hold), escalation (adding a permission you lack) and high-risk confirmation. **Implemented and unit-tested, wired when the operation exists (Phase 3/4):** self destructive, system role (delete/rename), destructive confirmation, and last-owner for suspend/deactivate/delete user. Toggles: `prevent_self_escalation` controls the self role change rule and `prevent_self_destructive` controls the self destructive rule (independent).

Every user role change goes through `Guardrails::assert()` inside the Action's transaction. A violation throws `AccessException` (HTTP 403, rendered in the API envelope), changes nothing and writes no audit entry. Rules are listed in `roles.guardrails.rules`; each can be toggled in `roles.guardrails`. Guardrail rules and doctor checks merge additively: the package defaults are always applied first, then any extras your published config lists, so a config that omits `rules` (or lists only custom rules) still enforces all nine built-ins. To switch a rule off, list its class in `roles.guardrails.disabled` (doctor checks use `roles.doctor.disabled`). Disabling a built-in guardrail is a security decision; do it deliberately.

| Code | Meaning | Applies to system actor |
| --- | --- | --- |
| `guardrail.last_owner` | Fewer than `min_owners` ACTIVE owners would remain (owners that are suspended, deactivated, invited or pending verification do not count; a locked owner does) | yes |
| `guardrail.self_role_change` | You changed your own role, edited a role you hold, or restored a role you hold | no |
| `guardrail.self_destructive` | You suspended, deactivated or deleted yourself | no |
| `guardrail.target_above_actor` | A non-owner suspended, reactivated, deactivated, deleted, unlocked or forced a password reset for an owner or a user whose effective permissions exceed their own | no (system actors and owners skip it) |
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
| POST | `roles` | `access.roles.create` (plus `access.roles.update` when `permissions` is non-empty, else 403 `forbidden`) | Body: `name` (required, slug `[a-z0-9][a-z0-9._-]*`, 2 to 64 chars, unique among live roles), `label` (required, `{en, ar, ...}` with the default locale required), optional `description` map, `color` (`#rrggbb` or null), `is_default`, `sort_order` (default max + 1), `permissions` (list of ids or names, max 500) and `confirm` (acknowledges high-risk permissions). Always non-system. Permissions are planned and asserted in the same transaction (escalation, high-risk, deprecated, unknown): a refusal leaves no role and writes no audit entry. `is_default` is refused for the Owner role name (422 `role.default_not_allowed`). 201 with the Role |
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
| GET | `users` | `access.users.view` | Paginated users of `roles.user_model`, joined to their access state (a user without a state row is `active`). `search` matches `name` and `email` literally. Filters: `status` (one or more of the user statuses), `role` (role id or name, repeatable), `mfa` (`on`/`off`), `last_login_from` / `last_login_to` and `joined_from` / `joined_to` (`YYYY-MM-DD`, both bounds inclusive; malformed dates are ignored), `no_role` (`yes`). Sorts: `name` (default), `email`, `status`, `last_login` (users who never logged in always sort last, in both directions, on every driver), `created`. `last_login_ip` is visible to every `access.users.view` holder (limiting it is a later decision). `include=roles` adds each user's roles as RoleLite. Returns User rows |
| GET | `users/overview` | `access.users.view` | Counts and weekly series; see Overview payloads |
| GET | `users/{user}` | `access.users.view` | User profile; 404 for an unknown user |
| GET | `users/{user}/permissions` | `access.users.view` | The user's effective permissions grouped by module, with where each comes from; see Payloads |
| POST | `users/{user}/suspend` | `access.users.suspend` | Body `reason` (required, max 500). Active or locked becomes suspended; already suspended is a 200 no-op (no write, no audit); any other status is 409 `user.invalid_transition`. Ends the user's database sessions and API tokens (any `tokens()` relation, e.g. Sanctum or Passport; both best effort and reported, never failing the action). Returns the User |
| POST | `users/{user}/reactivate` | `access.users.suspend` | Suspended or deactivated becomes active (roles are untouched, so access returns as it was; `status_reason` is cleared, `activated_at` is set only when it was empty and kept otherwise). Active, locked, invited and pending verification are 409 `user.invalid_transition` (use `unlock` for locked). Returns the User |
| POST | `users/{user}/deactivate` | `access.users.suspend` | Body `reason` (required, max 500). Active, locked or suspended becomes deactivated; already deactivated is a 200 no-op; invited / pending verification is 409. Deactivated is final except `reactivate`. Ends sessions and tokens. Returns the User |
| POST | `users/{user}/unlock` | `access.users.update` | Locked becomes active with `locked_until` and `failed_login_count` cleared; any other status is 409 `user.invalid_transition`. Returns the User |
| POST | `users/{user}/force-password-reset` | `access.users.update` | Ends the user's current access (database sessions and API tokens, exactly like suspend) and sends the host's password reset link through the password broker whose provider matches `config('roles.guard')`'s provider (the default broker only when the guard names no provider; when it names one that no broker uses, nothing is mailed and `broker_status` is `passwords.no_broker`). **It does not change the password: the old password keeps working until the user follows the link**, so pair it with a suspend when a credential is known to be compromised. Clears `password_changed_at` on an existing state row (no row is created) and leaves the status untouched. Never returns a token. `meta` has `link_sent` (the broker status is `passwords.sent`) and `broker_status` (e.g. `passwords.throttled`, `passwords.user`, or `mail_failed` when sending threw); a throttled or failed send does not undo the revocation, and the broker status is stored in the audit `meta`. Allowed on yourself. Returns the User  The recipient is resolved through that broker's own user provider and must be the same user id as the revoked target, otherwise nothing is mailed and `broker_status` is `passwords.user_mismatch` (`link_sent: false`; both are audited in `meta.broker_status`). |
| DELETE | `users/{user}` | `access.users.delete` | Body `confirmation` must equal the user's email (case-insensitive, trimmed; missing or wrong is 403 `guardrail.confirmation_mismatch`). Deletes through the host model (soft-deleted when it uses SoftDeletes), removes the role and permission pivot rows of that model, its state row and saved views, and sets `invited_by` to null on the invitations and state rows it created. Never the last active owner or yourself. Ends sessions and tokens. A host foreign key that still points at the user is 409 `user.in_use` with nothing changed. Soft-deleted users keep their email, so the address stays taken (new invitations or sign-ups for it will conflict until the row is force-deleted). 204 |
| GET | `invitations` | `access.invitations.view` | Paginated invitations, newest first. Filters `filter[status]` (`pending`, `accepted`, `expired`, `revoked`; repeatable), `search` (email), `sort` (`created`, `expires`, `-` for descending). `meta.counts` holds the four status totals. Row: see Payloads |
| POST | `invitations` | `access.invitations.create` | Body `email`, `role` (name or id, required), `confirm` for the Owner role or a high-risk role. The role goes through the assignment guardrails (escalation, high-risk confirmation, Owner role needs an Owner actor) but nothing is assigned until acceptance. 409 `invitation.user_exists` / `invitation.pending_exists`. 201 with the Invitation and `meta.mail_sent` |
| POST | `invitations/{invitation}/resend` | `access.invitations.create` | Rotates the token (the old link dies), restarts the expiry and mails the new link; optional `confirm`. 409 `invitation.not_pending` when accepted or revoked. 200 with the Invitation and `meta.mail_sent`; the response never carries the token |
| DELETE | `invitations/{invitation}` | `access.invitations.revoke` | Revokes a pending or expired invitation; already revoked is a 200 no-op (no audit); accepted is 409 `invitation.not_pending`. 200 with the Invitation |
| POST | `invitations/accept` | none (public) | See Invitations. Body `token`, `email`, `name`, `password`, `password_confirmation`. 200 `{accepted: true}` |
| GET | `saved-views` | `access.users.view` | The current user's saved filter views (never anyone else's), optionally `?page=users`. An unknown `page` is 422 |
| POST | `saved-views` | `access.users.view` | `page` (one of `roles.users.saved_view_pages`), `name` (1 to 80 chars, unique per user and page, 422 on a duplicate), `filters` (optional object: at most 20 keys, each value a scalar or a list of scalars, at most 2 KB as JSON). At most 25 views per user and page (422 `saved_view.limit`). Names and filters must be valid UTF-8 (422 otherwise). Saved views of every page require `access.users.view` (a known limit). Saved views are a personal preference and are not audited. 201 with the SavedView |
| DELETE | `saved-views/{view}` | `access.users.view` | Own views only; another user's id is 404, never 403. 204 |
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

**Invitation** (`GET invitations` rows and the write responses): `id`, `email`, `role` (RoleLite or null), `status` (`pending`, `accepted`, `revoked` or `expired`, derived: accepted wins over revoked over expired), `expires_at`, `accepted_at`, `revoked_at`, `invited_by` (`{id, name}`; `name` only when the viewer holds `access.users.view`; null when unknown), `created_at`. The token and its hash are never returned.

**RoleLite** (the `roles` of assignment responses): `id`, `name`, `label`, `label_text`, `color`, `is_system`.

**Member** (the `user` of assignment responses and the members list): `id` always; `name` and `email` only when the current user holds `access.users.view` (the members list requires it).

**User** (`GET users` rows): `id`, `name`, `email`, `status`, `status_reason`, `last_login_at`, `last_login_ip`, `mfa_enabled` (the state column, or the host column `roles.users.mfa_enabled_column` when that column exists on the user table, a nullable timestamp such as Fortify's `two_factor_confirmed_at`; a boolean column would always read as on), `email_verified_at`, `created_at`, `roles` (only with `include=roles`), `is_self`, `is_owner`, `locked`, `lock_reason` (`self`, `owner`, `above` or null). **`locked` only means `lock_reason` is not null (it is also true for your own row); it is not the account status `locked`, which is `status`. Read `lock_reason` for the cause** and `abilities` `{view, update, suspend, delete, assign}`. `owner` means the user holds the Owner role and you do not; `above` means they hold a permission you lack (the same computation the `guardrail.target_above_actor` rule uses); Owners are never locked by either. The flags are computed with a fixed number of queries however many rows are listed, and only narrow the permission-based abilities to what the server would accept.

**UserProfile** (`GET users/{user}`): the User fields plus `state` `{status, reason, activated_at, last_login_at, last_login_ip, failed_login_count, locked_until, mfa_enabled_at, mfa_method, password_changed_at}` (defaults for a user without a state row), `roles` (RoleLite plus `permissions_count`) and `effective_permissions_count` (distinct permissions through live roles plus direct grants).

**UserPermissions** (`GET users/{user}/permissions`): `data` is `[{module, permissions_count, permissions: [{id, name, module, label_text, risk, is_deprecated, sources, roles, direct}]}]`, modules and permissions sorted by name (permissions without a module are grouped under `other`). `sources` lists `role:<name>` for each live role granting it and `direct` for a direct grant; `roles` and `direct` give the same split. `meta` has `total` and `is_owner`.

**SavedView:** `id`, `page`, `name`, `filters` (an object), `created_at`.

**Permission:** `id`, `name`, `module`, `label`, `label_text`, `description`, `description_text`, `risk` (`low`/`medium`/`high`), `source` (`registry`/`manual`), `deprecated_at`, `is_deprecated`, `last_synced_at`, `sort_order`, `roles_count` (live roles of the configured guard holding it; present on list and detail), `created_at`, `updated_at`.

**SyncResult** (`POST permissions/sync`): `added`, `updated`, `restored`, `deprecated`, `pruned` (lists of permission names), `has_changes` (bool), `dry_run` (bool).

**Matrix** (`GET matrix`): `{modules, roles, summary}`.
- `modules`: `[{key, label, permissions: [{id, name, label_text, label, risk, deprecated}]}]`. Active permissions plus deprecated ones a live role still holds.
- `roles`: `[{id, name, label_text, color, is_system, is_owner, locked, permission_ids}]`; `locked` is true for the Owner role, a role you hold (unless you are an Owner) and every role when you may not update roles.
- `summary`: `{roles, permissions, grants}`.

**Me** (`GET me`): `{permissions, is_owner, role_ids, abilities}`. `permissions` are the package permission names (`access.*`) the user holds (all of them for an Owner); `role_ids` are the live roles the user holds; `abilities` is `{roles: {view, create, update, delete, assign}, permissions: {view, sync}, matrix: {view, update}}`. The route needs the `access:any` middleware (alias registered by the package; `any` is the only supported argument, anything else throws an `InvalidArgumentException` at request time; only non-deprecated permissions of the configured guard count): the user must be an Owner or hold at least one `access.*` permission, otherwise 403 `forbidden`.

**Overview payloads.**
- Roles (`GET roles/overview`): `total_roles` (includes trashed), `active_roles`, `system_roles`, `custom_roles`, `trashed_roles` (`active_roles` + `trashed_roles` = `total_roles`; `system_roles` + `custom_roles` = `active_roles`), `members_with_role`, `members_without_role` (users of `roles.user_model` holding at least one / no role of the configured guard), `avg_permissions_per_role`.
- Users (`GET users/overview`): `total_members`, `active`, `pending_invites` (usable invitations: not accepted, not revoked, not expired), `suspended`, `locked`, `deactivated` (users without a state row count as `active`), `without_mfa`, `without_role`, `joined_last_30_days` and `series` `{members, joined}`: eight rolling weekly buckets ending now, oldest first. `joined[i]` counts users created in the week `(b[i], b[i+1]]` and `members[i]` those created up to `b[i+1]` (cumulative). The buckets are rolling weeks, not calendar weeks, and `members[7]` equals `total_members` (a running total, not a trend). One SQL statement computes everything except the role and invitation counts.
- Permissions (`GET permissions/overview`): `total`, `active`, `deprecated`, `high_risk`, `manual`, `modules_count`, `unused` (active permissions held by no live role), `total_grants`, `avg_per_role`, `by_module` (`[{module, count, high_risk}]`) and `owner_only`. `owner_only` lists up to 5 active permissions (ordered by `sort_order`, then name) that no live role other than the Owner role holds, as `{id, name, label_text, module, risk}`: the permissions only the Owner can use today.

All roles and permissions are scoped to `roles.guard`: rows of another guard are 404 on `{role}` / `{permission}` routes and never appear in lists, overviews, modules, the matrix or the access check.

### Error codes

Errors are `{success: false, message, code, errors}`. `errors` is a field map for validation failures and an empty object otherwise.

| Code | HTTP | When |
| --- | --- | --- |
| `validation_failed` | 422 | Request validation failed (`errors` lists the fields), including an unknown `permission` on the access check, and a remember-me recall of an inactive user on a package JSON route (`errors.email` holds `access::auth.inactive`; the guard is logged out) |
| `unauthenticated` | 401 | No authenticated user |
| `forbidden` | 403 | The user lacks the route permission |
| `not_found` | 404 | Unknown, trashed (on routes that exclude them) or other-guard role, permission or user; unknown route |
| `method_not_allowed` | 405 | Wrong HTTP method for a route |
| `session_expired` | 419 | CSRF token mismatch or expired session; see Clients |
| `conflict` | 409 | Another HTTP 409 raised outside the package codes |
| `too_many_requests` | 429 | The `access` rate limit was exceeded |
| `http_error` | other 4xx/5xx | Any other HTTP exception |
| `guardrail.last_owner` | 403 | Fewer than `min_owners` active owners would remain |
| `guardrail.target_above_actor` | 403 | "You can't manage a user with more access than you have." |
| `user.invalid_transition` | 409 | A lifecycle action that does not apply to the user's current status; `errors.status` holds the current status |
| `user.in_use` | 409 | Deleting a user still referenced by a host foreign key; nothing was changed |
| `invitation.invalid` | 410 | Public accept: unknown, expired, revoked, already used token or an email that does not match; one identical response for all of them |
| `invitation.user_exists` | 409 | The email already belongs to a user (create, resend, accept); an existing account is never modified |
| `invitation.pending_exists` | 409 | A usable invitation for that email exists; resend it instead |
| `invitation.not_pending` | 409 | Resending or revoking an accepted (or, for resend, revoked) invitation |
| `saved_view.limit` | 422 | The user already has 25 saved views on that page |
| `account_inactive` | 403 | The signed-in user's access state no longer allows signing in; returned by the `access.active` middleware for JSON requests after it ended the session |
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

### Invitations

An invitation carries an email and a role. Create it (`POST invitations`), the package mails a link built from `roles.users.accept_url` (default `url('/accept-invitation')`) plus `?token=...&email=...`; your page posts the form to `POST {prefix}/invitations/accept`. Texts live in the package lang files (`invitation.php`, English and Arabic).

- **Token.** 64 random bytes, base64url. Only `sha256(token)` is stored; the plain token exists only in the mail (the notification is deliberately not queued, so it never enters a queue payload) and is never in a response, audit entry or log. The mail goes out after the transaction commits through `DB::afterCommit`; a delivery failure is reported, does not roll anything back and shows as `meta.mail_sent: false` (resend to retry). The TTL is `roles.users.invitation_ttl_hours` (72).
- **Accept.** One transaction with the invitation row locked. Unknown, expired, revoked, used tokens and a wrong email all answer the same 410 `invitation.invalid`. An email that already belongs to a user is 409 `invitation.user_exists` and nothing is changed. Otherwise the user is created through `roles.user_model` (`name`, `email`, hashed `password`, `email_verified_at` now), its state row is set `active` before the role is assigned, the invited role is assigned through `AssignRoleToUser` as the system actor with `confirmed: true` (the inviter's guardrails already vetted it), and the invitation is marked accepted. Fields other than the five documented are ignored (no `role`, `roles`, `is_admin`). If the invited role was deleted meanwhile the user is created without a role. The package writes `name`, `email` and the hashed `password` (plus `email_verified_at` only when the user table has that column; a model with the `hashed` password cast is not hashed twice) and nothing else. The user model must implement `AccessUser`, otherwise acceptance fails with a `LogicException` before anything is created. The response is `{accepted: true}`: the package does not log the user in, your app signs them in through its normal login.
- **Public route security.** `POST invitations/accept` is the only route of the package without authentication. It carries the middleware marker `access:public` (a pass-through; any other `access:` argument than `any` or `public` throws), the named limiter `access-invitation-accept` keyed by IP (`roles.users.accept_rate_limit`, default 6 per minute; 429 `too_many_requests` on the 7th attempt; behind a proxy or load balancer configure `TrustProxies`, otherwise every client shares the proxy's IP) and the configured route middleware WITHOUT its `auth*` entries (only string aliases such as `auth`, `auth:sanctum` or `auth.session` are stripped; an authentication middleware given as a class name or object stays, and would block the route). Keep the `web` group in `roles.routes.middleware` if you call it from an Inertia page so CSRF applies (a pure `api` stack has no CSRF). `roles:doctor` (`routes-protected`) accepts `access:public` for the route named `access.invitations.accept` only; any other route with the marker fails the check. - **Inviter authority.** A pending invitation does not outlive its inviter's authority. Suspending, deactivating or deleting the inviter revokes their pending invitations (`meta.revoked_invitations` on that audit entry, only when any). When an inviter loses a role (remove, sync, trash or force delete of the role), pending invitations whose role they can no longer assign (the Owner role without being an owner, or a role with a permission they no longer hold) are revoked, audited as `invitations.revoked_unauthorized`. At acceptance an inviter that was deleted or cannot sign in (suspended, deactivated, invited, pending verification) makes the token answer the same 410; locked inviters are tolerated, and invitations created by the system (`invited_by` null) are unaffected. A resend refuses while another pending invitation exists for the email (409 `invitation.pending_exists`). An invitation is expired from exactly `expires_at`.
- **Tokens and host tooling.** The plain token is in the mail link. Host tooling can capture it: the `log` mail driver, Telescope or similar recorders, and `NotificationSending` / `MessageSent` listeners. Use a real mailer in production and keep such tooling away from mail bodies.
- **Revoking.** `access.invitations.revoke` alone is enough to revoke any pending invitation, including owner-level ones.
Pending invitations can be disabled together with the routes via `roles.modules.invitations`.

### Inactive users

Users whose `user_access_states.status` is `suspended`, `deactivated`, `invited`, `pending_verification` or `locked` (until `locked_until` passes) cannot sign in. Two listeners cover the entry points: `Illuminate\Auth\Events\Validated` (`attempt()` and `validate()`) and `Illuminate\Auth\Events\Login` (Fortify's two-factor completion, `Auth::login()`, `loginUsingId()` and remember-me recall, which all skip `Validated`). Both throw a `ValidationException` on the `fortify.username` field (default `email`) with the translatable message `access::auth.inactive` (English and Arabic; publish with `--tag=roles-lang`); a refusal on `Login` also logs the guard out and invalidates its session. Users without a state row, models that are not `AccessUser`, a disabled `modules.users` and a missing `user_access_states` table all fail OPEN (everyone is treated as active; the doctor `users-state` check reports the missing table). The owner count behind the guardrails stays fail-closed: guardrailed writes need the table.

Remember-me: the recall happens when the guard first resolves the user, which fires `Login` before the `access.active` middleware runs. On a package JSON route that surfaces as 422 `validation_failed` (field `email`, message `access::auth.inactive`) and the guard is logged out; `403 account_inactive` is what an already-open session of a user suspended meanwhile receives. Both are tested.

Invitation acceptance sets the new user's state to `active` BEFORE the role is assigned and before any `Auth::login`, so the host may sign the user in right after the accept call. Registration flows of the host must not create `pending_verification` state rows before they log the user in: such a row makes the sign-in refuse the user.

The `access.active` middleware is the backstop for sessions that are already open (and for token guards): it logs the user out, invalidates the session and regenerates the CSRF token, then redirects web requests to the `login` route (403 when none exists) and answers JSON requests with HTTP 403 and code `account_inactive` (a deliberate choice: 403, not 401, because the credentials are fine but the account may not act). It reads the state once per request. The package appends it to its own API routes after the configured middleware (`roles.routes.enforce_active`, default `true`); add it to your web group yourself, for example `$middleware->appendToGroup('web', 'access.active')` in `bootstrap/app.php` (`roles:install` prints the reminder). An owner who is suspended, deactivated, invited or pending verification does not count towards `min_owners`; a locked owner does (lockouts are transient).

### Rate limiting

Package routes use the named limiter `access` (`throttle:access`), registered by the package. To override it, define `RateLimiter::for('access', ...)` in a provider that boots later than the package's (application providers do). The key is the user id, or the IP for guests; the limit is `roles.http.rate_limit` requests per minute (default 60; `null` disables the limiter). Exceeding it answers 429 `too_many_requests` with the usual `Retry-After` and `X-RateLimit-*` headers (the envelope keeps the headers of the original HTTP exception, so a 405 carries `Allow` too).

### Clients

With session authentication send the `X-XSRF-TOKEN` header (the value of the `XSRF-TOKEN` cookie) with `Accept: application/json` on every write. A CSRF mismatch or an expired session answers 419 `session_expired`: refresh the page or the CSRF cookie and retry once. Token clients (`auth:sanctum` tokens) are not subject to CSRF.

## Audit

Writes go to `enadstack/laravel-action-logger` (^1.1), module `roles`, `permissions` or `users`. Each effective write is one entry; no-ops, refusals and rollbacks write none.

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
| `users.suspended` | users | `warning` (`critical` when the target is an owner) | the user | `old_values` / `new_values` hold `status`; `meta.reason` |
| `users.reactivated` / `users.unlocked` | users | `info` | the user | `old_values` / `new_values` hold `status` |
| `users.deactivated` | users | `warning` (`critical` when the target is an owner) | the user | `meta.reason` |
| `users.password_reset_forced` | users | `info` | the user | One entry per request; `meta.broker_status` |
| `users.deleted` | users | `critical` | none | `old_values` hold `id`, `name`, `email`; `meta.was_owner` |
| `invitations.created` | invitations | `info` (`warning` for a high-risk role, `critical` for the Owner role) | the invitation | `meta.email`, `meta.role`; never the token |
| `invitations.resent` / `invitations.revoked` | invitations | `info` | the invitation | `meta.email`, `meta.role` |
| `invitations.revoked_unauthorized` | invitations | `info` | the inviter | The inviter lost the authority for some pending invitations; `meta.revoked_invitations`, `meta.invitation_ids` |
| `invitations.accepted` | invitations | `info` | the invitation | Actor is the new user. Accepting writes TWO entries on purpose (two resources change): this one and `users.activated` (module `users`, subject the new user, `meta.via` = `invitation`); the reused role Action adds its own `roles.assigned` |
| `permissions.created` | permissions | `info` | the permission | |
| `permissions.updated` | permissions | `info` (`warning` when a high risk was lowered) | the permission | Changed attributes only |
| `permissions.deleted` | permissions | `warning` | none | `meta` holds `id` and `name` |
| `permissions.synced` | permissions | `info` (`warning` when permissions were deprecated or pruned) | none | A non-dry-run sync changed something; counts per change type plus names (capped at 200 per list) |

Role write events (`roles.created`, `roles.updated`, `roles.trashed`, `roles.restored`, `roles.deleted`) are written once per effective change; a no-op update writes nothing. Moving `is_default` records `default_cleared_from` in `meta`.

**User lifecycle notes.** `reactivate` also clears `locked_until` and `failed_login_count` and keeps an existing `activated_at`. Unlock and force-reset are allowed on yourself (suspend, deactivate and delete are not). Ending sessions deletes `sessions` rows only for the `database` session driver and revokes tokens only when the user model has a `tokens()` relation; for every other driver, and for a login that races a suspension, the `access.active` middleware is the backstop that ends the session on the user's next request. Add it to your web group.

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
