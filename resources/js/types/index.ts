export interface Role {
  id: number
  name: string
  display_name?: string
  description?: string
  guard_name: string
  permissions_count?: number
  users_count?: number
  permissions?: Permission[]
  created_at?: string
  updated_at?: string
  deleted_at?: string | null
}

export interface Permission {
  id: number
  name: string
  display_name?: string
  description?: string
  guard_name: string
  group?: string
  group_label?: string
  roles_count?: number
  created_at?: string
  updated_at?: string
  deleted_at?: string | null
}

export interface PermissionGroup {
  name: string
  label?: string
  permissions: Permission[]
}

export interface PaginationMeta {
  current_page: number
  from: number
  to: number
  per_page: number
  last_page: number
  total: number
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: PaginationMeta
  links: {
    first: string
    last: string
    prev: string | null
    next: string | null
  }
}

export interface RoleFilters {
  search?: string
  guard?: string
  tenant_id?: number
  with_trashed?: boolean
  only_trashed?: boolean
  sort?: string
  direction?: 'asc' | 'desc'
}

export interface PermissionFilters {
  search?: string
  group?: string
  guard?: string
  sort?: string
  direction?: 'asc' | 'desc'
}

export interface PermissionMatrix {
  roles: Role[]
  permissions: Permission[]
  matrix: Record<number, number[]> // roleId => permissionIds[]
}

export interface RoleStats {
  total: number
  by_guard: Record<string, number>
  with_permissions: number
  without_permissions: number
  active?: number
  trashed?: number
}

export interface PermissionStats {
  total: number
  by_guard: Record<string, number>
  by_group: Record<string, number>
  assigned_to_roles: number
  not_assigned: number
}

export type ViewMode = 'table' | 'grid'

