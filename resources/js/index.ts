// Types
export * from './types'

// Composables
export { useRolesApi } from './composables/useRolesApi'
export { usePermissionsApi } from './composables/usePermissionsApi'

// Components
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

