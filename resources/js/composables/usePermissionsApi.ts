/**
 * usePermissionsApi Composable
 *
 * Vue composable for permissions API operations.
 * Uses the centralized API client - no hardcoded URLs.
 */
import { ref, computed, type Ref } from 'vue'
import { permissionsApi, type PermissionFilters, type CreatePermissionData, type UpdatePermissionData } from '@/laravel-roles/api'
import { useToast } from './useToast'
import type { Permission, PermissionStats, PaginationMeta, GroupedPermissions } from '@/laravel-roles/types'

export function usePermissionsApi() {
  const toast = useToast()

  // State
  const permissions: Ref<Permission[]> = ref([])
  const currentPermission: Ref<Permission | null> = ref(null)
  const stats: Ref<PermissionStats | null> = ref(null)
  const grouped: Ref<GroupedPermissions | null> = ref(null)
  const isLoading = ref(false)
  const isSaving = ref(false)
  const error = ref<string | null>(null)

  const filters: Ref<PermissionFilters> = ref({
    search: '',
    guard: '',
    group: '',
    status: '',
    sort: 'id',
    direction: 'desc',
    page: 1,
    per_page: 20,
  })

  const meta = ref<PaginationMeta>({
    current_page: 1,
    from: 0,
    to: 0,
    per_page: 20,
    last_page: 1,
    total: 0,
  })

  // Computed
  const hasPermissions = computed(() => permissions.value.length > 0)
  const isEmpty = computed(() => !isLoading.value && !hasPermissions.value)

  /**
   * Fetch paginated permissions
   */
  async function fetchPermissions(page = 1): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await permissionsApi.list({
        ...filters.value,
        page,
      })

      permissions.value = response.data
      meta.value = response.meta
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to fetch permissions'
      toast.error('Error', error.value)
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Fetch permission statistics
   */
  async function fetchStats(): Promise<void> {
    try {
      const response = await permissionsApi.stats()
      stats.value = response.data
    } catch (err) {
      console.error('Failed to fetch stats:', err)
    }
  }

  /**
   * Fetch grouped permissions
   */
  async function fetchGrouped(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await permissionsApi.grouped()
      grouped.value = response.data
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to fetch grouped permissions'
      toast.error('Error', error.value)
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Fetch a single permission
   */
  async function fetchPermission(id: number): Promise<Permission | null> {
    isLoading.value = true
    error.value = null

    try {
      const response = await permissionsApi.get(id)
      currentPermission.value = response.data
      return response.data
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to fetch permission'
      toast.error('Error', error.value)
      return null
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Create a new permission
   */
  async function createPermission(data: CreatePermissionData): Promise<Permission | null> {
    isSaving.value = true
    error.value = null

    try {
      const response = await permissionsApi.create(data)
      toast.success('Success', 'Permission created successfully')
      return response.data
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to create permission'
      toast.error('Error', error.value)
      return null
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Update a permission
   */
  async function updatePermission(id: number, data: UpdatePermissionData): Promise<Permission | null> {
    isSaving.value = true
    error.value = null

    try {
      const response = await permissionsApi.update(id, data)
      toast.success('Success', 'Permission updated successfully')

      // Update current permission if it's the same
      if (currentPermission.value?.id === id) {
        currentPermission.value = response.data
      }

      return response.data
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to update permission'
      toast.error('Error', error.value)
      return null
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Delete a permission
   */
  async function deletePermission(id: number): Promise<boolean> {
    isSaving.value = true
    error.value = null

    try {
      await permissionsApi.delete(id)
      toast.success('Success', 'Permission deleted successfully')

      // Refresh list
      await fetchPermissions(meta.value.current_page)
      return true
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to delete permission'
      toast.error('Error', error.value)
      return false
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Restore a permission
   */
  async function restorePermission(id: number): Promise<boolean> {
    isSaving.value = true
    error.value = null

    try {
      await permissionsApi.restore(id)
      toast.success('Success', 'Permission restored successfully')

      // Refresh list
      await fetchPermissions(meta.value.current_page)
      return true
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to restore permission'
      toast.error('Error', error.value)
      return false
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Update filters and refetch
   */
  function updateFilters(newFilters: Partial<PermissionFilters>): void {
    filters.value = { ...filters.value, ...newFilters }
    fetchPermissions(1)
  }

  return {
    // State
    permissions,
    currentPermission,
    stats,
    grouped,
    meta,
    filters,
    isLoading,
    isSaving,
    error,

    // Computed
    hasPermissions,
    isEmpty,

    // Methods
    fetchPermissions,
    fetchStats,
    fetchGrouped,
    fetchPermission,
    createPermission,
    updatePermission,
    deletePermission,
    restorePermission,
    updateFilters,
  }
}

export default usePermissionsApi
