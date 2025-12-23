<script setup lang="ts">
/**
 * PermissionMatrix Page - MANDATORY
 *
 * Role × Permission matrix with toggle functionality.
 * Implements optimistic updates with rollback on failure.
 * Supports group toggle using diff endpoint.
 */
import { ref, computed, onMounted, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { Checkbox } from '@/components/ui/checkbox'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from '@/components/ui/accordion'
import {
  ArrowLeftIcon,
  RefreshCwIcon,
  Loader2,
  ShieldIcon,
  KeyIcon,
  CheckCircle2,
  XCircle,
  AlertCircleIcon,
} from 'lucide-vue-next'

// Laravel Roles custom components
import PageHeader from '@/laravel-roles/components/ui/PageHeader.vue'
import DataTableSkeleton from '@/laravel-roles/components/ui/DataTableSkeleton.vue'
import EmptyState from '@/laravel-roles/components/ui/EmptyState.vue'

// Laravel Roles API layer
import { useMatrixApi } from '@/laravel-roles/composables/useMatrixApi'
import { uiUrl } from '@/laravel-roles/api/config'
import type { MatrixRole, MatrixPermission } from '@/laravel-roles/types'

// Props
const props = defineProps<{
  guard?: string
}>()

// Composable
const {
  matrix,
  roles,
  permissions,
  isLoading,
  isSaving,
  hasMatrix,
  fetchMatrix,
  togglePermission,
  grantGroup,
  revokeGroup,
  getGroupedPermissions,
} = useMatrixApi()

// Selected role for focused view
const selectedRoleId = ref<number | null>(null)
const activeGroups = ref<string[]>([])

// Computed
const selectedRole = computed(() => {
  if (!selectedRoleId.value) return null
  return roles.value.find(r => r.id === selectedRoleId.value) || null
})

const groupedPermissions = computed(() => getGroupedPermissions())

const groupNames = computed(() => Object.keys(groupedPermissions.value).sort())

// Permission check for current selection
const hasPermissionForRole = (roleId: number, permissionId: number): boolean => {
  if (!matrix.value) return false

  const row = matrix.value.matrix.find(r => r.permission_id === permissionId)
  if (!row) return false

  const role = roles.value.find(r => r.id === roleId)
  if (!role) return false

  const roleData = row.roles[role.name]
  return roleData?.has_permission || false
}

// Count permissions in group for a role
const getGroupPermissionCount = (roleId: number, groupName: string): { assigned: number; total: number } => {
  const perms = groupedPermissions.value[groupName] || []
  const assigned = perms.filter(p => hasPermissionForRole(roleId, p.id)).length
  return { assigned, total: perms.length }
}

// Is all permissions in group assigned?
const isGroupFullyAssigned = (roleId: number, groupName: string): boolean => {
  const { assigned, total } = getGroupPermissionCount(roleId, groupName)
  return total > 0 && assigned === total
}

// Is some permissions in group assigned?
const isGroupPartiallyAssigned = (roleId: number, groupName: string): boolean => {
  const { assigned, total } = getGroupPermissionCount(roleId, groupName)
  return assigned > 0 && assigned < total
}

// Lifecycle
onMounted(async () => {
  await fetchMatrix(props.guard)

  // Select first role by default
  if (roles.value.length > 0) {
    selectedRoleId.value = roles.value[0].id
  }

  // Open all groups by default
  activeGroups.value = groupNames.value
})

// Watch for role changes
watch(selectedRoleId, () => {
  // Reset groups when role changes
  activeGroups.value = groupNames.value
})

// Toggle permission handler
const handleToggle = async (permissionId: number, permissionName: string, value: boolean) => {
  if (!selectedRoleId.value) return

  await togglePermission(
    selectedRoleId.value,
    permissionId,
    permissionName,
    value
  )
}

// Toggle group handler
const handleGroupToggle = async (groupName: string, value: boolean) => {
  if (!selectedRoleId.value) return

  if (value) {
    await grantGroup(selectedRoleId.value, groupName)
  } else {
    await revokeGroup(selectedRoleId.value, groupName)
  }
}

// Refresh matrix
const handleRefresh = async () => {
  await fetchMatrix(props.guard)
}

// Navigation
const navigateBack = () => {
  router.visit(uiUrl('/roles'))
}

// Breadcrumbs
const breadcrumbs = [
  { label: 'Access Control' },
  { label: 'Roles', href: uiUrl('/roles') },
  { label: 'Permission Matrix' },
]
</script>

<template>
  <div class="container mx-auto py-6 space-y-6">
    <Head title="Permission Matrix" />

    <!-- Page Header -->
    <PageHeader
      title="Permission Matrix"
      description="Manage role permissions with toggle controls"
      :breadcrumbs="breadcrumbs"
    >
      <template #actions>
        <Button variant="outline" size="sm" @click="handleRefresh" :disabled="isLoading">
          <RefreshCwIcon class="mr-2 h-4 w-4" :class="{ 'animate-spin': isLoading }" />
          Refresh
        </Button>
        <Button variant="outline" @click="navigateBack">
          <ArrowLeftIcon class="mr-2 h-4 w-4" />
          Back
        </Button>
      </template>
    </PageHeader>

    <!-- Info Card -->
    <Card class="bg-muted/50">
      <CardContent class="flex items-start gap-3 pt-4">
        <div class="rounded-full bg-primary/10 p-2">
          <AlertCircleIcon class="h-4 w-4 text-primary" />
        </div>
        <div class="space-y-1">
          <p class="text-sm font-medium">How to use</p>
          <p class="text-sm text-muted-foreground">
            Select a role from the tabs below, then toggle permissions on/off.
            Use the group checkbox to grant or revoke all permissions in a group at once.
          </p>
        </div>
      </CardContent>
    </Card>

    <!-- Loading State -->
    <DataTableSkeleton v-if="isLoading && !hasMatrix" :columns="4" :rows="8" />

    <!-- Empty State -->
    <EmptyState
      v-else-if="!hasMatrix"
      title="No data available"
      description="No roles or permissions found. Create some roles first."
      action-label="Create Role"
      @action="navigateBack"
    >
      <template #icon>
        <ShieldIcon class="h-8 w-8 text-muted-foreground" />
      </template>
    </EmptyState>

    <!-- Matrix Content -->
    <template v-else>
      <!-- Role Tabs -->
      <Tabs
        :model-value="String(selectedRoleId)"
        @update:model-value="val => selectedRoleId = Number(val)"
        class="space-y-6"
      >
        <div class="overflow-x-auto">
          <TabsList class="inline-flex h-auto p-1">
            <TabsTrigger
              v-for="role in roles"
              :key="role.id"
              :value="String(role.id)"
              class="whitespace-nowrap px-4 py-2"
            >
              <ShieldIcon class="mr-2 h-4 w-4" />
              {{ role.label || role.name }}
            </TabsTrigger>
          </TabsList>
        </div>

        <!-- Role Content -->
        <TabsContent
          v-for="role in roles"
          :key="role.id"
          :value="String(role.id)"
        >
          <Card>
            <CardHeader>
              <div class="flex items-center justify-between">
                <div>
                  <CardTitle class="flex items-center gap-2">
                    <ShieldIcon class="h-5 w-5" />
                    {{ role.label || role.name }}
                  </CardTitle>
                  <CardDescription>
                    Toggle permissions for this role
                  </CardDescription>
                </div>
                <Badge v-if="isSaving" variant="secondary">
                  <Loader2 class="mr-1 h-3 w-3 animate-spin" />
                  Saving...
                </Badge>
              </div>
            </CardHeader>
            <CardContent>
              <!-- Permission Groups -->
              <Accordion
                type="multiple"
                v-model="activeGroups"
                class="space-y-2"
              >
                <AccordionItem
                  v-for="groupName in groupNames"
                  :key="groupName"
                  :value="groupName"
                  class="border rounded-lg px-4"
                >
                  <div class="flex items-center gap-3">
                    <!-- Group Toggle Checkbox -->
                    <div class="flex items-center" @click.stop>
                      <Checkbox
                        :checked="isGroupFullyAssigned(role.id, groupName)"
                        :indeterminate="isGroupPartiallyAssigned(role.id, groupName)"
                        @update:checked="(val: boolean) => handleGroupToggle(groupName, val)"
                        :disabled="isSaving"
                      />
                    </div>

                    <AccordionTrigger class="flex-1 hover:no-underline py-4">
                      <div class="flex items-center gap-3">
                        <KeyIcon class="h-4 w-4 text-muted-foreground" />
                        <span class="font-medium capitalize">{{ groupName }}</span>
                        <Badge variant="secondary" class="text-xs">
                          {{ getGroupPermissionCount(role.id, groupName).assigned }}
                          /
                          {{ getGroupPermissionCount(role.id, groupName).total }}
                        </Badge>
                        <CheckCircle2
                          v-if="isGroupFullyAssigned(role.id, groupName)"
                          class="h-4 w-4 text-green-500"
                        />
                      </div>
                    </AccordionTrigger>
                  </div>

                  <AccordionContent>
                    <div class="space-y-1 pl-7 pb-4">
                      <div
                        v-for="permission in groupedPermissions[groupName]"
                        :key="permission.id"
                        class="flex items-center justify-between py-2 px-3 hover:bg-muted/50 rounded-md transition-colors"
                      >
                        <div class="flex-1 min-w-0">
                          <span class="text-sm">
                            {{ permission.label || permission.name }}
                          </span>
                        </div>
                        <Switch
                          :checked="hasPermissionForRole(role.id, permission.id)"
                          @update:checked="(val: boolean) => handleToggle(permission.id, permission.name, val)"
                          :disabled="isSaving"
                          class="data-[state=checked]:bg-primary"
                        />
                      </div>
                    </div>
                  </AccordionContent>
                </AccordionItem>
              </Accordion>

              <!-- No groups -->
              <div
                v-if="groupNames.length === 0"
                class="text-center py-8 text-muted-foreground"
              >
                No permission groups found
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </template>
  </div>
</template>
