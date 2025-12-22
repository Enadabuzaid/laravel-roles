/**
 * Laravel Roles Package - TypeScript Types
 */

// Base Role type
export interface Role {
  id: number
  name: string
  label?: string | Record<string, string>
  display_name?: string
  description?: string | Record<string, string>
  guard_name: string
  status?: 'active' | 'inactive' | 'deleted'
  permissions_count?: number
  users_count?: number
  permissions?: Permission[]
  created_at?: string
  updated_at?: string
  deleted_at?: string | null
}

// Base Permission type
export interface Permission {
  id: number
  name: string
  label?: string | Record<string, string>
  display_name?: string
  description?: string | Record<string, string>
  guard_name: string
  group?: string
  group_label?: string | Record<string, string>
  status?: 'active' | 'inactive' | 'deleted'
  roles_count?: number
  created_at?: string
  updated_at?: string
  deleted_at?: string | null
}

// Permission group for grouped display
export interface PermissionGroup {
  name: string
  label?: string
  permissions: Permission[]
}

// Grouped permissions response
export interface GroupedPermissions {
  [groupName: string]: {
    label?: string
    permissions: Array<{
      id: number
      name: string
      label?: string
      description?: string
    }>
  }
}

// Pagination meta
export interface PaginationMeta {
  current_page: number
  from: number
  to: number
  per_page: number
  last_page: number
  total: number
}

// Paginated API response
export interface PaginatedResponse<T> {
  success?: boolean
  data: T[]
  meta: PaginationMeta
  links?: {
    first: string
    last: string
    prev: string | null
    next: string | null
  }
}

// Standard API response
export interface ApiResponse<T> {
  success: boolean
  data: T
  message?: string
}

// Role filters for list endpoint
export interface RoleFilters {
  search?: string
  guard?: string
  status?: string
  tenant_id?: number
  with_trashed?: boolean
  only_trashed?: boolean
  with_deleted?: boolean
  only_deleted?: boolean
  sort?: string
  direction?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

// Permission filters for list endpoint
export interface PermissionFilters {
  search?: string
  group?: string
  guard?: string
  status?: string
  sort?: string
  direction?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

// Permission Matrix types
export interface MatrixRole {
  id: number
  name: string
  label?: string
}

export interface MatrixPermission {
  id: number
  name: string
  label?: string
  group?: string
}

export interface MatrixRow {
  permission_id: number
  permission_name: string
  permission_label?: string
  permission_group?: string
  roles: Record<string, {
    role_id: number
    has_permission: boolean
  }>
}

export interface PermissionMatrix {
  roles: MatrixRole[]
  permissions: MatrixPermission[]
  matrix: MatrixRow[]
  groups?: Record<string, {
    label?: string
    permissions: Array<{
      id: number
      name: string
      label?: string
      roles: Record<string, {
        role_id: number
        has_permission: boolean
      }>
    }>
  }>
}

// Stats types
export interface RoleStats {
  total: number
  active: number
  inactive: number
  deleted: number
  with_permissions: number
  without_permissions: number
  by_status?: Record<string, number>
  by_guard?: Record<string, number>
  growth?: GrowthData
}

export interface PermissionStats {
  total: number
  active: number
  inactive: number
  deleted: number
  assigned: number
  unassigned: number
  by_group?: Record<string, number>
  by_status?: Record<string, number>
  growth?: GrowthData
}

export interface GrowthData {
  current: number
  previous: number
  difference: number
  percentage: number
  trend: 'up' | 'down' | 'stable'
}

// View mode toggle
export type ViewMode = 'table' | 'grid'

// Toast types
export type ToastType = 'success' | 'error' | 'warning' | 'info'

export interface Toast {
  id?: string
  type: ToastType
  title: string
  description?: string
  duration?: number
}

// Confirm dialog
export interface ConfirmDialogProps {
  open: boolean
  title: string
  description: string
  confirmLabel?: string
  cancelLabel?: string
  variant?: 'default' | 'destructive'
  onConfirm: () => void | Promise<void>
  onCancel?: () => void
}

// Form state
export interface FormState<T> {
  data: T
  errors: Record<keyof T, string | undefined>
  processing: boolean
  isDirty: boolean
}

// i18n label helper
export function resolveLabel(
  label: string | Record<string, string> | undefined | null,
  locale: string = 'en',
  fallback: string = 'en'
): string | undefined {
  if (!label) return undefined
  if (typeof label === 'string') return label
  return label[locale] ?? label[fallback] ?? Object.values(label)[0]
}
