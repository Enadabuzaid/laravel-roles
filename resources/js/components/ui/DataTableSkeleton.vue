<script setup lang="ts">
/**
 * DataTableSkeleton Component
 *
 * Loading skeleton for data tables.
 */
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

const props = withDefaults(defineProps<{
  columns?: number
  rows?: number
}>(), {
  columns: 5,
  rows: 5,
})
</script>

<template>
  <div class="rounded-md border">
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead v-for="i in columns" :key="i">
            <Skeleton class="h-4 w-24" />
          </TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        <TableRow v-for="row in rows" :key="row">
          <TableCell v-for="col in columns" :key="col">
            <Skeleton class="h-4 w-full" />
          </TableCell>
        </TableRow>
      </TableBody>
    </Table>
  </div>
</template>
