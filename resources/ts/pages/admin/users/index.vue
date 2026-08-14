<script setup lang="ts">
import AppDataTableServer from '@/components/table/AppDataTableServer.vue'
import { useLookup } from '@/composables/useLookups'
import { useRowAction } from '@/composables/useRowAction'
import { useServerTable } from '@/composables/useServerTable'
import type { TableHeader } from '@/types/api'

interface User {
  id: number
  name: string
  phone: string
  gender: string | null
  status: string
  status_label: string
  phone_verified: boolean
  governorate?: { id: number; name: string }
  district?: { id: number; name: string }
  orders_count?: number
  deletion_requested: boolean
  deletion_requested_at: string | null
  created_at: string | null
}

const governorates = useLookup('/admin/governorates')

const statusOptions = [
  { title: 'نشط', value: 'active' },
  { title: 'غير نشط', value: 'inactive' },
  { title: 'موقوف', value: 'suspended' },
  { title: 'مجدول للحذف', value: 'scheduled_for_deletion' },
]

const headers = computed<TableHeader[]>(() => [
  { title: 'الزبون', key: 'name' },
  { title: 'الهاتف', key: 'phone' },
  {
    title: 'المحافظة',
    key: 'governorate',
    sortable: false,
    filterKey: 'governorate_id',
    filter: { type: 'select', options: governorates.items.value.map(g => ({ title: g.name, value: g.id })) },
  },
  { title: 'الطلبات', key: 'orders_count', align: 'center', sortable: false },
  {
    title: 'التوثيق',
    key: 'phone_verified',
    sortable: false,
    filterKey: 'phone_verified',
    filter: { type: 'select', options: [{ title: 'موثّق', value: 1 }, { title: 'غير موثّق', value: 0 }] },
  },
  {
    title: 'الحالة',
    key: 'status',
    filterKey: 'status',
    filter: { type: 'select', options: statusOptions },
  },
  {
    title: 'طلب حذف',
    key: 'deletion_requested',
    sortable: false,
    align: 'center',
    filter: { type: 'select', options: [{ title: 'طلب الحذف', value: 1 }, { title: 'بلا طلب', value: 0 }] },
  },
  { title: 'إجراءات', key: 'actions', sortable: false, align: 'center' },
])

const table = useServerTable<User>('/admin/users', {
  defaultSort: 'created_at',
  defaultOrder: 'desc',
  filters: { status: null, governorate_id: null, phone_verified: null, deletion_requested: null },
})

const { busyRow, run } = useRowAction(() => table.refresh())

const dismissDeletion = (row: User) =>
  run(row.id, () => $api(`/admin/users/${row.id}/deletion-request`, { method: 'DELETE' }))

const setStatus = (row: User, status: string) =>
  run(row.id, () => $api(`/admin/users/${row.id}/status`, { method: 'PATCH', body: { status } }))

const genderLabel = (value: string | null) =>
  value === 'female' ? 'أنثى' : value === 'male' ? 'ذكر' : '—'

const statusColor = (status: string) => ({
  active: 'success', inactive: 'secondary', suspended: 'error',
  scheduled_for_deletion: 'error',
}[status] ?? 'secondary')
</script>

<template>
  <AppDataTableServer
    title="الزبائن"
    :headers="headers"
    :table="table"
    search-placeholder="بحث بالاسم أو الهاتف"
  >
    <template #item.name="{ item }">
      <div>
        <div class="text-high-emphasis font-weight-medium">{{ item.name }}</div>
        <div class="text-caption text-disabled">{{ genderLabel(item.gender) }}</div>
      </div>
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

    <template #item.orders_count="{ item }">
      <VChip size="small" label color="info">{{ item.orders_count ?? 0 }}</VChip>
    </template>

    <template #item.phone_verified="{ item }">
      <VIcon
        :icon="item.phone_verified ? 'tabler-circle-check' : 'tabler-circle-x'"
        :color="item.phone_verified ? 'success' : 'disabled'"
        size="20"
      />
    </template>

    <template #item.deletion_requested="{ item }">
      <VChip
        v-if="item.deletion_requested"
        color="error"
        size="small"
        variant="tonal"
        label
        prepend-icon="tabler-user-x"
      >
        طلب حذف
        <VTooltip activator="parent" location="top">{{ formatDateTime(item.deletion_requested_at) }}</VTooltip>
      </VChip>
      <span v-else class="text-disabled">—</span>
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
          :to="{ name: 'admin-users-id', params: { id: item.id } }"
        >
          <VIcon icon="tabler-eye" />
          <VTooltip activator="parent" location="top">عرض الملف والطلبات</VTooltip>
        </VBtn>

        <VBtn icon variant="text" size="small" color="default" :loading="busyRow === item.id">
          <VIcon icon="tabler-dots-vertical" />
          <VMenu activator="parent">
            <VList density="compact">
              <template v-if="item.deletion_requested">
                <VListItem @click="dismissDeletion(item)">
                  <template #prepend><VIcon icon="tabler-user-check" size="20" /></template>
                  <VListItemTitle>إلغاء طلب الحذف</VListItemTitle>
                </VListItem>
                <VDivider class="my-1" />
              </template>

              <VListSubheader>تغيير الحالة</VListSubheader>
              <VListItem
                v-for="option in statusOptions"
                :key="option.value"
                :active="item.status === option.value"
                @click="setStatus(item, option.value)"
              >
                <VListItemTitle>{{ option.title }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </VBtn>
      </div>
    </template>
  </AppDataTableServer>
</template>
