/**
 * Roles API Client
 *
 * Centralized API wrapper for roles endpoints.
 * Never hardcodes URLs - uses config for prefix.
 */

import { apiUrl, getHeaders } from './config'
import type { Role, PaginatedResponse, RoleStats, ApiResponse } from '../types'

export interface RoleFilters {
    search?: string
    guard?: string
    status?: string
    sort?: string
    direction?: 'asc' | 'desc'
    with_trashed?: boolean
    only_trashed?: boolean
    page?: number
    per_page?: number
}

export interface CreateRoleData {
    name: string
    label?: Record<string, string> | string
    description?: Record<string, string> | string
    guard_name?: string
    permission_ids?: number[]
}

export interface UpdateRoleData extends Partial<CreateRoleData> { }

class RolesApi {
    /**
     * Get paginated list of roles
     */
    async list(filters: RoleFilters = {}): Promise<PaginatedResponse<Role>> {
        const params = new URLSearchParams()

        Object.entries(filters).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') {
                params.append(key, String(value))
            }
        })

        const url = apiUrl(`/roles?${params.toString()}`)
        const response = await fetch(url, {
            method: 'GET',
            headers: getHeaders(),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Get role statistics
     */
    async stats(): Promise<ApiResponse<RoleStats>> {
        const response = await fetch(apiUrl('/roles-stats'), {
            method: 'GET',
            headers: getHeaders(),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Get recent roles
     */
    async recent(limit = 10): Promise<ApiResponse<Role[]>> {
        const response = await fetch(apiUrl(`/roles-recent?limit=${limit}`), {
            method: 'GET',
            headers: getHeaders(),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Get a single role
     */
    async get(id: number): Promise<ApiResponse<Role>> {
        const response = await fetch(apiUrl(`/roles/${id}`), {
            method: 'GET',
            headers: getHeaders(),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Get role with permissions
     */
    async getWithPermissions(id: number): Promise<ApiResponse<Role>> {
        const response = await fetch(apiUrl(`/roles/${id}/permissions`), {
            method: 'GET',
            headers: getHeaders(),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Create a new role
     */
    async create(data: CreateRoleData): Promise<ApiResponse<Role>> {
        const response = await fetch(apiUrl('/roles'), {
            method: 'POST',
            headers: getHeaders(),
            body: JSON.stringify(data),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Update a role
     */
    async update(id: number, data: UpdateRoleData): Promise<ApiResponse<Role>> {
        const response = await fetch(apiUrl(`/roles/${id}`), {
            method: 'PUT',
            headers: getHeaders(),
            body: JSON.stringify(data),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Delete a role (soft delete)
     */
    async delete(id: number): Promise<ApiResponse<null>> {
        const response = await fetch(apiUrl(`/roles/${id}`), {
            method: 'DELETE',
            headers: getHeaders(),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Restore a soft-deleted role
     */
    async restore(id: number): Promise<ApiResponse<null>> {
        const response = await fetch(apiUrl(`/roles/${id}/restore`), {
            method: 'POST',
            headers: getHeaders(),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Force delete a role
     */
    async forceDelete(id: number): Promise<ApiResponse<null>> {
        const response = await fetch(apiUrl(`/roles/${id}/force`), {
            method: 'DELETE',
            headers: getHeaders(),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Assign permissions to role (full sync)
     */
    async assignPermissions(roleId: number, permissionIds: number[]): Promise<ApiResponse<Role>> {
        const response = await fetch(apiUrl(`/roles/${roleId}/permissions`), {
            method: 'POST',
            headers: getHeaders(),
            body: JSON.stringify({ permission_ids: permissionIds }),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Diff-based permission sync (grant/revoke)
     */
    async diffPermissions(
        roleId: number,
        grant: string[] = [],
        revoke: string[] = []
    ): Promise<ApiResponse<{ result: { granted: string[]; revoked: string[]; skipped: unknown[] }; role: Role }>> {
        const response = await fetch(apiUrl(`/roles/${roleId}/permissions/diff`), {
            method: 'POST',
            headers: getHeaders(),
            body: JSON.stringify({ grant, revoke }),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Clone a role with its permissions
     */
    async clone(id: number, name: string, attributes: Record<string, unknown> = {}): Promise<ApiResponse<Role>> {
        const response = await fetch(apiUrl(`/roles/${id}/clone`), {
            method: 'POST',
            headers: getHeaders(),
            body: JSON.stringify({ name, ...attributes }),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Bulk delete roles
     */
    async bulkDelete(ids: number[]): Promise<ApiResponse<{ success: number[]; failed: unknown[] }>> {
        const response = await fetch(apiUrl('/roles/bulk-delete'), {
            method: 'POST',
            headers: getHeaders(),
            body: JSON.stringify({ ids }),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Bulk restore roles
     */
    async bulkRestore(ids: number[]): Promise<ApiResponse<{ success: number[]; failed: unknown[] }>> {
        const response = await fetch(apiUrl('/roles/bulk-restore'), {
            method: 'POST',
            headers: getHeaders(),
            body: JSON.stringify({ ids }),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Change role status
     */
    async changeStatus(id: number, status: 'active' | 'inactive' | 'deleted'): Promise<ApiResponse<Role>> {
        const response = await fetch(apiUrl(`/roles/${id}/status`), {
            method: 'PATCH',
            headers: getHeaders(),
            body: JSON.stringify({ status }),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Handle API errors
     */
    private async handleError(response: Response): Promise<Error> {
        let message = 'An error occurred'
        let errors: Record<string, string[]> = {}

        try {
            const data = await response.json()
            message = data.message || data.error || message
            errors = data.errors || {}
        } catch {
            message = response.statusText || message
        }

        const error = new Error(message) as Error & { status: number; errors: Record<string, string[]> }
        error.status = response.status
        error.errors = errors
        return error
    }
}

export const rolesApi = new RolesApi()
export default rolesApi
