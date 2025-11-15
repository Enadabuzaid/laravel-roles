# ✅ v1.1.1 Released - Critical Config Fix

## 🔧 Critical Bug Fix Release

**Package**: enadstack/laravel-roles  
**Version**: v1.1.1 (Patch Release)  
**Release Date**: November 15, 2025  
**Type**: Critical Bug Fix  
**Status**: ✅ Released and Live

---

## 🐛 The Problem That Was Fixed

### Issue Description
When users installed the package or ran `php artisan roles:install`, the `config/roles.php` file was being overwritten with a corrupted structure:

**Before Fix (Corrupted Config):**
```php
<?php  
return array (   
    'provider' => NULL, 
);
```

**Expected (Full Config):**
```php
<?php

return [
    'i18n' => [
        'enabled' => false,
        'locales' => ['en'],
        'default' => 'en',
        'fallback' => 'en',
    ],
    'guard' => env('ROLES_GUARD', 'web'),
    'tenancy' => [
        'mode' => 'single',
        'team_foreign_key' => 'team_id',
        'provider' => null,
    ],
    'routes' => [
        'prefix' => 'admin/acl',
        'middleware' => ['api', 'auth:sanctum'],
        'expose_me' => true,
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => 300,
        'keys' => [...],
    ],
    'seed' => [...],
];
```

### Root Cause
The `InstallCommand::writeConfigRoles()` method was using `var_export()` to write the config array, which:
- ❌ Lost all comments
- ❌ Lost clean formatting
- ❌ Only exported runtime config (which could be minimal)
- ❌ Lost default values

---

## ✅ The Fix

### What Changed

1. **New writeConfigRoles() Method**
   - ✅ Reads original config template
   - ✅ Preserves ALL comments and structure
   - ✅ Uses regex to update only specific values
   - ✅ Maintains all default values
   - ✅ Keeps clean formatting

2. **Added Safety Checks**
   - ✅ Checks if config already exists
   - ✅ Asks for confirmation before overwriting
   - ✅ Prevents accidental reconfiguration

3. **Updated Documentation**
   - ✅ Clear warning in README about when to run install
   - ✅ Added upgrade instructions
   - ✅ Added troubleshooting steps

---

## 🚀 How to Upgrade

### From v1.1.0 to v1.1.1

```bash
# Simply update the package
composer update enadstack/laravel-roles

# Clear caches
php artisan config:clear
php artisan cache:clear
```

**That's it!** Your existing `config/roles.php` is now safe and will be preserved.

### From v1.0.x to v1.1.1

```bash
# Update the package
composer update enadstack/laravel-roles

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan permission:cache-reset
```

---

## ⚠️ Important Notes

### DO NOT Re-run Install Command

❌ **WRONG** (During upgrade):
```bash
composer update enadstack/laravel-roles
php artisan roles:install  # ❌ DON'T DO THIS!
```

✅ **CORRECT** (During upgrade):
```bash
composer update enadstack/laravel-roles
# That's it! Config is preserved automatically
```

### When to Run roles:install

- ✅ **ONLY** on first installation of the package
- ❌ **NEVER** when upgrading to a newer version

---

## 🔍 Technical Details

### Files Changed

1. **src/Commands/InstallCommand.php**
   - Rewrote `writeConfigRoles()` method
   - Added config existence check
   - Added confirmation prompt
   - Uses regex replacements instead of var_export

2. **README.md**
   - Added upgrade instructions
   - Added warning about install command
   - Added troubleshooting section

3. **composer.json**
   - Version bumped to 1.1.1

4. **CHANGELOG.md**
   - Added v1.1.1 entry with fix details

### Code Changes Summary

**Before (Broken):**
```php
protected function writeConfigRoles(Filesystem $fs, array $config): void
{
    $target = config_path('roles.php');
    $export = var_export($config, true);  // ❌ Loses everything
    $php = "<?php\n\nreturn {$export};\n";
    $fs->put($target, $php);
}
```

**After (Fixed):**
```php
protected function writeConfigRoles(Filesystem $fs, array $config): void
{
    $target = config_path('roles.php');
    $source = __DIR__ . '/../../config/roles.php';
    
    // Copy original if doesn't exist
    if (!file_exists($target)) {
        $fs->copy($source, $target);
    }
    
    $content = $fs->get($target);
    
    // Use regex to update only specific values
    // Preserves all comments, formatting, and defaults
    // ... (detailed regex replacements)
    
    $fs->put($target, $content);
}
```

---

## 📊 Version Comparison

| Feature | v1.1.0 | v1.1.1 |
|---------|--------|--------|
| Tests Passing | 32/32 | 32/32 |
| Config Preservation | ❌ Broken | ✅ Fixed |
| Install Safety Check | ❌ No | ✅ Yes |
| Upgrade Instructions | ❌ No | ✅ Yes |
| Breaking Changes | 0 | 0 |

---

## ✅ Testing

All 32 tests still passing:
```
✅ Permission API Tests: 14/14 passing
✅ Role API Tests: 14/14 passing
✅ Permission Matrix Test: 1/1 passing
✅ Role Endpoints Test: 1/1 passing
✅ Sync Command Tests: 2/2 passing

TOTAL: 32/32 passing (100%)
```

---

## 📝 Git Information

### Tags
```
v1.1.1 (latest) ← NEW! Critical fix
v1.1.0
v1.0.1
v1.0.0
```

### Commit
```
fix: Preserve config/roles.php structure during installation (v1.1.1)
```

---

## 🎯 Summary

**What This Release Does:**
- ✅ Fixes critical config file corruption bug
- ✅ Preserves config structure and comments
- ✅ Adds safety checks during installation
- ✅ Improves documentation
- ✅ No breaking changes
- ✅ 100% backward compatible

**Who Should Upgrade:**
- 🔴 **All v1.1.0 users** - This is a critical fix
- 🟡 **All v1.0.x users** - Recommended upgrade
- ✅ **New users** - Get the fixed version automatically

**Upgrade Command:**
```bash
composer update enadstack/laravel-roles
```

---

## 🔗 Links

- **GitHub**: https://github.com/enadabuzaid/laravel-roles
- **Release**: https://github.com/enadabuzaid/laravel-roles/releases/tag/v1.1.1
- **Changelog**: See CHANGELOG.md
- **Issue Fixed**: Config file corruption during installation

---

**Release Status**: ✅ Successfully Released  
**Upgrade Recommended**: 🔴 Critical - Upgrade Immediately  
**Breaking Changes**: ✅ None  
**Backward Compatible**: ✅ Yes

🎉 **v1.1.1 is now live! All users should upgrade to get the fix.**

