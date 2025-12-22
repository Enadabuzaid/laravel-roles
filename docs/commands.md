# Commands

This document covers the artisan commands provided by Laravel Roles.

## roles:install

Interactive installation command.

### Purpose

Sets up the package in a new Laravel application.

### Usage

```bash
php artisan roles:install
```

### What It Does

1. Publishes the config file
2. Publishes Spatie permission migrations (if needed)
3. Publishes package migrations
4. Runs all migrations
5. Optionally runs `roles:sync`

### Options

| Option | Description |
|--------|-------------|
| `--force` | Overwrite existing config |
| `--no-migrate` | Skip running migrations |
| `--no-sync` | Skip running roles:sync |

### Example

```bash
php artisan roles:install --force

Publishing config...
Publishing migrations...
Running migrations...
Would you like to sync roles and permissions now? (yes/no) [yes]: yes
Running roles:sync...

Installation complete!
```

## roles:sync

Synchronize roles and permissions from configuration.

### Purpose

Creates roles and permissions defined in config, assigns permissions to roles based on the mapping.

### Usage

```bash
php artisan roles:sync
```

### What It Does

1. Creates roles defined in `seed.roles`
2. Creates permissions from `seed.permission_groups`
3. Expands wildcards in `seed.map`
4. Assigns permissions to roles
5. Clears permission cache

### Options

| Option | Description |
|--------|-------------|
| `--dry-run` | Show what would happen without making changes |
| `--prune` | Remove permissions not in config (use with caution) |
| `--team-id=` | Run for specific team (team-scoped mode) |
| `--guard=` | Override guard from config |
| `--verbose-output` | Show detailed output |

### Dry Run Example

```bash
php artisan roles:sync --dry-run

[DRY RUN] Would create roles:
  - admin (web)
  - editor (web)
  - viewer (web)

[DRY RUN] Would create permissions:
  - users.list (web)
  - users.create (web)
  - users.show (web)
  - users.update (web)
  - users.delete (web)

[DRY RUN] Would assign permissions:
  - admin: users.list, users.create, users.show, users.update, users.delete
  - editor: users.list, users.create, users.show, users.update
  - viewer: users.list, users.show

No changes made (dry run).
```

### Idempotency

The command is idempotent:

- Running twice produces the same result
- Existing roles/permissions are not duplicated
- Only missing items are created

### Team-Scoped Usage

```bash
# Sync for specific team
php artisan roles:sync --team-id=1
php artisan roles:sync --team-id=2

# With tenancy package
php artisan tenants:run roles:sync
```

### Multi-Database Usage

Run in tenant context:

```bash
# stancl/tenancy
php artisan tenants:run roles:sync

# Or manually set tenant context before running
```

## roles:doctor

Diagnostic command to verify configuration.

### Purpose

Checks that the package is correctly configured and identifies common issues.

### Usage

```bash
php artisan roles:doctor
```

### What It Checks

1. Config file exists and is valid
2. Database tables exist
3. Guard configuration is valid
4. Tenancy mode is set correctly
5. Cache driver is working
6. No orphaned permissions
7. No guard mismatches
8. UI configuration (if enabled)

### Example Output

```
Laravel Roles Doctor - v1.3.0
=============================

Configuration
  [OK] Config file exists
  [OK] Guard 'web' is configured in auth.guards

Database
  [OK] Table 'roles' exists
  [OK] Table 'permissions' exists
  [OK] Table 'role_has_permissions' exists
  [OK] Table 'model_has_roles' exists
  [OK] Table 'model_has_permissions' exists

Tenancy
  [OK] Mode: single
  [OK] No team configuration required

Cache
  [OK] Cache driver: redis
  [OK] Cache is enabled

Data Integrity
  [OK] No orphaned permissions found
  [OK] All roles have matching guards
  [OK] No duplicate role names per guard

UI
  [--] UI is disabled

=============================
All checks passed.
```

### Failure Example

```
Laravel Roles Doctor - v1.3.0
=============================

Configuration
  [OK] Config file exists
  [FAIL] Guard 'admin' is not configured in auth.guards

Database
  [OK] Table 'roles' exists
  [WARN] Table 'permissions' has 5 orphaned entries

=============================
1 error, 1 warning found.

Suggestions:
- Add 'admin' guard to config/auth.php
- Run 'php artisan roles:sync --prune' to remove orphaned permissions
```

### Options

| Option | Description |
|--------|-------------|
| `--fix` | Attempt to fix issues automatically |
| `--json` | Output results as JSON |

### JSON Output

```bash
php artisan roles:doctor --json
```

```json
{
    "version": "1.3.0",
    "checks": {
        "config": {"status": "ok", "message": "Config file exists"},
        "guard": {"status": "fail", "message": "Guard 'admin' not found"}
    },
    "errors": 1,
    "warnings": 0
}
```

## Command Summary

| Command | Purpose |
|---------|---------|
| `roles:install` | Initial setup |
| `roles:sync` | Sync roles/permissions from config |
| `roles:doctor` | Verify configuration |

## Next Steps

- [Installation](installation.md)
- [Configuration](configuration.md)
- [Troubleshooting](troubleshooting.md)
