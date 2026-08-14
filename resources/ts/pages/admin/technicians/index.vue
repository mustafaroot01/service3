<script setup lang="ts">
import AppDataTableServer from '@/components/table/AppDataTableServer.vue'
import { useLookup } from '@/composables/useLookups'
import { useRowAction } from '@/composables/useRowAction'
import { useServerTable } from '@/composables/useServerTable'
import TechnicianFormDrawer from '@/views/admin/technicians/TechnicianFormDrawer.vue'
import type { TableHeader } from '@/types/api'

interface Technician {
  id: number
  name: string
  phone: string
  status: string
  status_label: string
  source: string
  source_label: string
  governorate?: { id: number; name: string }
  district?: { id: number; name: string }
  specializations?: { id: number; name: string }[]
  orders_count?: number
  documents_complete?: boolean
  missing_documents?: { type: string; label: string }[]
}

const governorates = useLookup('/admin/governorates')
const specializations = useLookup('/admin/specializations')

const statusOptions = [
  { title: 'نشط', value: 'active' },
  { title: 'قيد الانتظار', value: 'pending' },
  { title: 'غير نشط', value: 'inactive' },
  { title: 'موقوف', value: 'suspended' },
]

const headers = computed<TableHeader[]>(() => [
  { title: 'الفني', key: 'name' },
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
  { title: 'الأوراق', key: 'documents_complete', sortable: false, align: 'center' },
  { title: 'الطلبات', key: 'orders_count', align: 'center', sortable: false },
  {
    title: 'المصدر',
    key: 'source',
    sortable: false,
    filter: {
      type: 'select',
      options: [
        { title: 'من اللوحة', value: 'manual' },
        { title: 'من استمارة', value: 'application' },
      ],
    },
  },
  { title: 'الحالة', key: 'status', filter: { type: 'select', options: statusOptions } },
  { title: 'إجراءات', key: 'actions', sortable: false, align: 'center' },
])

const table = useServerTable<Technician>('/admin/technicians', {
  defaultSort: 'created_at',
  defaultOrder: 'desc',
  filters: { status: null, governorate_id: null, specialization_id: null, source: null },
})

const { busyRow, run } = useRowAction(() => table.refresh())
const formDrawer = ref<InstanceType<typeof TechnicianFormDrawer>>()
const confirmDelete = ref<Technician | null>(null)

const setStatus = (row: Technician, status: string) =>
  run(row.id, () => $api(`/admin/technicians/${row.id}/status`, { method: 'PATCH', body: { status } }))

const remove = async () => {
  const row = confirmDelete.value
  if (!row)
    return

  confirmDelete.value = null
  await run(row.id, () => $api(`/admin/technicians/${row.id}`, { method: 'DELETE' }))
}

const statusColor = (status: string) => ({
  active: 'success', pending: 'warning', inactive: 'secondary', suspended: 'error',
}[status] ?? 'secondary')
</script>

<template>
  <div>
    <AppDataTableServer
      title="الفنيون"
      create-label="إضافة فني"
      :headers="headers"
      :table="table"
      search-placeholder="بحث بالاسم أو الهاتف"
      @create="formDrawer?.openCreate()"
    >
      <template #item.name="{ item }">
        <RouterLink
          :to="{ name: 'admin-technicians-id', params: { id: item.id } }"
          class="text-primary font-weight-medium"
        >
          {{ item.name }}
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
            color="primary"
            variant="tonal"
            label
          >
            {{ s.name }}
          </VChip>
          <span v-if="!item.specializations?.length" class="text-disabled">—</span>
        </div>
      </template>

      <template #item.documents_complete="{ item }">
        <VTooltip location="top">
          <template #activator="{ props: tip }">
            <VChip
              v-bind="tip"
              :color="item.documents_complete ? 'success' : 'warning'"
              size="small"
              label
            >
              {{ item.documents_complete ? 'مكتملة' : `ناقص ${item.missing_documents?.length ?? 0}` }}
            </VChip>
          </template>
          <span v-if="item.missing_documents?.length">
            {{ item.missing_documents.map((d: { label: string }) => d.label).join(' · ') }}
          </span>
          <span v-else>كل الأوراق مرفوعة</span>
        </VTooltip>
      </template>

      <template #item.orders_count="{ item }">
        <VChip size="small" label color="info">{{ item.orders_count ?? 0 }}</VChip>
      </template>

      <template #item.source="{ item }">
        <VChip
          :color="item.source === 'application' ? 'warning' : 'secondary'"
          size="small"
          variant="tonal"
          label
        >
          {{ item.source_label }}
        </VChip>
      </template>

      <template #item.status="{ item }">
        <VChip :color="statusColor(item.status)" size="small" label>{{ item.status_label }}</VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex align-center justify-center">
          <VBtn
            icon
            variant="text"
            size="small"
            color="default"
            :to="{ name: 'admin-technicians-id', params: { id: item.id } }"
          >
            <VIcon icon="tabler-eye" />
            <VTooltip activator="parent" location="top">عرض الملف</VTooltip>
          </VBtn>

          <VBtn
            icon
            variant="text"
            size="small"
            color="default"
            :loading="busyRow === item.id || formDrawer?.opening"
          >
            <VIcon icon="tabler-dots-vertical" />
            <VMenu activator="parent">
              <VList density="compact">
                <VListItem @click="formDrawer?.openEdit(item.id)">
                  <template #prepend><VIcon icon="tabler-edit" size="20" /></template>
                  <VListItemTitle>تعديل</VListItemTitle>
                </VListItem>

                <VDivider />
                <VListSubheader>تغيير الحالة</VListSubheader>
                <VListItem
                  v-for="option in statusOptions"
                  :key="option.value"
                  :active="item.status === option.value"
                  @click="setStatus(item, option.value)"
                >
                  <VListItemTitle>{{ option.title }}</VListItemTitle>
                </VListItem>

                <VDivider />
                <VListItem @click="confirmDelete = item">
                  <template #prepend><VIcon icon="tabler-trash" size="20" color="error" /></template>
                  <VListItemTitle class="text-error">حذف</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </VBtn>
        </div>
      </template>
    </AppDataTableServer>

    <TechnicianFormDrawer ref="formDrawer" @saved="table.refresh()" />

    <VDialog :model-value="confirmDelete !== null" max-width="440" @update:model-value="confirmDelete = null">
      <VCard title="تأكيد الحذف">
        <VCardText>سيتم حذف «{{ confirmDelete?.name }}» وكل صوره وأوراقه. هل أنت متأكد؟</VCardText>
        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="confirmDelete = null">إلغاء</VBtn>
          <VBtn color="error" @click="remove">حذف</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
