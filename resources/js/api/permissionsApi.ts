/**
 * Permissions API Client
 *
 * Centralized API wrapper for permissions endpoints.
 * Never hardcodes URLs - uses config for prefix.
 */

import { apiUrl, getHeaders } from './config'
import type { Permission, PaginatedResponse, PermissionStats, ApiResponse, GroupedPermissions } from '../types'

export interface PermissionFilters {
    search?: string
    guard?: string
    group?: string
    status?: string
    sort?: string
    direction?: 'asc' | 'desc'
    with_trashed?: boolean
    only_trashed?: boolean
    page?: number
    per_page?: number
}

export interface CreatePermissionData {
    name: string
    label?: Record<string, string> | string
    description?: Record<string, string> | string
    group?: string
    guard_name?: string
}

export interface UpdatePermissionData extends Partial<CreatePermissionData> { }

class PermissionsApi {
    /**
     * Get paginated list of permissions
     */
    async list(filters: PermissionFilters = {}): Promise<PaginatedResponse<Permission>> {
        const params = new URLSearchParams()

        Object.entries(filters).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') {
                params.append(key, String(value))
            }
        })

        const url = apiUrl(`/permissions?${params.toString()}`)
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
     * Get permission statistics
     */
    async stats(): Promise<ApiResponse<PermissionStats>> {
        const response = await fetch(apiUrl('/permissions-stats'), {
            method: 'GET',
            headers: getHeaders(),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Get recent permissions
     */
    async recent(limit = 10): Promise<ApiResponse<Permission[]>> {
        const response = await fetch(apiUrl(`/permissions-recent?limit=${limit}`), {
            method: 'GET',
            headers: getHeaders(),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Get grouped permissions
     */
    async grouped(): Promise<ApiResponse<GroupedPermissions>> {
        const response = await fetch(apiUrl('/permission-groups'), {
            method: 'GET',
            headers: getHeaders(),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Get a single permission
     */
    async get(id: number): Promise<ApiResponse<Permission>> {
        const response = await fetch(apiUrl(`/permissions/${id}`), {
            method: 'GET',
            headers: getHeaders(),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Create a new permission
     */
    async create(data: CreatePermissionData): Promise<ApiResponse<Permission>> {
        const response = await fetch(apiUrl('/permissions'), {
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
     * Update a permission
     */
    async update(id: number, data: UpdatePermissionData): Promise<ApiResponse<Permission>> {
        const response = await fetch(apiUrl(`/permissions/${id}`), {
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
     * Delete a permission (soft delete)
     */
    async delete(id: number): Promise<ApiResponse<null>> {
        const response = await fetch(apiUrl(`/permissions/${id}`), {
            method: 'DELETE',
            headers: getHeaders(),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Restore a soft-deleted permission
     */
    async restore(id: number): Promise<ApiResponse<null>> {
        const response = await fetch(apiUrl(`/permissions/${id}/restore`), {
            method: 'POST',
            headers: getHeaders(),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Force delete a permission
     */
    async forceDelete(id: number): Promise<ApiResponse<null>> {
        const response = await fetch(apiUrl(`/permissions/${id}/force`), {
            method: 'DELETE',
            headers: getHeaders(),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Bulk delete permissions
     */
    async bulkDelete(ids: number[]): Promise<ApiResponse<{ success: number[]; failed: unknown[] }>> {
        const response = await fetch(apiUrl('/permissions/bulk-delete'), {
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
     * Change permission status
     */
    async changeStatus(id: number, status: 'active' | 'inactive' | 'deleted'): Promise<ApiResponse<Permission>> {
        const response = await fetch(apiUrl(`/permissions/${id}/status`), {
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

export const permissionsApi = new PermissionsApi()
export default permissionsApi
