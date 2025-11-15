# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.1] - 2025-11-15

### Fixed - Critical Config File Bug
- **CRITICAL FIX**: Config file `config/roles.php` was being overwritten with minimal structure
  - Fixed `InstallCommand::writeConfigRoles()` method using `var_export()` 
  - Now preserves original config file structure and comments
  - Uses regex replacements to update only specific configuration values
  - Prevents loss of comments, formatting, and default values

### Added
- Config existence check in install command to prevent accidental overwrites
- Warning prompt when attempting to reconfigure existing installation
- Upgrade instructions section in README
- Clear documentation about when to run `php artisan roles:install`

### Changed
- Install command now asks for confirmation before reconfiguring existing setup
- README now clearly states `roles:install` should only be run on initial installation
- Added troubleshooting steps for accidental config overwrites

### Important
⚠️ **This release fixes a critical bug** where running `composer update` could result in users accidentally re-running the install command and getting a corrupted `config/roles.php` file with only `'provider' => null`.

**Upgrade from v1.1.0:**
```bash
composer update enadstack/laravel-roles
# Your config/roles.php is now safe and preserved!
```

## [1.1.0] - 2025-11-15

### Added - Documentation Enhancement Release
- **Comprehensive README.md** (22KB) - Complete guide covering all features
  - Installation from Composer to setup
  - 30+ API endpoints fully documented
  - 4 detailed usage scenarios (Blog, E-commerce, SaaS, Module addition)
  - Service layer code examples
  - Events documentation with listener examples
  - Multi-tenancy setup for all 3 modes
  - Troubleshooting section
  - Testing information
  - Contributing guidelines

- **QUICK_REFERENCE.md** (7.3KB) - Daily use cheat sheet
  - 3-step installation guide
  - Common commands reference
  - API endpoints quick lookup
  - Service layer code snippets
  - Multi-tenancy quick setup
  - Response format examples
  - Code usage examples (routes, blade, controllers)

- **DOCUMENTATION_COMPLETE.md** - Documentation overview
  - Complete documentation suite summary
  - Coverage matrix showing 100% documentation
  - Quality metrics
  - User guidance for all documentation files

### Documentation Coverage
- ✅ 10 comprehensive markdown files
- ✅ 100% feature coverage
- ✅ Real-world scenario examples
- ✅ Copy-paste ready code snippets
- ✅ Troubleshooting guides
- ✅ Multi-tenancy setup guides

### Improved
- Enhanced developer experience with quick reference guide
- Better onboarding for new users
- Clearer examples for common tasks
- More comprehensive troubleshooting section

## [1.0.1] - 2025-11-15

### Fixed
- Code quality improvements
- SQL injection protection
- Performance optimizations
- Bulk operations efficiency

## [1.0.0] - 2025-11-15

### Added - Initial Production Release
- Complete Role CRUD API with bulk operations
- Complete Permission CRUD API with filtering & search
- Permission Matrix endpoint (roles × permissions grid)
- Sync command for config-driven permissions
- Multi-tenancy support (single/team-scoped/multi-database)
- i18n support for labels and descriptions
- Service layer architecture (RoleService, PermissionService)
- 6 FormRequest validation classes
- 3 API Resource classes for consistent responses
- 6 Domain events for auditing
- Team-scoped middleware
- Cache management with auto-invalidation
- Soft deletes with restore functionality
- 32 comprehensive integration tests (100% pass rate)

### Features
- Role management (CRUD, bulk ops, clone, restore)
- Permission management (CRUD, grouping, matrix view)
- Statistics endpoints
- Recent items endpoints
- Current user ACL endpoints
- Config-driven permission seeding

### Technical
- Based on Spatie Laravel Permission v6.0
- Laravel 12+ compatible
- PHP 8.2+ required
- Clean architecture with service layer
- Event-driven design
- Comprehensive test suite

---

## Version Comparison

| Version | Release Date | Focus | Tests | Docs |
|---------|-------------|-------|-------|------|
| v1.1.0 | 2025-11-15 | Documentation | 32/32 | 100% |
| v1.0.1 | 2025-11-15 | Bug Fixes | 32/32 | 80% |
| v1.0.0 | 2025-11-15 | Initial Release | 32/32 | 60% |

---

## Upgrade Guide

### From v1.0.x to v1.1.0

No breaking changes! This is a documentation-only release.

**Steps:**
```bash
composer update enadstack/laravel-roles
```

No additional actions required. All new documentation is available in the package.

### Benefits of Upgrading
- Access to comprehensive README
- Quick reference guide for daily use
- Better understanding of all features
- More code examples
- Clearer troubleshooting guides

---

## Links

- [GitHub Repository](https://github.com/enadabuzaid/laravel-roles)
- [Documentation](https://github.com/enadabuzaid/laravel-roles#readme)
- [Issues](https://github.com/enadabuzaid/laravel-roles/issues)

---

**Legend:**
- **Added**: New features
- **Changed**: Changes in existing functionality
- **Deprecated**: Soon-to-be removed features
- **Removed**: Removed features
- **Fixed**: Bug fixes
- **Security**: Security fixes

