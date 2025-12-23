/**
 * useMatrixApi Composable
 *
 * Vue composable for permission matrix operations.
 * Handles optimistic updates with rollback on failure.
 */
import { ref, computed, type Ref } from 'vue'
import { matrixApi } from '@/laravel-roles/api'
import { useToast } from './useToast'
import type { PermissionMatrix, MatrixRole, MatrixPermission, MatrixRow } from '@/laravel-roles/types'

// Internal state for optimistic updates
interface OptimisticState {
    roleId: number
    permissionName: string
    previousValue: boolean
}

export function useMatrixApi() {
    const toast = useToast()

    // State
    const matrix: Ref<PermissionMatrix | null> = ref(null)
    const isLoading = ref(false)
    const isSaving = ref(false)
    const error = ref<string | null>(null)

    // Track in-flight operations for optimistic updates
    const pendingOperations: Ref<Map<string, OptimisticState>> = ref(new Map())

    // Computed
    const roles = computed<MatrixRole[]>(() => matrix.value?.roles || [])
    const permissions = computed<MatrixPermission[]>(() => matrix.value?.permissions || [])
    const hasMatrix = computed(() => matrix.value !== null)

    /**
     * Fetch the permission matrix
     */
    async function fetchMatrix(guard?: string): Promise<void> {
        isLoading.value = true
        error.value = null

        try {
            const response = await matrixApi.get(guard)
            matrix.value = response.data
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Failed to fetch matrix'
            toast.error('Error', error.value)
        } finally {
            isLoading.value = false
        }
    }

    /**
     * Fetch grouped matrix
     */
    async function fetchGroupedMatrix(): Promise<void> {
        isLoading.value = true
        error.value = null

        try {
            const response = await matrixApi.getGrouped()
            matrix.value = response.data
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Failed to fetch matrix'
            toast.error('Error', error.value)
        } finally {
            isLoading.value = false
        }
    }

    /**
     * Check if a role has a permission
     */
    function hasPermission(roleId: number, permissionId: number): boolean {
        if (!matrix.value) return false

        const row = matrix.value.matrix.find(r => r.permission_id === permissionId)
        if (!row) return false

        const roleData = Object.values(row.roles).find(r => r.role_id === roleId)
        return roleData?.has_permission || false
    }

    /**
     * Toggle a single permission with optimistic update
     */
    async function togglePermission(
        roleId: number,
        permissionId: number,
        permissionName: string,
        value: boolean
    ): Promise<boolean> {
        const operationKey = `${roleId}-${permissionName}`
        const previousValue = hasPermission(roleId, permissionId)

        // Store previous state for rollback
        pendingOperations.value.set(operationKey, {
            roleId,
            permissionName,
            previousValue,
        })

        // Optimistic update
        updateMatrixValue(roleId, permissionId, value)
        isSaving.value = true

        try {
            const result = await matrixApi.togglePermission(roleId, permissionName, value)

            if (!result.success) {
                // Rollback on failure
                updateMatrixValue(roleId, permissionId, previousValue)
                toast.error('Error', result.error || 'Failed to update permission')
                return false
            }

            toast.success('Success', value ? 'Permission granted' : 'Permission revoked')
            return true
        } catch (err) {
            // Rollback on error
            updateMatrixValue(roleId, permissionId, previousValue)
            toast.error('Error', 'Failed to update permission')
            return false
        } finally {
            pendingOperations.value.delete(operationKey)
            isSaving.value = pendingOperations.value.size > 0
        }
    }

    /**
     * Toggle all permissions in a group
     */
    async function toggleGroup(
        roleId: number,
        groupName: string,
        permissionNames: string[],
        value: boolean
    ): Promise<boolean> {
        isSaving.value = true

        try {
            const result = await matrixApi.toggleGroup(roleId, permissionNames, value)

            if (!result.success) {
                toast.error('Error', result.error || 'Failed to update group')
                return false
            }

            // Refresh matrix to get updated state
            await fetchMatrix()
            toast.success('Success', value ? 'Group permissions granted' : 'Group permissions revoked')
            return true
        } catch (err) {
            toast.error('Error', 'Failed to update group')
            return false
        } finally {
            isSaving.value = false
        }
    }

    /**
     * Grant all permissions in a group using wildcard
     */
    async function grantGroup(roleId: number, groupName: string): Promise<boolean> {
        isSaving.value = true

        try {
            const result = await matrixApi.grantGroup(roleId, groupName)

            if (!result.success) {
                toast.error('Error', result.error || 'Failed to grant group')
                return false
            }

            await fetchMatrix()
            toast.success('Success', `All ${groupName} permissions granted`)
            return true
        } catch (err) {
            toast.error('Error', 'Failed to grant group')
            return false
        } finally {
            isSaving.value = false
        }
    }

    /**
     * Revoke all permissions in a group using wildcard
     */
    async function revokeGroup(roleId: number, groupName: string): Promise<boolean> {
        isSaving.value = true

        try {
            const result = await matrixApi.revokeGroup(roleId, groupName)

            if (!result.success) {
                toast.error('Error', result.error || 'Failed to revoke group')
                return false
            }

            await fetchMatrix()
            toast.success('Success', `All ${groupName} permissions revoked`)
            return true
        } catch (err) {
            toast.error('Error', 'Failed to revoke group')
            return false
        } finally {
            isSaving.value = false
        }
    }

    /**
     * Update matrix value locally (for optimistic updates)
     */
    function updateMatrixValue(roleId: number, permissionId: number, value: boolean): void {
        if (!matrix.value) return

        const rowIndex = matrix.value.matrix.findIndex(r => r.permission_id === permissionId)
        if (rowIndex === -1) return

        const row = matrix.value.matrix[rowIndex]
        const role = roles.value.find(r => r.id === roleId)
        if (!role) return

        const roleKey = role.name
        if (row.roles[roleKey]) {
            row.roles[roleKey].has_permission = value
        }
    }

    /**
     * Get permissions grouped by group name
     */
    function getGroupedPermissions(): Record<string, MatrixPermission[]> {
        if (!matrix.value) return {}

        const groups: Record<string, MatrixPermission[]> = {}

        for (const perm of matrix.value.permissions) {
            const group = perm.group || 'ungrouped'
            if (!groups[group]) {
                groups[group] = []
            }
            groups[group].push(perm)
        }

        return groups
    }

    return {
        // State
        matrix,
        isLoading,
        isSaving,
        error,

        // Computed
        roles,
        permissions,
        hasMatrix,

        // Methods
        fetchMatrix,
        fetchGroupedMatrix,
        hasPermission,
        togglePermission,
        toggleGroup,
        grantGroup,
        revokeGroup,
        getGroupedPermissions,
    }
}

export default useMatrixApi
