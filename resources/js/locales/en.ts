export default {
  roles: {
    // Roles section
    roles: {
      title: 'Roles',
      subtitle: 'Manage system roles and their permissions',
      empty: 'No roles found'
    },

    // Permissions section
    permissions: {
      title: 'Permissions',
      subtitle: 'Manage system permissions',
      empty: 'No permissions found',
      create: 'Create Permission',
      select_all: 'Select All',
      deselect_all: 'Deselect All',
      confirm: {
        delete: 'Are you sure you want to delete {name}?'
      }
    },

    // Matrix section
    matrix: {
      title: 'Permission Matrix',
      subtitle: 'Manage role permissions in a visual matrix',
      view: 'View Matrix',
      permission: 'Permission',
      search_permissions: 'Search permissions...',
      empty: 'No permissions or roles configured',
      empty_title: 'No Matrix Data',
      empty_description: 'Create some roles and permissions first to use the matrix view',
      no_results: 'No permissions match your search',
      info: {
        title: 'How to use the matrix',
        description: 'Check or uncheck boxes to assign or remove permissions from roles. Changes are saved immediately.'
      }
    },

    // Fields
    fields: {
      name: 'Name',
      display_name: 'Display Name',
      description: 'Description',
      guard: 'Guard',
      permissions: 'Permissions',
      users: 'Users',
      roles_count: 'Roles',
      group: 'Group',
      created_at: 'Created',
      updated_at: 'Updated'
    },

    // Placeholders
    placeholders: {
      name: 'e.g., editor',
      display_name: 'e.g., Content Editor',
      description: 'Brief description of this role...'
    },

    // Hints
    hints: {
      name: 'Lowercase, no spaces. Used internally.',
      guard_readonly: 'Guard cannot be changed after creation'
    },

    // Actions
    create: 'Create Role',
    edit: 'Edit Role',
    delete: 'Delete Role',
    clone: 'Clone Role',
    create_subtitle: 'Create a new role with permissions',
    edit_subtitle: 'Editing: {name}',

    // Confirmations
    confirm: {
      delete: 'Are you sure you want to delete {name}?'
    },

    // Filters
    filters: {
      select_guard: 'Select Guard',
      select_group: 'Select Group'
    },

    // Groups (for permissions)
    groups: {
      users: 'Users',
      roles: 'Roles',
      permissions: 'Permissions',
      posts: 'Posts',
      comments: 'Comments',
      settings: 'Settings'
    },

    // Stats
    stats: {
      total_roles: 'Total Roles',
      with_permissions: 'With Permissions',
      without_permissions: 'Without Permissions',
      active: 'Active Roles',
      total_permissions: 'Total Permissions',
      permission_groups: 'Permission Groups',
      assigned: 'Assigned',
      not_assigned: 'Not Assigned'
    },

    // Messages
    grid_view_coming_soon: 'Grid view coming soon'
  },

  // Common translations
  common: {
    // Actions
    actions: 'Actions',
    view: 'View',
    edit: 'Edit',
    delete: 'Delete',
    restore: 'Restore',
    clone: 'Clone',
    create: 'Create',
    update: 'Update',
    cancel: 'Cancel',
    save: 'Save',
    back: 'Back',
    search: 'Search',
    filter: 'Filter',

    // Status
    status: 'Status',
    active: 'Active',
    inactive: 'Inactive',
    deleted: 'Deleted',

    // Views
    table: 'Table',
    grid: 'Grid',

    // Pagination
    showing: 'Showing',
    of: 'of',
    results: 'results',
    previous: 'Previous',
    next: 'Next',

    // Misc
    all: 'All',
    none: 'None',
    loading: 'Loading...',
    error: 'Error',
    success: 'Success'
  }
}

