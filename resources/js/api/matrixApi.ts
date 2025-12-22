/**
 * Matrix API Client
 *
 * Centralized API wrapper for permission matrix endpoints.
 * Handles optimistic updates and rollback logic.
 */

import { apiUrl, getHeaders } from './config'
import type { ApiResponse, PermissionMatrix } from '../types'
import rolesApi from './rolesApi'

export interface MatrixToggleResult {
    success: boolean
    granted?: string[]
    revoked?: string[]
    error?: string
}

class MatrixApi {
    /**
     * Get the full permission matrix
     */
    async get(guard?: string): Promise<ApiResponse<PermissionMatrix>> {
        const url = guard ? apiUrl(`/matrix?guard=${guard}`) : apiUrl('/matrix')
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
     * Get grouped permission matrix
     */
    async getGrouped(): Promise<ApiResponse<PermissionMatrix>> {
        const response = await fetch(apiUrl('/matrix/grouped'), {
            method: 'GET',
            headers: getHeaders(),
        })

        if (!response.ok) {
            throw await this.handleError(response)
        }

        return response.json()
    }

    /**
     * Toggle a single permission for a role
     * Uses diff endpoint for atomic operation
     */
    async togglePermission(
        roleId: number,
        permissionName: string,
        value: boolean
    ): Promise<MatrixToggleResult> {
        try {
            const result = await rolesApi.diffPermissions(
                roleId,
                value ? [permissionName] : [],
                value ? [] : [permissionName]
            )

            return {
                success: true,
                granted: result.data.result.granted,
                revoked: result.data.result.revoked,
            }
        } catch (error) {
            return {
                success: false,
                error: error instanceof Error ? error.message : 'Failed to toggle permission',
            }
        }
    }

    /**
     * Toggle all permissions in a group for a role
     */
    async toggleGroup(
        roleId: number,
        permissionNames: string[],
        value: boolean
    ): Promise<MatrixToggleResult> {
        try {
            const result = await rolesApi.diffPermissions(
                roleId,
                value ? permissionNames : [],
                value ? [] : permissionNames
            )

            return {
                success: true,
                granted: result.data.result.granted,
                revoked: result.data.result.revoked,
            }
        } catch (error) {
            return {
                success: false,
                error: error instanceof Error ? error.message : 'Failed to toggle group',
            }
        }
    }

    /**
     * Grant all permissions in a group using wildcard
     */
    async grantGroup(roleId: number, groupName: string): Promise<MatrixToggleResult> {
        try {
            const result = await rolesApi.diffPermissions(roleId, [`${groupName}.*`], [])

            return {
                success: true,
                granted: result.data.result.granted,
            }
        } catch (error) {
            return {
                success: false,
                error: error instanceof Error ? error.message : 'Failed to grant group',
            }
        }
    }

    /**
     * Revoke all permissions in a group using wildcard
     */
    async revokeGroup(roleId: number, groupName: string): Promise<MatrixToggleResult> {
        try {
            const result = await rolesApi.diffPermissions(roleId, [], [`${groupName}.*`])

            return {
                success: true,
                revoked: result.data.result.revoked,
            }
        } catch (error) {
            return {
                success: false,
                error: error instanceof Error ? error.message : 'Failed to revoke group',
            }
        }
    }

    /**
     * Handle API errors
     */
    private async handleError(response: Response): Promise<Error> {
        let message = 'An error occurred'

        try {
            const data = await response.json()
            message = data.message || data.error || message
        } catch {
            message = response.statusText || message
        }

        const error = new Error(message) as Error & { status: number }
        error.status = response.status
        return error
    }
}

export const matrixApi = new MatrixApi()
export default matrixApi
