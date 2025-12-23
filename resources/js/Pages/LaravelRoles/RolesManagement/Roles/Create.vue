<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import Toast from '../../shared/Toast.vue'

interface Props { config: { prefix: string }; guards?: string[]; permissions?: any[] }
const props = withDefaults(defineProps<Props>(), { guards: () => ['web', 'api'], permissions: () => [] })
interface Permission { id: number; name: string; group: string; label?: string }

const form = ref({ name: '', description: '', guard_name: 'web', permissions: [] as number[] })
const errors = ref<Record<string, string[]>>({}), loading = ref(false), permSearch = ref(''), allPerms = ref<Permission[]>([])

const apiPrefix = computed(() => props.config?.prefix || 'admin/acl')
const url = (p: string) => `/${apiPrefix.value}${p}`
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
const toast = (m: string, t: 'success' | 'error' = 'success') => { const x = (window as any).__lr_toast; if (x) t === 'success' ? x.success(m) : x.error(m) }

const fetchPerms = async () => { if (props.permissions?.length) { allPerms.value = props.permissions; return }; try { const res = await fetch(url('/permissions'), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }); if (res.ok) { const d = await res.json(); allPerms.value = d.data || d || [] } } catch {} }

const groupedPerms = computed(() => {
  const q = permSearch.value.toLowerCase()
  const filtered = allPerms.value.filter(p => p.name.toLowerCase().includes(q) || (p.label?.toLowerCase().includes(q)))
  const g: Record<string, Permission[]> = {}
  for (const p of filtered) { const k = p.group || 'other'; if (!g[k]) g[k] = []; g[k].push(p) }
  return g
})

const togglePerm = (id: number) => { const i = form.value.permissions.indexOf(id); i === -1 ? form.value.permissions.push(id) : form.value.permissions.splice(i, 1) }
const toggleGroup = (perms: Permission[]) => { const ids = perms.map(p => p.id); const all = ids.every(id => form.value.permissions.includes(id)); if (all) form.value.permissions = form.value.permissions.filter(id => !ids.includes(id)); else ids.forEach(id => { if (!form.value.permissions.includes(id)) form.value.permissions.push(id) }) }
const isGroupSelected = (perms: Permission[]) => perms.every(p => form.value.permissions.includes(p.id))
const isGroupPartial = (perms: Permission[]) => { const s = perms.filter(p => form.value.permissions.includes(p.id)); return s.length > 0 && s.length < perms.length }
const selectAll = () => form.value.permissions = allPerms.value.map(p => p.id)
const deselectAll = () => form.value.permissions = []

const submit = async () => {
  loading.value = true; errors.value = {}
  try {
    const permNames = form.value.permissions.map(id => allPerms.value.find(p => p.id === id)?.name).filter(Boolean)
    const res = await fetch(url('/roles'), { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', body: JSON.stringify({ name: form.value.name, description: form.value.description, guard_name: form.value.guard_name, permissions: permNames }) })
    const d = await res.json()
    if (!res.ok) { if (res.status === 422) errors.value = d.errors || {}; throw new Error() }
    toast('Role created'); window.location.href = url('/roles')
  } catch { toast('Failed', 'error') } finally { loading.value = false }
}
onMounted(fetchPerms)
</script>

<template>
  <Head title="Create Role" /><Toast />
  <div class="space-y-6 max-w-4xl">
    <div class="flex items-center gap-4"><a :href="url('/roles')" class="p-2 rounded-lg hover:bg-muted"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg></a><div><h1 class="text-2xl font-bold">Create Role</h1><p class="text-muted-foreground">Create a new role and assign permissions</p></div></div>
    <form @submit.prevent="submit" class="space-y-6">
      <div class="rounded-xl border bg-card shadow-sm"><div class="border-b p-4"><h2 class="font-semibold">Basic Info</h2></div><div class="space-y-4 p-4">
        <div class="space-y-2"><label class="text-sm font-medium">Name <span class="text-destructive">*</span></label><input v-model="form.name" type="text" placeholder="e.g., editor" :class="['h-10 w-full rounded-lg border bg-background px-3 text-sm', errors.name ? 'border-destructive' : '']" /><p v-if="errors.name" class="text-sm text-destructive">{{ errors.name[0] }}</p></div>
        <div class="space-y-2"><label class="text-sm font-medium">Description</label><textarea v-model="form.description" rows="2" placeholder="Describe role..." class="w-full rounded-lg border bg-background px-3 py-2 text-sm" /></div>
        <div class="space-y-2"><label class="text-sm font-medium">Guard</label><select v-model="form.guard_name" class="h-10 rounded-lg border bg-background px-3 text-sm"><option v-for="g in props.guards" :key="g" :value="g">{{ g }}</option></select></div>
      </div></div>
      <div class="rounded-xl border bg-card shadow-sm"><div class="border-b p-4 flex items-center justify-between"><div><h2 class="font-semibold">Permissions</h2><p class="text-sm text-muted-foreground">{{ form.permissions.length }}/{{ allPerms.length }} selected</p></div><div class="flex gap-2 text-sm"><button type="button" @click="selectAll" class="text-primary hover:underline">Select All</button><span class="text-muted-foreground">|</span><button type="button" @click="deselectAll" class="text-primary hover:underline">Deselect All</button></div></div>
        <div class="p-4"><div class="relative mb-4"><svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg><input v-model="permSearch" type="text" placeholder="Search..." class="h-10 w-full rounded-lg border bg-background pl-10 pr-3 text-sm" /></div>
        <div v-if="!Object.keys(groupedPerms).length" class="py-8 text-center text-muted-foreground">{{ permSearch ? 'No matches' : 'No permissions. Run: php artisan roles:sync' }}</div>
        <div v-else class="space-y-4"><div v-for="(perms, group) in groupedPerms" :key="group" class="rounded-lg border"><div class="flex items-center gap-3 bg-muted/50 px-4 py-3"><input type="checkbox" :checked="isGroupSelected(perms)" :indeterminate="isGroupPartial(perms)" @change="toggleGroup(perms)" class="h-4 w-4 rounded" /><span class="font-medium capitalize">{{ group }}</span><span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">{{ perms.filter(p => form.permissions.includes(p.id)).length }}/{{ perms.length }}</span></div><div class="grid gap-2 p-4 sm:grid-cols-2 md:grid-cols-3"><label v-for="p in perms" :key="p.id" :class="['flex items-center gap-2 rounded-lg border p-3 cursor-pointer hover:bg-muted/50', form.permissions.includes(p.id) ? 'bg-primary/5 border-primary/50' : '']"><input type="checkbox" :checked="form.permissions.includes(p.id)" @change="togglePerm(p.id)" class="h-4 w-4 rounded" /><div class="flex-1 min-w-0"><p class="text-sm font-medium truncate">{{ p.label || p.name }}</p></div></label></div></div></div>
      </div></div>
      <div class="flex justify-end gap-3"><a :href="url('/roles')" class="h-10 px-4 rounded-lg border inline-flex items-center text-sm font-medium hover:bg-muted">Cancel</a><button type="submit" :disabled="loading" class="h-10 px-4 rounded-lg bg-primary text-primary-foreground text-sm font-medium inline-flex items-center disabled:opacity-50"><svg v-if="loading" class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>{{ loading ? 'Creating...' : 'Create Role' }}</button></div>
    </form>
  </div>
</template>
