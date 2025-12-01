<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'
import { HomeIcon } from 'lucide-vue-next'
import { useRolesApi } from '@/composables/useRolesApi'
import { usePermissionsApi } from '@/composables/usePermissionsApi'
import RoleForm from '@/components/RoleForm.vue'

// Composables
const { createRole, isLoading: isSaving } = useRolesApi()
const { permissionGroups, fetchPermissionGroups, isLoading } = usePermissionsApi()

// Lifecycle
onMounted(async () => {
  await fetchPermissionGroups()
})

// Methods
const handleSubmit = async (payload: any) => {
  try {
    await createRole(payload)
    // TODO: Show success toast
    router.visit('/admin/acl/roles')
  } catch (error) {
    // TODO: Show error toast
    console.error('Failed to create role:', error)
  }
}

const handleCancel = () => {
  router.visit('/admin/acl/roles')
}
</script>

<template>
  <div class="container mx-auto py-6 space-y-6">
    <Head :title="$t('roles.create')" />

    <!-- Breadcrumbs -->
    <Breadcrumb>
      <BreadcrumbList>
        <BreadcrumbItem>
          <BreadcrumbLink href="/admin">
            <HomeIcon class="h-4 w-4" />
          </BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <BreadcrumbLink href="/admin/acl/roles">
            {{ $t('roles.roles.title') }}
          </BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <BreadcrumbPage>{{ $t('roles.create') }}</BreadcrumbPage>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- Page Header -->
    <div>
      <h1 class="text-3xl font-bold tracking-tight">
        {{ $t('roles.create') }}
      </h1>
      <p class="text-muted-foreground">
        {{ $t('roles.create_subtitle') }}
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-12">
      <div class="h-8 w-8 border-2 border-primary border-t-transparent rounded-full animate-spin" />
    </div>

    <!-- Form -->
    <RoleForm
      v-else
      :permission-groups="permissionGroups"
      :loading="isSaving"
      @submit="handleSubmit"
      @cancel="handleCancel"
    />
  </div>
</template>

