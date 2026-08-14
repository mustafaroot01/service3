<script setup lang="ts">
import AppDataTableServer from '@/components/table/AppDataTableServer.vue'
import { useLookup } from '@/composables/useLookups'
import { useServerTable } from '@/composables/useServerTable'
import type { TableHeader } from '@/types/api'

interface Order {
  id: number
  order_number: string
  status: string
  status_label: string
  scheduled_date: string | null
  time_from: string | null
  time_to: string | null
  visit_ends_next_day: boolean
  user?: { id: number; name: string; phone: string }
  service?: { id: number; name: string }
  technician?: { id: number; name: string } | null
  governorate?: { id: number; name: string }
  district?: { id: number; name: string }
  created_at: string | null
}

const governorates = useLookup('/admin/governorates')

const statusOptions = [
  { title: 'معلّق', value: 'pending' },
  { title: 'مؤكّد', value: 'confirmed' },
  { title: 'تم تعيين فني', value: 'assigned' },
  { title: 'تم الكشف', value: 'inspected' },
  { title: 'تم إنجاز الخدمة', value: 'completed' },
  { title: 'ملغى', value: 'cancelled' },
]

const headers = computed<TableHeader[]>(() => [
  { title: 'رقم الطلب', key: 'order_number' },
  { title: 'الزبون', key: 'user', sortable: false },
  { title: 'الخدمة', key: 'service', sortable: false },
  {
    title: 'المحافظة',
    key: 'governorate',
    sortable: false,
    filterKey: 'governorate_id',
    filter: { type: 'select', options: governorates.items.value.map(g => ({ title: g.name, value: g.id })) },
  },
  {
    title: 'الفني',
    key: 'technician',
    sortable: false,
    filterKey: 'unassigned',
    filter: { type: 'select', options: [{ title: 'بلا فني', value: 1 }, { title: 'معيَّن', value: 0 }] },
  },
  { title: 'الموعد', key: 'scheduled_date' },
  { title: 'الحالة', key: 'status', filter: { type: 'select', options: statusOptions } },
  { title: 'إجراءات', key: 'actions', sortable: false, align: 'center' },
])

const table = useServerTable<Order>('/admin/orders', {
  defaultSort: 'created_at',
  defaultOrder: 'desc',
  filters: { status: null, governorate_id: null, unassigned: null },
})

const shortDate = (value: string | null) => formatDate(value, { month: 'short', day: 'numeric' })
</script>

<template>
  <AppDataTableServer
    title="الطلبات"
    :headers="headers"
    :table="table"
    search-placeholder="بحث برقم الطلب أو الوصف"
  >
    <template #item.order_number="{ item }">
      <RouterLink
        :to="{ name: 'admin-orders-id', params: { id: item.id } }"
        class="text-primary font-weight-medium"
      >
        {{ item.order_number }}
      </RouterLink>
    </template>

    <template #item.user="{ item }">
      <div v-if="item.user">
        <div>{{ item.user.name }}</div>
        <div class="text-caption text-disabled" dir="ltr">{{ item.user.phone }}</div>
      </div>
      <span v-else class="text-disabled">—</span>
    </template>

    <template #item.service="{ item }">{{ item.service?.name ?? '—' }}</template>

    <template #item.governorate="{ item }">
      <div v-if="item.governorate">
        <div>{{ item.governorate.name }}</div>
        <div class="text-caption text-disabled">{{ item.district?.name }}</div>
      </div>
      <span v-else class="text-disabled">—</span>
    </template>

    <template #item.technician="{ item }">
      <span v-if="item.technician">{{ item.technician.name }}</span>
      <VChip v-else size="small" color="warning" label>بلا فني</VChip>
    </template>

    <template #item.scheduled_date="{ item }">
      <div>
        <div>{{ formatDate(item.scheduled_date) }}</div>
        <AppVisitWindow class="text-caption text-disabled" :from="item.time_from" :to="item.time_to" :ends-next-day="item.visit_ends_next_day" />
      </div>
    </template>

    <template #item.status="{ item }">
      <VChip :color="orderStatusVisual(item.status).color" size="small" label>{{ item.status_label }}</VChip>
    </template>

    <template #item.actions="{ item }">
      <VBtn
        icon
        variant="text"
        size="small"
        color="default"
        :to="{ name: 'admin-orders-id', params: { id: item.id } }"
      >
        <VIcon icon="tabler-eye" />
      </VBtn>
    </template>
  </AppDataTableServer>
</template>
