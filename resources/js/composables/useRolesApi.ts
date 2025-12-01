import { ref, computed, type Ref } from 'vue'
import { router } from '@inertiajs/vue3'
import type {
  Role,
  RoleFilters,
  PaginatedResponse,
  RoleStats
} from '@/types'

export function useRolesApi() {
  const roles: Ref<Role[]> = ref([])
  const stats: Ref<RoleStats | null> = ref(null)
  const isLoading = ref(false)
  const filters: Ref<RoleFilters> = ref({
    search: '',
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

  const fetchRoles = async (page = 1) => {
    isLoading.value = true

    try {
      const params = new URLSearchParams({
        page: page.toString(),
        per_page: meta.value.per_page.toString(),
        ...(filters.value.search && { search: filters.value.search }),
        ...(filters.value.guard && { guard: filters.value.guard }),
        ...(filters.value.sort && { sort: filters.value.sort }),
        ...(filters.value.direction && { direction: filters.value.direction }),
        ...(filters.value.with_trashed && { with_trashed: 'true' }),
        ...(filters.value.only_trashed && { only_trashed: 'true' })
      })

      const response = await fetch(`/api/roles?${params}`)
      const data: PaginatedResponse<Role> = await response.json()

      roles.value = data.data
      meta.value = data.meta
    } catch (error) {
      console.error('Error fetching roles:', error)
    } finally {
      isLoading.value = false
    }
  }

  const fetchStats = async () => {
    try {
      const response = await fetch('/api/roles-stats')
      stats.value = await response.json()
    } catch (error) {
      console.error('Error fetching role stats:', error)
    }
  }

  const createRole = async (data: Partial<Role> & { permission_ids?: number[] }) => {
    isLoading.value = true

    try {
      const response = await fetch('/api/roles', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify(data)
      })

      if (!response.ok) {
        throw new Error('Failed to create role')
      }

      const result = await response.json()
      return result.data
    } catch (error) {
      console.error('Error creating role:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const updateRole = async (id: number, data: Partial<Role> & { permission_ids?: number[] }) => {
    isLoading.value = true

    try {
      const response = await fetch(`/api/roles/${id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify(data)
      })

      if (!response.ok) {
        throw new Error('Failed to update role')
      }

      const result = await response.json()
      return result.data
    } catch (error) {
      console.error('Error updating role:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const deleteRole = async (id: number) => {
    isLoading.value = true

    try {
      const response = await fetch(`/api/roles/${id}`, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
      })

      if (!response.ok) {
        throw new Error('Failed to delete role')
      }

      await fetchRoles(meta.value.current_page)
    } catch (error) {
      console.error('Error deleting role:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const restoreRole = async (id: number) => {
    isLoading.value = true

    try {
      const response = await fetch(`/api/roles/${id}/restore`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
      })

      if (!response.ok) {
        throw new Error('Failed to restore role')
      }

      await fetchRoles(meta.value.current_page)
    } catch (error) {
      console.error('Error restoring role:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const updateFilters = (newFilters: Partial<RoleFilters>) => {
    filters.value = { ...filters.value, ...newFilters }
    fetchRoles(1) // Reset to page 1 when filters change
  }

  const hasRoles = computed(() => roles.value.length > 0)
  const isEmpty = computed(() => !isLoading.value && !hasRoles.value)

  return {
    // State
    roles,
    stats,
    meta,
    filters,
    isLoading,

    // Computed
    hasRoles,
    isEmpty,

    // Methods
    fetchRoles,
    fetchStats,
    createRole,
    updateRole,
    deleteRole,
    restoreRole,
    updateFilters
  }
}

