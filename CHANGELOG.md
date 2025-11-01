# Changelog

All notable changes to the Laravel Roles & Permissions package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Service Layer Architecture**: Introduced `RoleService` and `PermissionService` for clean, maintainable code following best practices
- **Restore Endpoints**: Added restore functionality for soft-deleted roles and permissions
  - `POST /admin/acl/roles/{id}/restore`
  - `POST /admin/acl/permissions/{id}/restore`
- **Force Delete Endpoints**: Added permanent deletion endpoints for roles and permissions
  - `DELETE /admin/acl/roles/{id}/force`
  - `DELETE /admin/acl/permissions/{id}/force`
- **Bulk Operations for Roles**:
  - `POST /admin/acl/roles/bulk-delete` - Soft delete multiple roles at once
  - `POST /admin/acl/roles/bulk-restore` - Restore multiple soft-deleted roles at once
- **Recent Items Tracking**:
  - `GET /admin/acl/roles-recent` - Get recently created roles
  - `GET /admin/acl/permissions-recent` - Get recently created permissions
- **Statistics Endpoints**:
  - `GET /admin/acl/roles-stats` - Get comprehensive role statistics
  - `GET /admin/acl/permissions-stats` - Get comprehensive permission statistics with group breakdown
- **Permission Matrix**: 
  - `GET /admin/acl/permissions-matrix` - Visual matrix showing which roles have which permissions
- **Permission Assignment**:
  - `POST /admin/acl/roles/{id}/permissions` - Assign or replace permissions for a role
  - `GET /admin/acl/roles/{id}/permissions` - Get all permissions for a specific role
- **Permissions Grouped by Role**:
  - `GET /admin/acl/roles-permissions` - List all permissions organized by their assigned roles
- **Enhanced Filtering**: Added search, sorting, and filtering capabilities to list endpoints
- **Comprehensive Documentation**:
  - Complete README with usage examples and best practices
  - Detailed API Reference with all endpoints documented
  - CHANGELOG for tracking package evolution

### Changed
- **Controller Architecture**: Refactored controllers to use service layer instead of direct model access
- **Routes Organization**: Reorganized routes for better clarity and RESTful structure
- **Validation**: Improved request validation with detailed error messages
- **Response Format**: Standardized API responses for consistency

### Improved
- **Code Quality**: Applied clean code principles and SOLID design patterns
- **Maintainability**: Separated business logic into service classes for easier testing and maintenance
- **Reusability**: Made the package truly reusable across different projects with minimal configuration
- **Error Handling**: Enhanced error handling with meaningful messages and proper HTTP status codes
- **Performance**: Optimized queries for statistics and matrix generation

### Technical Details
- Enhanced `RoleController` with 11 new methods using service layer
- Enhanced `PermissionController` with 5 new methods using service layer
- Created `RoleService` with 12 public methods for role management
- Created `PermissionService` with 9 public methods for permission management
- Added 16 new routes for advanced operations
- Maintained backward compatibility with existing endpoints

## [1.0.0] - Initial Release

### Added
- Basic CRUD operations for roles and permissions
- Soft delete support for roles and permissions
- Multi-tenancy support (single, team-scoped, multi-database)
- Internationalization (i18n) support with multi-language labels
- Permission grouping functionality
- Integration with Spatie Laravel Permission package
- Interactive installation command (`php artisan roles:install`)
- Sync command for idempotent role/permission seeding (`php artisan roles:sync`)
- Database migrations for roles and permissions with i18n support
- Configuration file with extensive customization options
- Service provider for automatic package registration
- Seeder for initial roles and permissions
- Support for custom guards
- Routes with configurable prefix and middleware
- Permission groups endpoint for UI integration

### Features
- **Models**:
  - `Role` model extending Spatie's Role with soft deletes
  - `Permission` model extending Spatie's Permission with soft deletes and grouping
- **Controllers**:
  - `RoleController` with basic CRUD operations
  - `PermissionController` with basic CRUD operations
- **Commands**:
  - `roles:install` - Interactive installation wizard
  - `roles:sync` - Sync roles and permissions from config
- **Configuration**:
  - Multi-language support configuration
  - Tenancy mode selection
  - Custom routes configuration
  - Seed data configuration
- **Database**:
  - Migration for adding i18n, tenant, and soft delete columns to roles table
  - Migration for adding i18n, group, tenant, and soft delete columns to permissions table

---

## Upgrade Guide

### Upgrading to Latest Version

If you're upgrading from the initial release:

1. **Backup Your Database**: Always backup before upgrading
2. **Update Composer**: 
   ```bash
   composer update enadstack/laravel-roles
   ```
3. **Run Migrations**: No new migrations required for this update
4. **Clear Cache**:
   ```bash
   php artisan permission:cache-reset
   php artisan config:clear
   php artisan route:clear
   ```
5. **Update Route References**: If you're using route names, note the new routes added
6. **Service Layer**: Consider refactoring to use the new service layer for better maintainability

### Breaking Changes
- None. This release is fully backward compatible.

### Deprecations
- None

---

## Roadmap

Future planned features:

- [ ] Permission bulk operations (bulk delete, bulk restore)
- [ ] Role cloning functionality
- [ ] Permission templates
- [ ] Advanced permission inheritance
- [ ] Audit logging for all changes
- [ ] API versioning support
- [ ] GraphQL API support
- [ ] UI components (Vue/React) for permission management
- [ ] Real-time permission updates via WebSockets
- [ ] Permission usage analytics
- [ ] Export/Import functionality for roles and permissions
- [ ] Permission validation rules
- [ ] Role hierarchies and inheritance
- [ ] Time-based permissions (temporary access)
- [ ] IP-based permission restrictions

---

## Support

- **Issues**: [GitHub Issues](https://github.com/Enadabuzaid/laravel-roles/issues)
- **Documentation**: [README](README.md)
- **API Reference**: [API_REFERENCE](API_REFERENCE.md)

---

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

---

## Credits

- **Author**: Enad Abuzaid
- **Built on**: [Spatie Laravel Permission](https://github.com/spatie/laravel-permission)
- **Contributors**: [All Contributors](https://github.com/Enadabuzaid/laravel-roles/graphs/contributors)

---

## License

MIT License. See [LICENSE](LICENSE) for more information.
