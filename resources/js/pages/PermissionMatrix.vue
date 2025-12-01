<script setup lang="ts">
import { onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'
import { HomeIcon, ArrowLeftIcon } from 'lucide-vue-next'
import { usePermissionsApi } from '@/composables/usePermissionsApi'
import PermissionsMatrixTable from '@/components/PermissionsMatrixTable.vue'

// Composable
const {
  matrix,
  isLoading,
  fetchMatrix,
  togglePermission
} = usePermissionsApi()

// Lifecycle
onMounted(async () => {
  await fetchMatrix()
})

// Methods
const handleToggle = async (payload: { roleId: number; permissionId: number; value: boolean }) => {
  try {
    await togglePermission(payload.roleId, payload.permissionId, payload.value)
    // TODO: Show success toast
  } catch (error) {
    // TODO: Show error toast
    console.error('Failed to toggle permission:', error)
  }
}

const handleBack = () => {
  router.visit('/admin/acl/permissions')
}
</script>

<template>
  <div class="container mx-auto py-6 space-y-6">
    <Head :title="$t('roles.matrix.title')" />

    <!-- Breadcrumbs -->
    <Breadcrumb>
      <BreadcrumbList>
        <BreadcrumbItem>
          <BreadcrumbLink href="/admin">
            <HomeIcon class="h-4 w-4" />
          </BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <BreadcrumbLink href="/admin/acl/permissions">
            {{ $t('roles.permissions.title') }}
          </BreadcrumbLink>
        </BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem>
          <BreadcrumbPage>{{ $t('roles.matrix.title') }}</BreadcrumbPage>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- Page Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-3xl font-bold tracking-tight">
          {{ $t('roles.matrix.title') }}
        </h1>
        <p class="text-muted-foreground">
          {{ $t('roles.matrix.subtitle') }}
        </p>
      </div>
      <Button variant="outline" @click="handleBack">
        <ArrowLeftIcon class="mr-2 h-4 w-4" />
        {{ $t('common.back') }}
      </Button>
    </div>

    <!-- Info Card -->
    <div class="rounded-lg border bg-card p-4">
      <div class="flex items-start gap-3">
        <div class="rounded-full bg-primary/10 p-2">
          <svg
            class="h-4 w-4 text-primary"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
        </div>
        <div class="space-y-1">
          <p class="text-sm font-medium">
            {{ $t('roles.matrix.info.title') }}
          </p>
          <p class="text-sm text-muted-foreground">
            {{ $t('roles.matrix.info.description') }}
          </p>
        </div>
      </div>
    </div>

    <!-- Matrix -->
    <PermissionsMatrixTable
      v-if="matrix"
      :matrix="matrix"
      :loading="isLoading"
      @toggle="handleToggle"
    />

    <!-- Empty State -->
    <div
      v-else-if="!isLoading"
      class="flex flex-col items-center justify-center py-12 text-center"
    >
      <div class="rounded-full bg-muted p-3 mb-4">
        <svg
          class="h-6 w-6 text-muted-foreground"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
          />
        </svg>
      </div>
      <h3 class="text-lg font-semibold">{{ $t('roles.matrix.empty_title') }}</h3>
      <p class="text-muted-foreground mt-2 max-w-md">
        {{ $t('roles.matrix.empty_description') }}
      </p>
    </div>
  </div>
</template>

