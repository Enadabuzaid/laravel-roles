<script setup lang="ts">
import { ref, watch } from 'vue'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { SearchIcon, XIcon } from 'lucide-vue-next'
import type { RoleFilters, PermissionFilters } from '@/types'

interface Props {
  type?: 'roles' | 'permissions'
  modelValue?: RoleFilters | PermissionFilters
}

const props = withDefaults(defineProps<Props>(), {
  type: 'roles'
})

const emit = defineEmits<{
  'update:modelValue': [filters: RoleFilters | PermissionFilters]
}>()

const search = ref(props.modelValue?.search || '')
const guard = ref(props.modelValue?.guard || '')
const group = ref((props.modelValue as PermissionFilters)?.group || '')

const guards = [
  { value: 'web', label: 'Web' },
  { value: 'api', label: 'API' },
  { value: 'admin', label: 'Admin' }
]

// Debounce search
let searchTimeout: number | undefined
watch(search, (newValue) => {
  clearTimeout(searchTimeout)
  searchTimeout = window.setTimeout(() => {
    emitFilters()
  }, 300)
})

watch([guard, group], () => {
  emitFilters()
})

const emitFilters = () => {
  const filters: any = {
    search: search.value,
    guard: guard.value
  }

  if (props.type === 'permissions') {
    filters.group = group.value
  }

  emit('update:modelValue', filters)
}

const clearFilters = () => {
  search.value = ''
  guard.value = ''
  group.value = ''
  emitFilters()
}

const hasActiveFilters = () => {
  return Boolean(search.value || guard.value || group.value)
}
</script>

<template>
  <div class="flex flex-col gap-4 p-4 border rounded-lg bg-card">
    <div class="flex flex-col sm:flex-row gap-3">
      <!-- Search Input -->
      <div class="flex-1 relative">
        <SearchIcon class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
        <Input
          v-model="search"
          :placeholder="$t('common.search')"
          class="pl-9"
        />
      </div>

      <!-- Guard Select -->
      <Select v-model="guard">
        <SelectTrigger class="w-full sm:w-[180px]">
          <SelectValue :placeholder="$t('roles.filters.select_guard')" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="">{{ $t('common.all') }}</SelectItem>
          <SelectItem
            v-for="guardOption in guards"
            :key="guardOption.value"
            :value="guardOption.value"
          >
            {{ guardOption.label }}
          </SelectItem>
        </SelectContent>
      </Select>

      <!-- Group Select (Permissions only) -->
      <Select v-if="type === 'permissions'" v-model="group">
        <SelectTrigger class="w-full sm:w-[180px]">
          <SelectValue :placeholder="$t('roles.filters.select_group')" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="">{{ $t('common.all') }}</SelectItem>
          <!-- TODO: Load groups dynamically from API -->
          <SelectItem value="users">{{ $t('roles.groups.users') }}</SelectItem>
          <SelectItem value="roles">{{ $t('roles.groups.roles') }}</SelectItem>
          <SelectItem value="permissions">{{ $t('roles.groups.permissions') }}</SelectItem>
        </SelectContent>
      </Select>

      <!-- Clear Filters Button -->
      <Button
        v-if="hasActiveFilters()"
        variant="ghost"
        size="icon"
        @click="clearFilters"
        class="shrink-0"
      >
        <XIcon class="h-4 w-4" />
      </Button>
    </div>
  </div>
</template>

