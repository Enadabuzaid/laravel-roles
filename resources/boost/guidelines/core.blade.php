## enadstack/laravel-roles

Roles, permissions, guardrails and an audit API on top of spatie/laravel-permission. Install: `php artisan roles:install`, then `migrate`, `roles:sync`, `roles:seed`, `roles:owner you@example.com`, and check with `php artisan roles:doctor`.

- Permissions live in `access/permissions.php` (registry); run `roles:sync` after editing. Never create permissions by hand.
- Users use the `HasAccess` trait and implement `AccessUser`; use `assignAccessRole`, `syncAccessRoles`, `isOwner()`. Do not call Spatie's `assignRole` directly: guardrails and audit would be skipped.
- Mutate roles only through the Actions in `Enadstack\LaravelRoles\Roles\Actions` (one transaction, guardrails, audit entry).
- Protect routes with the `access` middleware alias and `can:access.<module>.<ability>`; add `access.active` to the web group.
- The `owner` super role and `is_system` roles are locked by guardrails; the last owner cannot be removed.

### Multi-tenant (teams)
- Enable with `php artisan roles:install --teams=1 --team-key=tenant_id` (sets `permission.teams` and `roles.teams`, key = `permission.team_foreign_key`), then migrate. `roles:doctor` verifies both flags and the column.
- Set the tenant per request: `Enadstack\LaravelRoles\Support\TeamContext::set($tenantId)` from your tenant middleware, or use the `access.team` alias (reads `roles.team_resolver` or the user's team key attribute).
- Roles with a null team key are global (platform admin); `Role` queries show the current team's roles plus global ones, never another team's.
- Seed per tenant: `TeamContext::run($tenantId, fn () => app(SeedDefaultRoles::class)->handle())` or `php artisan roles:seed --team=<id>`.
- In queues and commands the team is not carried over: wrap the work in `TeamContext::run()`.
