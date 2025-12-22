# Laravel Roles

A reusable, multi-tenant, guard-aware ACL system for Laravel applications built on top of [spatie/laravel-permission](https://github.com/spatie/laravel-permission).

## Features

- Contract-based architecture with dependency injection
- Three tenancy modes: single, team-scoped, multi-database
- Guard-aware role and permission management
- Contextual caching (tenant, guard, locale aware)
- Permission matrix with efficient queries (no N+1)
- Diff-based permission sync with wildcard support
- Optional Vue UI (Inertia.js + shadcn-vue)
- Comprehensive artisan commands
- Full i18n support for labels and descriptions

## Requirements

- PHP 8.2+
- Laravel 12.0+
- spatie/laravel-permission 6.0+

## Supported Tenancy Modes

| Mode | Description |
|------|-------------|
| `single` | Non-multi-tenant applications |
| `team_scoped` | Spatie's built-in team feature |
| `multi_database` | External providers (stancl/tenancy, etc.) |

## Installation

```bash
composer require enadstack/laravel-roles
php artisan roles:install
php artisan roles:sync
```

## Documentation

- [Installation](docs/installation.md)
- [Configuration](docs/configuration.md)
- [Tenancy](docs/tenancy.md)
- [Guards](docs/guards.md)
- [Roles](docs/roles.md)
- [Permissions](docs/permissions.md)
- [Permission Matrix](docs/permission-matrix.md)
- [Vue UI](docs/ui-vue.md)
- [API Reference](docs/api.md)
- [Caching](docs/caching.md)
- [Commands](docs/commands.md)
- [Testing](docs/testing.md)
- [Upgrading](docs/upgrading.md)
- [Troubleshooting](docs/troubleshooting.md)
- [Feature Roadmap](docs/features.md)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history.

## License

MIT License. See [LICENSE](LICENSE) for details.

## Credits

- [Enad Abuzaid](https://github.com/enadabuzaid)
- Built on [spatie/laravel-permission](https://github.com/spatie/laravel-permission)
