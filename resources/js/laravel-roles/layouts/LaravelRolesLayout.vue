<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import LrToast from '@/laravel-roles/ui/LrToast.vue'

interface Props {
  title?: string
  description?: string
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Laravel Roles',
  description: 'Manage roles and permissions',
})

// Navigation items
const navItems = [
  { label: 'Roles', href: '/admin/acl/roles', icon: 'shield' },
  { label: 'Permissions', href: '/admin/acl/permissions', icon: 'key' },
  { label: 'Matrix', href: '/admin/acl/matrix', icon: 'grid' },
]

// Get current path
const currentPath = ref('')
onMounted(() => {
  currentPath.value = window.location.pathname
})

const isActive = (href: string) => {
  return currentPath.value.startsWith(href)
}

// Get prefix from config
const prefix = (window as any).laravelRoles?.uiPrefix || 'admin/acl'
</script>

<template>
  <div class="min-h-screen bg-background">
    <!-- Header -->
    <header class="sticky top-0 z-40 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
      <div class="container flex h-14 items-center">
        <div class="mr-4 flex">
          <a href="/" class="mr-6 flex items-center space-x-2">
            <svg class="h-6 w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <span class="hidden font-bold sm:inline-block">Laravel Roles</span>
          </a>
          <nav class="flex items-center space-x-6 text-sm font-medium">
            <a
              v-for="item in navItems"
              :key="item.href"
              :href="item.href"
              :class="[
                'transition-colors hover:text-foreground/80',
                isActive(item.href) ? 'text-foreground' : 'text-foreground/60'
              ]"
            >
              {{ item.label }}
            </a>
          </nav>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="container py-6">
      <!-- Page Header -->
      <div v-if="title" class="mb-6">
        <h1 class="text-3xl font-bold tracking-tight">{{ title }}</h1>
        <p v-if="description" class="text-muted-foreground">{{ description }}</p>
      </div>

      <!-- Page Content -->
      <slot />
    </main>

    <!-- Toast Container -->
    <LrToast />
  </div>
</template>

<style>
/* Ensure CSS variables are available */
:root {
  --background: 0 0% 100%;
  --foreground: 222.2 84% 4.9%;
  --card: 0 0% 100%;
  --card-foreground: 222.2 84% 4.9%;
  --popover: 0 0% 100%;
  --popover-foreground: 222.2 84% 4.9%;
  --primary: 222.2 47.4% 11.2%;
  --primary-foreground: 210 40% 98%;
  --secondary: 210 40% 96.1%;
  --secondary-foreground: 222.2 47.4% 11.2%;
  --muted: 210 40% 96.1%;
  --muted-foreground: 215.4 16.3% 46.9%;
  --accent: 210 40% 96.1%;
  --accent-foreground: 222.2 47.4% 11.2%;
  --destructive: 0 84.2% 60.2%;
  --destructive-foreground: 210 40% 98%;
  --border: 214.3 31.8% 91.4%;
  --input: 214.3 31.8% 91.4%;
  --ring: 222.2 84% 4.9%;
  --radius: 0.5rem;
}

.dark {
  --background: 222.2 84% 4.9%;
  --foreground: 210 40% 98%;
  --card: 222.2 84% 4.9%;
  --card-foreground: 210 40% 98%;
  --popover: 222.2 84% 4.9%;
  --popover-foreground: 210 40% 98%;
  --primary: 210 40% 98%;
  --primary-foreground: 222.2 47.4% 11.2%;
  --secondary: 217.2 32.6% 17.5%;
  --secondary-foreground: 210 40% 98%;
  --muted: 217.2 32.6% 17.5%;
  --muted-foreground: 215 20.2% 65.1%;
  --accent: 217.2 32.6% 17.5%;
  --accent-foreground: 210 40% 98%;
  --destructive: 0 62.8% 30.6%;
  --destructive-foreground: 210 40% 98%;
  --border: 217.2 32.6% 17.5%;
  --input: 217.2 32.6% 17.5%;
  --ring: 212.7 26.8% 83.9%;
}

.container {
  width: 100%;
  margin-left: auto;
  margin-right: auto;
  padding-left: 1rem;
  padding-right: 1rem;
}

@media (min-width: 640px) {
  .container {
    max-width: 640px;
  }
}

@media (min-width: 768px) {
  .container {
    max-width: 768px;
  }
}

@media (min-width: 1024px) {
  .container {
    max-width: 1024px;
  }
}

@media (min-width: 1280px) {
  .container {
    max-width: 1280px;
  }
}
</style>
