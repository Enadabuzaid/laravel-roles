/**
 * useRolesApi Composable
 *
 * Vue composable for roles API operations.
 * Uses the centralized API client - no hardcoded URLs.
 */
import { ref, computed, type Ref } from 'vue'
import { rolesApi, type RoleFilters, type CreateRoleData, type UpdateRoleData } from '@/api'
import { uiUrl } from '@/api/config'
import { useToast } from './useToast'
import type { Role, RoleStats, PaginationMeta } from '@/types'

export function useRolesApi() {
  const toast = useToast()

  // State
  const roles: Ref<Role[]> = ref([])
  const currentRole: Ref<Role | null> = ref(null)
  const stats: Ref<RoleStats | null> = ref(null)
  const isLoading = ref(false)
  const isSaving = ref(false)
  const error = ref<string | null>(null)

  const filters: Ref<RoleFilters> = ref({
    search: '',
    guard: '',
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
  const hasRoles = computed(() => roles.value.length > 0)
  const isEmpty = computed(() => !isLoading.value && !hasRoles.value)

  /**
   * Fetch paginated roles
   */
  async function fetchRoles(page = 1): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await rolesApi.list({
        ...filters.value,
        page,
      })

      roles.value = response.data
      meta.value = response.meta
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to fetch roles'
      toast.error('Error', error.value)
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Fetch role statistics
   */
  async function fetchStats(): Promise<void> {
    try {
      const response = await rolesApi.stats()
      stats.value = response.data
    } catch (err) {
      console.error('Failed to fetch stats:', err)
    }
  }

  /**
   * Fetch a single role
   */
  async function fetchRole(id: number): Promise<Role | null> {
    isLoading.value = true
    error.value = null

    try {
      const response = await rolesApi.get(id)
      currentRole.value = response.data
      return response.data
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to fetch role'
      toast.error('Error', error.value)
      return null
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Fetch role with permissions
   */
  async function fetchRoleWithPermissions(id: number): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await rolesApi.getWithPermissions(id)
      currentRole.value = response.data
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to fetch role'
      toast.error('Error', error.value)
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Create a new role
   */
  async function createRole(data: CreateRoleData): Promise<Role | null> {
    isSaving.value = true
    error.value = null

    try {
      const response = await rolesApi.create(data)
      toast.success('Success', 'Role created successfully')
      return response.data
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to create role'
      toast.error('Error', error.value)
      return null
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Update a role
   */
  async function updateRole(id: number, data: UpdateRoleData): Promise<Role | null> {
    isSaving.value = true
    error.value = null

    try {
      const response = await rolesApi.update(id, data)
      toast.success('Success', 'Role updated successfully')

      // Update current role if it's the same
      if (currentRole.value?.id === id) {
        currentRole.value = response.data
      }

      return response.data
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to update role'
      toast.error('Error', error.value)
      return null
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Delete a role
   */
  async function deleteRole(id: number): Promise<boolean> {
    isSaving.value = true
    error.value = null

    try {
      await rolesApi.delete(id)
      toast.success('Success', 'Role deleted successfully')

      // Refresh list
      await fetchRoles(meta.value.current_page)
      return true
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to delete role'
      toast.error('Error', error.value)
      return false
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Restore a role
   */
  async function restoreRole(id: number): Promise<boolean> {
    isSaving.value = true
    error.value = null

    try {
      await rolesApi.restore(id)
      toast.success('Success', 'Role restored successfully')

      // Refresh list
      await fetchRoles(meta.value.current_page)
      return true
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to restore role'
      toast.error('Error', error.value)
      return false
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Force delete a role
   */
  async function forceDeleteRole(id: number): Promise<boolean> {
    isSaving.value = true
    error.value = null

    try {
      await rolesApi.forceDelete(id)
      toast.success('Success', 'Role permanently deleted')

      // Refresh list
      await fetchRoles(meta.value.current_page)
      return true
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to delete role'
      toast.error('Error', error.value)
      return false
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Clone a role
   */
  async function cloneRole(id: number, name: string): Promise<Role | null> {
    isSaving.value = true
    error.value = null

    try {
      const response = await rolesApi.clone(id, name)
      toast.success('Success', 'Role cloned successfully')
      return response.data
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to clone role'
      toast.error('Error', error.value)
      return null
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Update filters and refetch
   */
  function updateFilters(newFilters: Partial<RoleFilters>): void {
    filters.value = { ...filters.value, ...newFilters }
    fetchRoles(1)
  }

  /**
   * Get UI route URLs (using config prefix)
   */
  function getRoutes() {
    return {
      index: uiUrl('/roles'),
      create: uiUrl('/roles/create'),
      show: (id: number) => uiUrl(`/roles/${id}`),
      edit: (id: number) => uiUrl(`/roles/${id}/edit`),
    }
  }

  return {
    // State
    roles,
    currentRole,
    stats,
    meta,
    filters,
    isLoading,
    isSaving,
    error,

    // Computed
    hasRoles,
    isEmpty,

    // Methods
    fetchRoles,
    fetchStats,
    fetchRole,
    fetchRoleWithPermissions,
    createRole,
    updateRole,
    deleteRole,
    restoreRole,
    forceDeleteRole,
    cloneRole,
    updateFilters,
    getRoutes,
  }
}

export default useRolesApi
