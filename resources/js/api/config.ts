/**
 * Laravel Roles API Configuration
 *
 * Centralized configuration for API endpoints.
 * Reads prefix from window.laravelRoles or defaults.
 */

export interface LaravelRolesConfig {
    apiPrefix: string
    uiPrefix: string
    csrfToken: string | null
}

/**
 * Get the Laravel Roles configuration from window or defaults
 */
export function getConfig(): LaravelRolesConfig {
    const windowConfig = (window as unknown as { laravelRoles?: Partial<LaravelRolesConfig> }).laravelRoles || {}

    return {
        apiPrefix: windowConfig.apiPrefix || '/admin/acl',
        uiPrefix: windowConfig.uiPrefix || '/admin/acl',
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || null,
    }
}

/**
 * Build a full API URL
 */
export function apiUrl(path: string): string {
    const config = getConfig()
    const cleanPath = path.startsWith('/') ? path : `/${path}`
    return `${config.apiPrefix}${cleanPath}`
}

/**
 * Build a full UI URL
 */
export function uiUrl(path: string): string {
    const config = getConfig()
    const cleanPath = path.startsWith('/') ? path : `/${path}`
    return `${config.uiPrefix}${cleanPath}`
}

/**
 * Get default headers for API requests
 */
export function getHeaders(customHeaders: Record<string, string> = {}): Record<string, string> {
    const config = getConfig()
    const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...customHeaders,
    }

    if (config.csrfToken) {
        headers['X-CSRF-TOKEN'] = config.csrfToken
    }

    return headers
}

export default {
    getConfig,
    apiUrl,
    uiUrl,
    getHeaders,
}
