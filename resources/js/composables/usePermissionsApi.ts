import { ref, computed, type Ref } from 'vue'
import type {
  Permission,
  PermissionFilters,
  PaginatedResponse,
  PermissionStats,
  PermissionMatrix,
  PermissionGroup,
  Role
} from '@/types'

export function usePermissionsApi() {
  const permissions: Ref<Permission[]> = ref([])
  const permissionGroups: Ref<PermissionGroup[]> = ref([])
  const stats: Ref<PermissionStats | null> = ref(null)
  const matrix: Ref<PermissionMatrix | null> = ref(null)
  const isLoading = ref(false)
  const filters: Ref<PermissionFilters> = ref({
    search: '',
    group: '',
    guard: '',
    sort: 'id',
    direction: 'desc'
  })

  const meta = ref({
    current_page: 1,
    from: 0,
    to: 0,
    per_page: 20,
    last_page: 1,
    total: 0
  })

  const fetchPermissions = async (page = 1) => {
    isLoading.value = true

    try {
      const params = new URLSearchParams({
        page: page.toString(),
        per_page: meta.value.per_page.toString(),
        ...(filters.value.search && { search: filters.value.search }),
        ...(filters.value.group && { group: filters.value.group }),
        ...(filters.value.guard && { guard: filters.value.guard }),
        ...(filters.value.sort && { sort: filters.value.sort }),
        ...(filters.value.direction && { direction: filters.value.direction })
      })

      const response = await fetch(`/api/permissions?${params}`)
      const data: PaginatedResponse<Permission> = await response.json()

      permissions.value = data.data
      meta.value = data.meta
    } catch (error) {
      console.error('Error fetching permissions:', error)
    } finally {
      isLoading.value = false
    }
  }

  const fetchPermissionGroups = async () => {
    isLoading.value = true

    try {
      const response = await fetch('/api/permission-groups')
      const data = await response.json()
      permissionGroups.value = Object.entries(data).map(([name, perms]) => ({
        name,
        label: name,
        permissions: perms as Permission[]
      }))
    } catch (error) {
      console.error('Error fetching permission groups:', error)
    } finally {
      isLoading.value = false
    }
  }

  const fetchStats = async () => {
    try {
      const response = await fetch('/api/permissions-stats')
      stats.value = await response.json()
    } catch (error) {
      console.error('Error fetching permission stats:', error)
    }
  }

  const fetchMatrix = async () => {
    isLoading.value = true

    try {
      const response = await fetch('/api/permissions-matrix')
      matrix.value = await response.json()
    } catch (error) {
      console.error('Error fetching permission matrix:', error)
    } finally {
      isLoading.value = false
    }
  }

  const togglePermission = async (roleId: number, permissionId: number, value: boolean) => {
    try {
      const response = await fetch('/api/roles/matrix', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({
          role_id: roleId,
          permission_id: permissionId,
          value
        })
      })

      if (!response.ok) {
        throw new Error('Failed to toggle permission')
      }

      // Update local matrix state
      if (matrix.value) {
        if (value) {
          if (!matrix.value.matrix[roleId]) {
            matrix.value.matrix[roleId] = []
          }
          if (!matrix.value.matrix[roleId].includes(permissionId)) {
            matrix.value.matrix[roleId].push(permissionId)
          }
        } else {
          if (matrix.value.matrix[roleId]) {
            matrix.value.matrix[roleId] = matrix.value.matrix[roleId].filter(
              id => id !== permissionId
            )
          }
        }
      }
    } catch (error) {
      console.error('Error toggling permission:', error)
      throw error
    }
  }

  const createPermission = async (data: Partial<Permission>) => {
    isLoading.value = true

    try {
      const response = await fetch('/api/permissions', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify(data)
      })

      if (!response.ok) {
        throw new Error('Failed to create permission')
      }

      const result = await response.json()
      return result.data
    } catch (error) {
      console.error('Error creating permission:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const updatePermission = async (id: number, data: Partial<Permission>) => {
    isLoading.value = true

    try {
      const response = await fetch(`/api/permissions/${id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify(data)
      })

      if (!response.ok) {
        throw new Error('Failed to update permission')
      }

      const result = await response.json()
      return result.data
    } catch (error) {
      console.error('Error updating permission:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const deletePermission = async (id: number) => {
    isLoading.value = true

    try {
      const response = await fetch(`/api/permissions/${id}`, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
      })

      if (!response.ok) {
        throw new Error('Failed to delete permission')
      }

      await fetchPermissions(meta.value.current_page)
    } catch (error) {
      console.error('Error deleting permission:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const updateFilters = (newFilters: Partial<PermissionFilters>) => {
    filters.value = { ...filters.value, ...newFilters }
    fetchPermissions(1) // Reset to page 1 when filters change
  }

  const hasPermissions = computed(() => permissions.value.length > 0)
  const isEmpty = computed(() => !isLoading.value && !hasPermissions.value)
  const groupedPermissions = computed(() => {
    const groups: Record<string, Permission[]> = {}
    permissions.value.forEach(permission => {
      const group = permission.group || 'other'
      if (!groups[group]) {
        groups[group] = []
      }
      groups[group].push(permission)
    })
    return groups
  })

  return {
    // State
    permissions,
    permissionGroups,
    stats,
    matrix,
    meta,
    filters,
    isLoading,

    // Computed
    hasPermissions,
    isEmpty,
    groupedPermissions,

    // Methods
    fetchPermissions,
    fetchPermissionGroups,
    fetchStats,
    fetchMatrix,
    togglePermission,
    createPermission,
    updatePermission,
    deletePermission,
    updateFilters
  }
}

