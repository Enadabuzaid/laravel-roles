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
import type { Role } from '@/types'

// Props
interface Props {
  roleId: number
}

const props = defineProps<Props>()

// Composables
const { updateRole, isLoading: isSaving } = useRolesApi()
const { permissionGroups, fetchPermissionGroups, isLoading } = usePermissionsApi()

// State
const role = ref<Role | null>(null)

// Lifecycle
onMounted(async () => {
  await Promise.all([
    fetchPermissionGroups(),
    fetchRole()
  ])
})

// Methods
const fetchRole = async () => {
  try {
    const response = await fetch(`/api/roles/${props.roleId}`)
    const data = await response.json()
    role.value = data.data
  } catch (error) {
    console.error('Failed to fetch role:', error)
    // TODO: Show error toast and redirect
    router.visit('/admin/acl/roles')
  }
}

const handleSubmit = async (payload: any) => {
  try {
    await updateRole(props.roleId, payload)
    // TODO: Show success toast
    router.visit('/admin/acl/roles')
  } catch (error) {
    // TODO: Show error toast
    console.error('Failed to update role:', error)
  }
}

const handleCancel = () => {
  router.visit('/admin/acl/roles')
}
</script>

<template>
  <div class="container mx-auto py-6 space-y-6">
    <Head :title="$t('roles.edit')" />

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
          <BreadcrumbPage>{{ $t('roles.edit') }}</BreadcrumbPage>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- Page Header -->
    <div>
      <h1 class="text-3xl font-bold tracking-tight">
        {{ $t('roles.edit') }}
      </h1>
      <p v-if="role" class="text-muted-foreground">
        {{ $t('roles.edit_subtitle', { name: role.display_name || role.name }) }}
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading || !role" class="flex items-center justify-center py-12">
      <div class="h-8 w-8 border-2 border-primary border-t-transparent rounded-full animate-spin" />
    </div>

    <!-- Form -->
    <RoleForm
      v-else
      :role="role"
      :permission-groups="permissionGroups"
      :loading="isSaving"
      @submit="handleSubmit"
      @cancel="handleCancel"
    />
  </div>
</template>

