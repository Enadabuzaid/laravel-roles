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
  CopyIcon,
  RotateCcwIcon
} from 'lucide-vue-next'
import LocaleBadge from './LocaleBadge.vue'
import type { Role } from '@/types'

defineProps<{
  roles: Role[]
  loading?: boolean
}>()

const emit = defineEmits<{
  view: [role: Role]
  edit: [role: Role]
  delete: [role: Role]
  restore: [role: Role]
  clone: [role: Role]
}>()
</script>

<template>
  <div class="rounded-md border">
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>{{ $t('roles.fields.name') }}</TableHead>
          <TableHead>{{ $t('roles.fields.guard') }}</TableHead>
          <TableHead>{{ $t('roles.fields.permissions') }}</TableHead>
          <TableHead>{{ $t('roles.fields.users') }}</TableHead>
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
        <TableRow v-else-if="roles.length === 0">
          <TableCell colspan="6" class="h-24 text-center text-muted-foreground">
            {{ $t('roles.empty') }}
          </TableCell>
        </TableRow>
        <TableRow v-else v-for="role in roles" :key="role.id" :class="{ 'opacity-50': role.deleted_at }">
          <TableCell class="font-medium">
            <div class="flex items-center gap-2">
              {{ role.display_name || role.name }}
              <LocaleBadge v-if="role.display_name" locale="en" />
            </div>
          </TableCell>
          <TableCell>
            <Badge variant="outline">{{ role.guard_name }}</Badge>
          </TableCell>
          <TableCell>
            <Badge variant="secondary">
              {{ role.permissions_count || 0 }} {{ $t('roles.permissions') }}
            </Badge>
          </TableCell>
          <TableCell>
            <span class="text-muted-foreground">
              {{ role.users_count || 0 }} {{ $t('roles.users') }}
            </span>
          </TableCell>
          <TableCell>
            <Badge v-if="role.deleted_at" variant="destructive">
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
                <DropdownMenuItem @click="emit('view', role)">
                  <EyeIcon class="mr-2 h-4 w-4" />
                  {{ $t('common.view') }}
                </DropdownMenuItem>
                <DropdownMenuItem v-if="!role.deleted_at" @click="emit('edit', role)">
                  <EditIcon class="mr-2 h-4 w-4" />
                  {{ $t('common.edit') }}
                </DropdownMenuItem>
                <DropdownMenuItem v-if="!role.deleted_at" @click="emit('clone', role)">
                  <CopyIcon class="mr-2 h-4 w-4" />
                  {{ $t('common.clone') }}
                </DropdownMenuItem>
                <DropdownMenuSeparator v-if="!role.deleted_at" />
                <DropdownMenuItem
                  v-if="role.deleted_at"
                  @click="emit('restore', role)"
                  class="text-green-600 dark:text-green-400"
                >
                  <RotateCcwIcon class="mr-2 h-4 w-4" />
                  {{ $t('common.restore') }}
                </DropdownMenuItem>
                <DropdownMenuItem
                  v-else
                  @click="emit('delete', role)"
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

