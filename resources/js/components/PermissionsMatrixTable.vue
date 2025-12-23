<script setup lang="ts">
import { ref, computed } from 'vue'
import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { SearchIcon } from 'lucide-vue-next'
import type { PermissionMatrix } from '@/laravel-roles/types'

const props = defineProps<{
  matrix: PermissionMatrix
  loading?: boolean
}>()

const emit = defineEmits<{
  toggle: [payload: { roleId: number; permissionId: number; value: boolean }]
}>()

const searchQuery = ref('')

// Group permissions by their group
const groupedPermissions = computed(() => {
  const groups: Record<string, typeof props.matrix.permissions> = {}

  props.matrix.permissions.forEach(permission => {
    const group = permission.group || 'other'
    if (!groups[group]) {
      groups[group] = []
    }

    // Filter by search
    if (searchQuery.value) {
      const query = searchQuery.value.toLowerCase()
      if (
        permission.name.toLowerCase().includes(query) ||
        permission.display_name?.toLowerCase().includes(query)
      ) {
        groups[group].push(permission)
      }
    } else {
      groups[group].push(permission)
    }
  })

  return groups
})

const isChecked = (roleId: number, permissionId: number): boolean => {
  return props.matrix.matrix[roleId]?.includes(permissionId) || false
}

const handleToggle = (roleId: number, permissionId: number, checked: boolean) => {
  emit('toggle', { roleId, permissionId, value: checked })
}
</script>

<template>
  <div class="space-y-4">
    <!-- Search Filter -->
    <div class="relative max-w-sm">
      <SearchIcon class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
      <Input
        v-model="searchQuery"
        :placeholder="$t('roles.matrix.search_permissions')"
        class="pl-9"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="h-8 w-8 border-2 border-primary border-t-transparent rounded-full animate-spin" />
    </div>

    <!-- Matrix Table -->
    <div v-else class="border rounded-lg overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full border-collapse">
          <!-- Header with Roles -->
          <thead class="bg-muted/50 sticky top-0 z-10">
            <tr>
              <th class="sticky left-0 z-20 bg-muted/50 border-r border-b px-4 py-3 text-left font-medium text-sm">
                {{ $t('roles.matrix.permission') }}
              </th>
              <th
                v-for="role in matrix.roles"
                :key="role.id"
                class="border-b px-4 py-3 text-center font-medium text-sm min-w-[120px]"
              >
                <div class="flex flex-col items-center gap-1">
                  <span>{{ role.display_name || role.name }}</span>
                  <Badge variant="outline" class="text-xs">
                    {{ role.guard_name }}
                  </Badge>
                </div>
              </th>
            </tr>
          </thead>

          <!-- Body with Permissions grouped -->
          <tbody>
            <template v-for="(permissions, groupName) in groupedPermissions" :key="groupName">
              <!-- Group Header -->
              <tr class="bg-muted/30">
                <td
                  :colspan="matrix.roles.length + 1"
                  class="px-4 py-2 font-semibold text-sm"
                >
                  {{ groupName }}
                </td>
              </tr>

              <!-- Permissions in this group -->
              <tr
                v-for="permission in permissions"
                :key="permission.id"
                class="hover:bg-muted/20 transition-colors"
              >
                <td class="sticky left-0 z-10 bg-card border-r px-4 py-3 text-sm">
                  <div class="flex flex-col">
                    <span class="font-medium">
                      {{ permission.display_name || permission.name }}
                    </span>
                    <span class="text-xs text-muted-foreground">
                      {{ permission.name }}
                    </span>
                  </div>
                </td>

                <td
                  v-for="role in matrix.roles"
                  :key="`${role.id}-${permission.id}`"
                  class="border-l px-4 py-3 text-center"
                >
                  <div class="flex items-center justify-center">
                    <Checkbox
                      :checked="isChecked(role.id, permission.id)"
                      @update:checked="handleToggle(role.id, permission.id, $event as boolean)"
                    />
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-if="!loading && Object.keys(groupedPermissions).length === 0"
      class="text-center py-12 text-muted-foreground"
    >
      {{ searchQuery ? $t('roles.matrix.no_results') : $t('roles.matrix.empty') }}
    </div>
  </div>
</template>

<style scoped>
/* Ensure sticky positioning works properly */
table {
  position: relative;
}

.sticky {
  position: sticky;
  background-color: inherit;
}
</style>

