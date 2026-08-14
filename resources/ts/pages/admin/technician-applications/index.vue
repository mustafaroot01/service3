<script setup lang="ts">
import AppDataTableServer from '@/components/table/AppDataTableServer.vue'
import { useLookup } from '@/composables/useLookups'
import { useServerTable } from '@/composables/useServerTable'
import type { TableHeader } from '@/types/api'

interface Application {
  id: number
  full_name: string
  phone: string
  status: string
  status_label: string
  governorate?: { id: number; name: string }
  district?: { id: number; name: string }
  specializations?: { id: number; name: string }[]
  created_at: string | null
}

const governorates = useLookup('/admin/governorates')
const specializations = useLookup('/admin/specializations')

const statusOptions = [
  { title: 'معلّق', value: 'pending' },
  { title: 'قيد المراجعة', value: 'under_review' },
  { title: 'تم الرفض', value: 'rejected' },
]

const headers = computed<TableHeader[]>(() => [
  { title: 'المتقدّم', key: 'full_name' },
  { title: 'الهاتف', key: 'phone' },
  {
    title: 'المحافظة',
    key: 'governorate',
    sortable: false,
    filterKey: 'governorate_id',
    filter: { type: 'select', options: governorates.items.value.map(g => ({ title: g.name, value: g.id })) },
  },
  {
    title: 'الاختصاصات',
    key: 'specializations',
    sortable: false,
    filterKey: 'specialization_id',
    filter: { type: 'select', options: specializations.items.value.map(s => ({ title: s.name, value: s.id })) },
  },
  { title: 'تاريخ التقديم', key: 'created_at' },
  { title: 'الحالة', key: 'status', filter: { type: 'select', options: statusOptions } },
  { title: 'إجراءات', key: 'actions', sortable: false, align: 'center' },
])

const table = useServerTable<Application>('/admin/technician-applications', {
  defaultSort: 'created_at',
  defaultOrder: 'desc',
  filters: { status: null, governorate_id: null, specialization_id: null },
})

const statusColor = (status: string) => ({
  pending: 'warning', under_review: 'info', rejected: 'error',
}[status] ?? 'secondary')

</script>

<template>
  <AppDataTableServer
    title="استمارات انضمام الفنيين"
    :headers="headers"
    :table="table"
    search-placeholder="بحث بالاسم أو الهاتف"
  >
    <template #item.full_name="{ item }">
      <RouterLink
        :to="{ name: 'admin-technician-applications-id', params: { id: item.id } }"
        class="text-primary font-weight-medium"
      >
        {{ item.full_name }}
      </RouterLink>
    </template>

    <template #item.phone="{ item }">
      <span dir="ltr">{{ item.phone }}</span>
    </template>

    <template #item.governorate="{ item }">
      <div v-if="item.governorate">
        <div>{{ item.governorate.name }}</div>
        <div class="text-caption text-disabled">{{ item.district?.name }}</div>
      </div>
      <span v-else class="text-disabled">—</span>
    </template>

    <template #item.specializations="{ item }">
      <div class="d-flex flex-wrap gap-1">
        <VChip
          v-for="s in item.specializations ?? []"
          :key="s.id"
          size="x-small"
          variant="tonal"
          label
        >
          {{ s.name }}
        </VChip>
      </div>
    </template>

    <template #item.created_at="{ item }">{{ formatDate(item.created_at) }}</template>

    <template #item.status="{ item }">
      <VChip :color="statusColor(item.status)" size="small" label>{{ item.status_label }}</VChip>
    </template>

    <template #item.actions="{ item }">
      <VBtn
        icon
        variant="text"
        size="small"
        color="default"
        :to="{ name: 'admin-technician-applications-id', params: { id: item.id } }"
      >
        <VIcon icon="tabler-eye" />
        <VTooltip activator="parent" location="top">عرض الاستمارة</VTooltip>
      </VBtn>
    </template>
  </AppDataTableServer>
</template>
