# Quick Start Guide - Laravel Roles Vue UI

Get started with the roles & permissions UI in 5 minutes.

## 📦 Installation

### Step 1: Install Dependencies

```bash
# Core dependencies
npm install @inertiajs/vue3 lucide-vue-next

# Install shadcn-vue
npx shadcn-vue@latest init

# Add required components
npx shadcn-vue@latest add button card table checkbox accordion
npx shadcn-vue@latest add dropdown-menu select input textarea label badge
npx shadcn-vue@latest add toggle-group breadcrumb
```

### Step 2: Copy Files

Copy the entire `resources/js` folder to your project:

```bash
cp -r vendor/enadstack/laravel-roles/resources/js/* resources/js/
```

Or if developing the package:

```
resources/js/
├── components/
├── composables/
├── pages/
├── types/
└── locales/
```

### Step 3: Configure TypeScript (tsconfig.json)

```json
{
  "compilerOptions": {
    "target": "ES2020",
    "useDefineForClassFields": true,
    "module": "ESNext",
    "lib": ["ES2020", "DOM", "DOM.Iterable"],
    "skipLibCheck": true,
    "moduleResolution": "bundler",
    "allowImportingTsExtensions": true,
    "resolveJsonModule": true,
    "isolatedModules": true,
    "noEmit": true,
    "jsx": "preserve",
    "strict": true,
    "noUnusedLocals": true,
    "noUnusedParameters": true,
    "noFallthroughCasesInSwitch": true,
    "paths": {
      "@/*": ["./resources/js/*"]
    }
  },
  "include": ["resources/js/**/*.ts", "resources/js/**/*.vue"],
  "references": [{ "path": "./tsconfig.node.json" }]
}
```

### Step 4: Update Vite Config (vite.config.ts)

```typescript
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import { fileURLToPath } from 'url'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/app.ts', 'resources/css/app.css'],
      refresh: true,
    }),
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url))
    }
  }
})
```

## 🚀 Basic Usage

### Create Your First Page

**1. Create routes in `routes/web.php`:**

```php
use Inertia\Inertia;

Route::middleware(['auth'])->prefix('admin/acl')->group(function () {
    Route::get('/roles', fn() => Inertia::render('RolesIndex'))
        ->name('roles.index');
    
    Route::get('/roles/create', fn() => Inertia::render('RoleCreate'))
        ->name('roles.create');
    
    Route::get('/roles/{role}/edit', fn($role) => Inertia::render('RoleEdit', [
        'roleId' => $role
    ]))->name('roles.edit');
    
    Route::get('/permissions', fn() => Inertia::render('PermissionsIndex'))
        ->name('permissions.index');
    
    Route::get('/permissions/matrix', fn() => Inertia::render('PermissionMatrix'))
        ->name('permissions.matrix');
});
```

**2. That's it! Navigate to `/admin/acl/roles` to see the UI.**

## 🎨 Customization

### Change Theme Colors

Edit `tailwind.config.js`:

```javascript
module.exports = {
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: 'hsl(var(--primary))',
          foreground: 'hsl(var(--primary-foreground))',
        },
        // ... customize more colors
      }
    }
  }
}
```

### Add Translations

**Laravel way** - Create `lang/en/roles.php`:

```php
return [
    'roles' => [
        'title' => 'Roles Management',
        'create' => 'Create New Role',
        // ... more translations
    ]
];
```

**Vue i18n way** - Update `resources/js/locales/en.ts`:

```typescript
export default {
  roles: {
    roles: {
      title: 'Your Custom Title'
    }
  }
}
```

### Customize Components

All components accept standard props and emit events:

```vue
<template>
  <RoleTable
    :roles="roles"
    :loading="isLoading"
    @edit="handleEdit"
    @delete="handleDelete"
  />
</template>
```

## 🔧 Configuration

### API Base URL

If your API is on a different domain:

```typescript
// In composables, change:
const response = await fetch('/api/roles')

// To:
const apiUrl = import.meta.env.VITE_API_URL || ''
const response = await fetch(`${apiUrl}/api/roles`)
```

### CSRF Token

The composables look for Laravel's CSRF token:

```html
<!-- In your layout -->
<meta name="csrf-token" content="{{ csrf_token() }}">
```

### Per Page Default

Change in composables:

```typescript
const meta = ref({
  per_page: 20, // Change this
  // ...
})
```

## 📱 Responsive Breakpoints

The UI is responsive using Tailwind breakpoints:

- `sm`: 640px (mobile landscape)
- `md`: 768px (tablet)
- `lg`: 1024px (desktop)
- `xl`: 1280px (large desktop)

## 🎯 Common Recipes

### Add Toast Notifications

```bash
npm install vue-sonner
```

```typescript
// In your app.ts
import { Toaster } from 'vue-sonner'

app.component('Toaster', Toaster)
```

```vue
<script setup lang="ts">
import { toast } from 'vue-sonner'

const handleDelete = async (role) => {
  try {
    await deleteRole(role.id)
    toast.success('Role deleted successfully')
  } catch (error) {
    toast.error('Failed to delete role')
  }
}
</script>

<template>
  <div>
    <Toaster position="top-right" />
    <RoleTable @delete="handleDelete" />
  </div>
</template>
```

### Add Confirmation Dialog

```bash
npx shadcn-vue@latest add alert-dialog
```

```vue
<script setup lang="ts">
import { ref } from 'vue'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'

const showConfirm = ref(false)
const roleToDelete = ref(null)

const confirmDelete = (role) => {
  roleToDelete.value = role
  showConfirm.value = true
}

const handleConfirm = async () => {
  await deleteRole(roleToDelete.value.id)
  showConfirm.value = false
}
</script>

<template>
  <div>
    <RoleTable @delete="confirmDelete" />
    
    <AlertDialog :open="showConfirm" @update:open="showConfirm = $event">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Are you sure?</AlertDialogTitle>
          <AlertDialogDescription>
            This will delete the role "{{ roleToDelete?.name }}".
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancel</AlertDialogCancel>
          <AlertDialogAction @click="handleConfirm">
            Delete
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
```

### Customize Loading State

```vue
<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton'

const CustomLoader = () => (
  <div class="space-y-4">
    <Skeleton class="h-12 w-full" />
    <Skeleton class="h-12 w-full" />
    <Skeleton class="h-12 w-full" />
  </div>
)
</script>
```

## 🔍 Troubleshooting

### Issue: Components not found

**Solution**: Check your Vite alias configuration and ensure `@/` points to `resources/js/`.

### Issue: TypeScript errors

**Solution**: Run `npm run type-check` and install missing type definitions.

### Issue: Styles not loading

**Solution**: Ensure Tailwind is configured and `@/styles/globals.css` is imported in your main app file.

### Issue: API calls failing

**Solution**: Check CSRF token is present, API routes are correct, and CORS is configured.

### Issue: Dark mode not working

**Solution**: Ensure shadcn-vue's dark mode is configured in your layout.

## 🎓 Learn More

- [Full Documentation](./README.md)
- [Component API Reference](./README.md#components)
- [Composables Reference](./README.md#composables)
- [Type Definitions](./types/index.ts)

## 🆘 Need Help?

1. Check the [README](./README.md) for detailed docs
2. Review component source code for examples
3. Check Laravel Roles package documentation
4. Open an issue on GitHub

---

**You're ready to go!** 🚀

Start building your roles & permissions UI with beautiful, type-safe Vue components.

