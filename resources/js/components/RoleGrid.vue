<script setup lang="ts">
import { Card, CardContent, CardFooter, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  EditIcon,
  TrashIcon,
  EyeIcon,
  CopyIcon,
  RotateCcwIcon,
  ShieldIcon
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
  <div v-if="loading" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
    <Card v-for="i in 6" :key="i" class="animate-pulse">
      <CardHeader>
        <div class="h-6 w-32 bg-muted rounded" />
        <div class="h-4 w-full bg-muted rounded mt-2" />
      </CardHeader>
      <CardContent>
        <div class="h-20 bg-muted rounded" />
      </CardContent>
    </Card>
  </div>

  <div v-else-if="roles.length === 0" class="flex flex-col items-center justify-center py-12">
    <ShieldIcon class="h-12 w-12 text-muted-foreground mb-4" />
    <p class="text-muted-foreground">{{ $t('roles.empty') }}</p>
  </div>

  <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
    <Card
      v-for="role in roles"
      :key="role.id"
      :class="{ 'opacity-50': role.deleted_at }"
      class="hover:shadow-md transition-shadow"
    >
      <CardHeader>
        <div class="flex items-start justify-between">
          <div class="space-y-1 flex-1">
            <CardTitle class="flex items-center gap-2">
              {{ role.display_name || role.name }}
              <LocaleBadge v-if="role.display_name" locale="en" />
            </CardTitle>
            <CardDescription class="text-xs">
              {{ role.name }}
            </CardDescription>
          </div>
          <Badge v-if="role.deleted_at" variant="destructive" class="ml-2">
            {{ $t('common.deleted') }}
          </Badge>
        </div>
      </CardHeader>

      <CardContent class="space-y-3">
        <div class="flex items-center justify-between text-sm">
          <span class="text-muted-foreground">{{ $t('roles.fields.guard') }}</span>
          <Badge variant="outline">{{ role.guard_name }}</Badge>
        </div>

        <div class="flex items-center justify-between text-sm">
          <span class="text-muted-foreground">{{ $t('roles.fields.permissions') }}</span>
          <Badge variant="secondary">
            {{ role.permissions_count || 0 }}
          </Badge>
        </div>

        <div class="flex items-center justify-between text-sm">
          <span class="text-muted-foreground">{{ $t('roles.fields.users') }}</span>
          <span class="font-medium">{{ role.users_count || 0 }}</span>
        </div>

        <div v-if="role.description" class="text-sm text-muted-foreground pt-2 border-t">
          {{ role.description }}
        </div>
      </CardContent>

      <CardFooter class="flex gap-2">
        <Button
          size="sm"
          variant="outline"
          class="flex-1"
          @click="emit('view', role)"
        >
          <EyeIcon class="mr-2 h-4 w-4" />
          {{ $t('common.view') }}
        </Button>

        <Button
          v-if="role.deleted_at"
          size="sm"
          variant="default"
          @click="emit('restore', role)"
        >
          <RotateCcwIcon class="h-4 w-4" />
        </Button>
        <template v-else>
          <Button
            size="sm"
            variant="outline"
            @click="emit('edit', role)"
          >
            <EditIcon class="h-4 w-4" />
          </Button>

          <Button
            size="sm"
            variant="outline"
            @click="emit('clone', role)"
          >
            <CopyIcon class="h-4 w-4" />
          </Button>

          <Button
            size="sm"
            variant="destructive"
            @click="emit('delete', role)"
          >
            <TrashIcon class="h-4 w-4" />
          </Button>
        </template>
      </CardFooter>
    </Card>
  </div>
</template>

