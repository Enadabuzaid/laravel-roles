# Troubleshooting

Common issues and their solutions for the Laravel Roles package.

---

## roles:sync Fails with Missing Columns

### Symptoms

When running `php artisan roles:sync`, you see:

```
SQLSTATE[HY000]: General error: 1 no such column: label
```

Or similar errors for `description` or `group_label` columns.

### Root Cause

The `roles:sync` command attempts to update permission metadata (label, description, group_label), but these columns don't exist in your database. This commonly happens because:

1. You're using an older version of the package
2. The metadata migration was not published or run
3. You're on a fresh installation without running migrations

### Fix

**Step 1: Publish and run migrations**

```bash
php artisan migrate
```

**Step 2: Verify columns exist**

```bash
php artisan roles:doctor
```

Look for the "Permission Metadata Columns" section:

```
Permission Metadata Columns:
  └ label ......................................... Present
  └ description ................................... Present
  └ group_label ................................... Present
```

**Step 3: Re-run sync**

```bash
php artisan roles:sync
```

### Alternative: Skip Metadata Updates

If you don't need metadata columns, the v1.3.3+ version of `roles:sync` will automatically skip them if they don't exist. You can verify this:

```bash
php artisan roles:sync --verbose-output
```

You should see: `Updating labels and descriptions ... skipped (no metadata columns)`

---

## Create/Edit Pages Redirect to /admin

### Symptoms

- Roles index page (`/admin/acl/roles`) works correctly
- Clicking "Create Role" or "Edit" redirects to `/admin` or shows 404
- The browser URL changes but the page doesn't load

### Root Cause

This typically happens due to:

1. **Missing Inertia page resolver configuration** - The app.ts doesn't know how to resolve `LaravelRoles/*` pages
2. **Missing Vite alias** - The `@/laravel-roles` import path isn't configured
3. **Pages not published** - The Vue pages weren't published to `resources/js/Pages/LaravelRoles/`

### Fix

**Step 1: Verify pages are published**

```bash
ls -la resources/js/Pages/LaravelRoles/
```

You should see:
- RolesIndex.vue
- RoleCreate.vue
- RoleEdit.vue
- PermissionsIndex.vue
- PermissionMatrix.vue

If missing, republish:

```bash
php artisan vendor:publish --tag=roles-vue --force
```

**Step 2: Configure Vite alias**

Add to `vite.config.ts`:

```typescript
resolve: {
    alias: {
        '@': path.resolve(__dirname, './resources/js'),
        '@/laravel-roles': path.resolve(__dirname, './resources/js/laravel-roles'),
    },
},
```

**Step 3: Update Inertia page resolver**

In `resources/js/app.ts`:

```typescript
createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob([
            './Pages/**/*.vue',
            './Pages/LaravelRoles/**/*.vue',  // Add this
        ])
        return resolvePageComponent(`./Pages/${name}.vue`, pages)
    },
    // ...
})
```

**Step 4: Clear Vite cache and rebuild**

```bash
npm run build
# or for development
npm run dev
```

**Step 5: Verify routes**

```bash
php artisan route:list --name=roles.ui
```

All routes should be listed with proper controller actions.

---

## UI Pages Not Rendering Inside App Layout

### Symptoms

- Package pages render but without your app's sidebar/header
- Pages appear as standalone without navigation
- Layout is different from other pages in your app

### Root Cause

Package pages are published as standalone Vue components. They don't automatically use your app's layout because:

1. Inertia needs to be configured to apply a default layout
2. Or each page needs to manually import and use your layout

### Fix

**Option A: Configure Inertia to auto-apply layout**

In `resources/js/app.ts`:

```typescript
import AppLayout from '@/Layouts/AppLayout.vue'

createInertiaApp({
    resolve: async (name) => {
        const page = await resolvePageComponent(`./Pages/${name}.vue`, pages)
        
        // Auto-apply AppLayout to Laravel Roles pages
        if (name.startsWith('LaravelRoles/')) {
            page.default.layout = page.default.layout || AppLayout
        }
        
        return page
    },
    // ...
})
```

**Option B: Edit each page manually**

Open each published page and wrap content with your layout:

```vue
<template>
  <AppLayout>
    <!-- existing page content here -->
  </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue'
// ... rest of imports
</script>
```

---

## "Cannot find module '@/laravel-roles/...'"

### Symptoms

TypeScript/IDE errors showing:

```
Cannot find module '@/laravel-roles/api' or its corresponding type declarations.
```

### Root Cause

The `@/laravel-roles` alias is not configured in:
- `vite.config.ts` (for runtime)
- `tsconfig.json` (for TypeScript/IDE support)

### Fix

**Step 1: Update vite.config.ts**

```typescript
resolve: {
    alias: {
        '@': path.resolve(__dirname, './resources/js'),
        '@/laravel-roles': path.resolve(__dirname, './resources/js/laravel-roles'),
    },
},
```

**Step 2: Update tsconfig.json (optional, for IDE support)**

```json
{
    "compilerOptions": {
        "paths": {
            "@/*": ["./resources/js/*"],
            "@/laravel-roles/*": ["./resources/js/laravel-roles/*"]
        }
    }
}
```

**Step 3: Restart IDE/TypeScript server**

In VS Code: `Ctrl+Shift+P` → "TypeScript: Restart TS Server"

---

## API Requests Return 404

### Symptoms

UI pages load but data doesn't appear. Network tab shows 404 errors for API requests.

### Root Cause

1. API routes are not loaded
2. API prefix configuration mismatch
3. Missing `window.laravelRoles` configuration

### Fix

**Step 1: Verify API routes are registered**

```bash
php artisan route:list --name=roles
```

You should see API routes like:
- `GET roles/api/roles`
- `POST roles/api/roles`
- etc.

**Step 2: Check config prefix**

```bash
php artisan tinker
>>> config('roles.routes.prefix')
"admin/acl"
```

**Step 3: Add window config to layout**

In your base Blade layout, before `</head>`:

```html
<script>
  window.laravelRoles = {
    apiPrefix: '{{ config("roles.routes.prefix") }}',
    uiPrefix: '{{ config("roles.ui.prefix") }}',
  };
</script>
```

---

## Permission Denied Errors

### Symptoms

Pages show "403 Forbidden" or "This action is unauthorized"

### Root Cause

The package uses Laravel's authorization (policies) to control access. You need to either:
1. Define policies for Role and Permission models
2. Give your user the required permissions

### Fix

**Option A: Create a super-admin check**

In `AuthServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    // Super-admin bypass
    Gate::before(function ($user, $ability) {
        if ($user->hasRole('super-admin')) {
            return true;
        }
    });
}
```

**Option B: Define policies**

Create policies for Role and Permission models that check appropriate permissions.

---

## SQLite Compatibility Issues

### Symptoms

Migrations or sync commands fail on SQLite with JSON column errors.

### Root Cause

Earlier versions used JSON column type which isn't fully supported by SQLite.

### Fix

**Upgrade to v1.3.3+** which uses TEXT columns for SQLite compatibility.

Then run:

```bash
php artisan migrate
```

---

## Need More Help?

1. Run diagnostics: `php artisan roles:doctor`
2. Check the [GitHub Issues](https://github.com/enadstack/laravel-roles/issues)
3. Ensure you're on the latest version: `composer update enadstack/laravel-roles`
