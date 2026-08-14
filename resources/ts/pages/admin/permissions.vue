<script setup lang="ts">
import AppDataTableServer from '@/components/table/AppDataTableServer.vue'
import { useServerTable } from '@/composables/useServerTable'
import type { ApiResponse, TableHeader } from '@/types/api'

interface Permission {
  id: number
  key: string
  module: string
  module_label: string
  group: string | null
  action: string
  action_label: string
  label: string
  roles: { id: number; label: string; is_locked: boolean }[]
}

interface Option { key: string; label: string }

const modules = ref<Option[]>([])
const actions = ref<Option[]>([])
const roles = ref<{ id: number; label: string }[]>([])

const headers = computed<TableHeader[]>(() => [
  { title: 'الصلاحية', key: 'label', sortable: false },
  {
    title: 'القسم',
    key: 'module_label',
    sortable: false,
    filterKey: 'module',
    filter: { type: 'select', options: modules.value.map(m => ({ title: m.label, value: m.key })) },
  },
  {
    title: 'الإجراء',
    key: 'action_label',
    sortable: false,
    filterKey: 'action',
    filter: { type: 'select', options: actions.value.map(a => ({ title: a.label, value: a.key })) },
  },
  {
    title: 'مُسندة إلى',
    key: 'roles',
    sortable: false,
    filterKey: 'role_id',
    filter: { type: 'select', options: roles.value.map(r => ({ title: r.label, value: r.id })) },
  },
  { title: 'المعرّف', key: 'key', sortable: false },
])

const table = useServerTable<Permission>('/admin/permissions', {
  filters: { module: null, action: null, role_id: null },
})

const actionColor = (action: string) => ({
  view: 'info', create: 'success', update: 'warning', delete: 'error',
}[action] ?? 'secondary')

onMounted(async () => {
  const [filters, roleList] = await Promise.all([
    $api<ApiResponse<{ modules: Option[]; actions: Option[] }>>('/admin/permissions/filters'),
    $api<ApiResponse<{ id: number; label: string }[]>>('/admin/roles'),
  ])

  modules.value = filters.data?.modules ?? []
  actions.value = filters.data?.actions ?? []
  roles.value = roleList.data ?? []
})
</script>

<template>
  <div>
    <VAlert
      type="info"
      variant="tonal"
      density="compact"
      class="mb-6"
    >
      الصلاحيات مُعرَّفة في النظام نفسه ولا تُضاف أو تُحذف من هنا — كل واحدة يحرسها مسار فعلي في الـAPI.
      ما تتحكّم به هو <strong>مَن يملكها</strong>، من
      <RouterLink :to="{ name: 'admin-roles' }" class="text-primary">صفحة الأدوار</RouterLink>.
    </VAlert>

    <AppDataTableServer
      title="الصلاحيات المتوفرة"
      :headers="headers"
      :table="table"
      search-placeholder="بحث بالاسم أو المعرّف"
    >
      <template #item.label="{ item }">
        <span class="text-high-emphasis font-weight-medium">{{ item.label }}</span>
      </template>

      <template #item.action_label="{ item }">
        <VChip :color="actionColor(item.action)" size="small" variant="tonal" label>
          {{ item.action_label }}
        </VChip>
      </template>

      <template #item.roles="{ item }">
        <div v-if="item.roles.length" class="d-flex flex-wrap gap-1">
          <VChip
            v-for="role in item.roles"
            :key="role.id"
            size="x-small"
            :color="role.is_locked ? 'error' : 'primary'"
            variant="tonal"
            label
          >
            {{ role.label }}
          </VChip>
        </div>
        <span v-else class="text-disabled">لا أحد</span>
      </template>

      <template #item.key="{ item }">
        <span class="text-caption text-disabled" dir="ltr">{{ item.key }}</span>
      </template>
    </AppDataTableServer>
  </div>
</template>
