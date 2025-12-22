/**
 * Laravel Roles Package - Main Export
 *
 * All components, composables, and types are exported here.
 * This package is designed to be published into a Laravel project
 * that uses Vue 3 + Inertia.js + shadcn-vue.
 */

// Types
export * from './types'

// API Client Layer
export * from './api'

// Composables
export { useRolesApi } from './composables/useRolesApi'
export { usePermissionsApi } from './composables/usePermissionsApi'
export { useMatrixApi } from './composables/useMatrixApi'
export { useToast } from './composables/useToast'

// UI Components
export { default as PageHeader } from './components/ui/PageHeader.vue'
export { default as ConfirmDialog } from './components/ui/ConfirmDialog.vue'
export { default as SearchInput } from './components/ui/SearchInput.vue'
export { default as DataTableSkeleton } from './components/ui/DataTableSkeleton.vue'
export { default as EmptyState } from './components/ui/EmptyState.vue'

// Permission Matrix Components
export { default as PermissionToggleRow } from './components/PermissionToggleRow.vue'
export { default as PermissionGroupAccordion } from './components/PermissionGroupAccordion.vue'

// Legacy Components (for backward compatibility)
export { default as ViewToggle } from './components/ViewToggle.vue'
export { default as FiltersBar } from './components/FiltersBar.vue'
export { default as LocaleBadge } from './components/LocaleBadge.vue'
export { default as RoleStatsCards } from './components/RoleStatsCards.vue'
export { default as RoleTable } from './components/RoleTable.vue'
export { default as RoleGrid } from './components/RoleGrid.vue'
export { default as RoleForm } from './components/RoleForm.vue'
export { default as PermissionStatsCards } from './components/PermissionStatsCards.vue'
export { default as PermissionTable } from './components/PermissionTable.vue'
export { default as PermissionsMatrixTable } from './components/PermissionsMatrixTable.vue'

// Pages
export { default as RolesIndex } from './pages/RolesIndex.vue'
export { default as RoleCreate } from './pages/RoleCreate.vue'
export { default as RoleEdit } from './pages/RoleEdit.vue'
export { default as PermissionsIndex } from './pages/PermissionsIndex.vue'
export { default as PermissionMatrix } from './pages/PermissionMatrix.vue'

// Locales
export { default as enTranslations } from './locales/en'
