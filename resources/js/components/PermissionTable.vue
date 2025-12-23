<script setup lang="ts">
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  EditIcon,
  TrashIcon,
  MoreVerticalIcon,
  EyeIcon,
  RotateCcwIcon
} from 'lucide-vue-next'
import type { Permission } from '@/laravel-roles/types'

defineProps<{
  permissions: Permission[]
  loading?: boolean
}>()

const emit = defineEmits<{
  view: [permission: Permission]
  edit: [permission: Permission]
  delete: [permission: Permission]
  restore: [permission: Permission]
}>()
</script>

<template>
  <div class="rounded-md border">
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>{{ $t('roles.fields.name') }}</TableHead>
          <TableHead>{{ $t('roles.fields.group') }}</TableHead>
          <TableHead>{{ $t('roles.fields.guard') }}</TableHead>
          <TableHead>{{ $t('roles.fields.roles_count') }}</TableHead>
          <TableHead>{{ $t('common.status') }}</TableHead>
          <TableHead class="text-right">{{ $t('common.actions') }}</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        <TableRow v-if="loading">
          <TableCell colspan="6" class="h-24 text-center">
            <div class="flex items-center justify-center">
              <div class="h-6 w-6 border-2 border-primary border-t-transparent rounded-full animate-spin" />
            </div>
          </TableCell>
        </TableRow>
        <TableRow v-else-if="permissions.length === 0">
          <TableCell colspan="6" class="h-24 text-center text-muted-foreground">
            {{ $t('roles.permissions.empty') }}
          </TableCell>
        </TableRow>
        <TableRow
          v-else
          v-for="permission in permissions"
          :key="permission.id"
          :class="{ 'opacity-50': permission.deleted_at }"
        >
          <TableCell class="font-medium">
            <div class="flex flex-col">
              <span>{{ permission.display_name || permission.name }}</span>
              <span class="text-xs text-muted-foreground">{{ permission.name }}</span>
            </div>
          </TableCell>
          <TableCell>
            <Badge v-if="permission.group" variant="secondary">
              {{ permission.group_label || permission.group }}
            </Badge>
            <span v-else class="text-muted-foreground text-sm">-</span>
          </TableCell>
          <TableCell>
            <Badge variant="outline">{{ permission.guard_name }}</Badge>
          </TableCell>
          <TableCell>
            <span class="text-muted-foreground">
              {{ permission.roles_count || 0 }}
            </span>
          </TableCell>
          <TableCell>
            <Badge v-if="permission.deleted_at" variant="destructive">
              {{ $t('common.deleted') }}
            </Badge>
            <Badge v-else variant="default">
              {{ $t('common.active') }}
            </Badge>
          </TableCell>
          <TableCell class="text-right">
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="ghost" size="icon">
                  <MoreVerticalIcon class="h-4 w-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem @click="emit('view', permission)">
                  <EyeIcon class="mr-2 h-4 w-4" />
                  {{ $t('common.view') }}
                </DropdownMenuItem>
                <DropdownMenuItem v-if="!permission.deleted_at" @click="emit('edit', permission)">
                  <EditIcon class="mr-2 h-4 w-4" />
                  {{ $t('common.edit') }}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  v-if="permission.deleted_at"
                  @click="emit('restore', permission)"
                  class="text-green-600 dark:text-green-400"
                >
                  <RotateCcwIcon class="mr-2 h-4 w-4" />
                  {{ $t('common.restore') }}
                </DropdownMenuItem>
                <DropdownMenuItem
                  v-else
                  @click="emit('delete', permission)"
                  class="text-destructive"
                >
                  <TrashIcon class="mr-2 h-4 w-4" />
                  {{ $t('common.delete') }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </TableCell>
        </TableRow>
      </TableBody>
    </Table>
  </div>
</template>

