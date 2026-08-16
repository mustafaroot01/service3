<script setup lang="ts">
import AppDataTableServer from '@/components/table/AppDataTableServer.vue'
import { useServerTable } from '@/composables/useServerTable'
import { useToast } from '@/composables/useToast'
import type { ApiResponse, TableHeader } from '@/types/api'

interface Customer {
  id: number
  name: string
  phone: string
  gender: string | null
  status: string
  status_label: string
  terms_accepted_at: string | null
  governorate?: { id: number; name: string }
  district?: { id: number; name: string }
  orders_count?: number
  deletion_requested: boolean
  deletion_requested_at: string | null
  whatsapp: string | null
  created_at: string | null
}

const route = useRoute('admin-users-id')
const toast = useToast()

const customer = ref<Customer | null>(null)
const loading = ref(true)
const changingStatus = ref(false)

const load = async () => {
  loading.value = true
  try {
    const res = await $api<ApiResponse<Customer>>(`/admin/users/${route.params.id}`)

    customer.value = res.data
  }
  finally {
    loading.value = false
  }
}

const setStatus = async (status: string) => {
  changingStatus.value = true
  try {
    const res = await $api<ApiResponse<Customer>>(`/admin/users/${route.params.id}/status`, {
      method: 'PATCH',
      body: { status },
    })

    customer.value = res.data
    toast.success(res.message)
  }
  catch (e: any) {
    const errs = e?.data?.errors

    toast.error(errs ? Object.values(errs).flat()[0] as string : e?.data?.message ?? 'تعذّر تغيير الحالة')
  }
  finally {
    changingStatus.value = false
  }
}

const dismissDeletion = async () => {
  changingStatus.value = true
  try {
    const res = await $api<ApiResponse<Customer>>(`/admin/users/${route.params.id}/deletion-request`, { method: 'DELETE' })

    customer.value = res.data
    toast.success(res.message)
  }
  catch (e: any) {
    toast.error(e?.data?.message ?? 'تعذّر إلغاء الطلب')
  }
  finally {
    changingStatus.value = false
  }
}

const statusOptions = [
  { title: 'نشط', value: 'active' },
  { title: 'غير نشط', value: 'inactive' },
  { title: 'موقوف', value: 'suspended' },
  { title: 'مجدول للحذف', value: 'scheduled_for_deletion' },
]

const orderHeaders: TableHeader[] = [
  { title: 'رقم الطلب', key: 'order_number' },
  { title: 'الخدمة', key: 'service', sortable: false },
  { title: 'الفني', key: 'technician', sortable: false },
  { title: 'الموعد', key: 'scheduled_date' },
  {
    title: 'الحالة',
    key: 'status',
    filter: {
      type: 'select',
      options: [
        { title: 'معلّق', value: 'pending' }, { title: 'مؤكّد', value: 'confirmed' },
        { title: 'تم تعيين فني', value: 'assigned' }, { title: 'تم الكشف', value: 'inspected' },
        { title: 'تم إنجاز الخدمة', value: 'completed' }, { title: 'ملغى', value: 'cancelled' },
      ],
    },
  },
  { title: 'إجراءات', key: 'actions', sortable: false, align: 'center' },
]

const ordersTable = useServerTable<any>(`/admin/users/${route.params.id}/orders`, {
  filters: { status: null },
})

const genderLabel = (value: string | null) =>
  value === 'female' ? 'أنثى' : value === 'male' ? 'ذكر' : '—'

const statusColor = (status: string) => ({
  active: 'success', inactive: 'secondary', suspended: 'error',
  scheduled_for_deletion: 'error',
  confirmed: 'info', assigned: 'primary', inspected: 'secondary', completed: 'success', cancelled: 'error',
}[status] ?? 'secondary')


const details = computed(() => {
  const record = customer.value
  if (!record)
    return []

  return [
    { icon: 'tabler-phone', label: 'رقم الهاتف', value: record.phone, ltr: true },
    { icon: 'tabler-gender-bigender', label: 'الجنس', value: genderLabel(record.gender) },
    { icon: 'tabler-map-2', label: 'المحافظة', value: record.governorate?.name ?? '—' },
    { icon: 'tabler-map-pin', label: 'القضاء', value: record.district?.name ?? '—' },
    { icon: 'tabler-file-check', label: 'قبول الشروط', value: formatDate(record.terms_accepted_at) },
    { icon: 'tabler-clipboard-list', label: 'عدد الطلبات', value: String(record.orders_count ?? 0) },
    { icon: 'tabler-calendar-plus', label: 'تاريخ التسجيل', value: formatDate(record.created_at) },
  ]
})

onMounted(load)
</script>

<template>
  <div>
    <VProgressLinear v-if="loading" indeterminate />

    <template v-else-if="customer">
      <VCard class="mb-6">
        <VCardText class="d-flex flex-wrap align-center gap-x-6 gap-y-4">
          <VAvatar rounded :size="72" color="primary" variant="tonal">
            <span class="text-2xl font-weight-medium">{{ avatarText(customer.name) }}</span>
          </VAvatar>

          <div>
            <div class="d-flex align-center flex-wrap gap-2">
              <h4 class="text-h4 mb-0">{{ customer.name }}</h4>
              <VChip :color="statusColor(customer.status)" size="small" label>
                {{ customer.status_label }}
              </VChip>
            </div>

            <div class="d-flex flex-wrap gap-x-5 gap-y-1 text-body-2 text-disabled mt-2">
              <span dir="ltr">{{ customer.phone }}</span>
              <span>{{ customer.governorate?.name }} / {{ customer.district?.name }}</span>
              <span>{{ customer.orders_count ?? 0 }} طلب</span>
              <span>سجّل {{ formatDate(customer.created_at) }}</span>
            </div>
          </div>

          <VSpacer />

          <div class="d-flex flex-wrap gap-2">
            <VBtn
              v-if="customer.whatsapp"
              :href="customer.whatsapp"
              target="_blank"
              color="success"
              variant="tonal"
              prepend-icon="tabler-brand-whatsapp"
            >
              مراسلة الزبون
            </VBtn>

            <VBtn variant="tonal" color="secondary" :loading="changingStatus" append-icon="tabler-chevron-down">
              تغيير الحالة
              <VMenu activator="parent">
                <VList density="compact">
                  <VListItem
                    v-for="option in statusOptions"
                    :key="option.value"
                    :active="customer.status === option.value"
                    @click="setStatus(option.value)"
                  >
                    <VListItemTitle>{{ option.title }}</VListItemTitle>
                  </VListItem>
                </VList>
              </VMenu>
            </VBtn>
          </div>
        </VCardText>
      </VCard>

      <VAlert
        v-if="customer.deletion_requested"
        type="error"
        variant="tonal"
        class="mb-6"
      >
        <div class="d-flex flex-wrap align-center gap-3">
          <span>طلب هذا الزبون حذف حسابه بتاريخ {{ formatDateTime(customer.deletion_requested_at) }}.</span>
          <VBtn size="small" variant="tonal" color="error" :loading="changingStatus" @click="dismissDeletion">
            إلغاء الطلب
          </VBtn>
        </div>
      </VAlert>

      <VCard title="بيانات الزبون" class="mb-6">
        <VCardText>
          <VRow>
            <VCol v-for="row in details" :key="row.label" cols="12" sm="6" md="3">
              <div class="d-flex align-center gap-3">
                <VAvatar rounded size="38" color="secondary" variant="tonal">
                  <VIcon :icon="row.icon" size="20" />
                </VAvatar>
                <div class="overflow-hidden">
                  <div class="text-caption text-disabled">{{ row.label }}</div>
                  <div
                    class="text-body-1 text-high-emphasis text-truncate"
                    :dir="row.ltr ? 'ltr' : undefined"
                    :class="row.ltr ? 'text-end' : undefined"
                  >
                    {{ row.value }}
                  </div>
                </div>
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <AppDataTableServer
        title="طلبات الزبون"
        :headers="orderHeaders"
        :table="ordersTable"
        search-placeholder="بحث برقم الطلب"
      >
        <template #item.order_number="{ item }">
          <RouterLink
            :to="{ name: 'admin-orders-id', params: { id: item.id } }"
            class="text-primary font-weight-medium"
          >
            {{ item.order_number }}
          </RouterLink>
        </template>

        <template #item.service="{ item }">{{ item.service?.name ?? '—' }}</template>

        <template #item.technician="{ item }">
          <div v-if="item.technician">
            <div>{{ item.technician.name }}</div>
            <div class="text-caption text-disabled" dir="ltr">{{ item.technician.phone }}</div>
          </div>
          <span v-else class="text-disabled">لم يُعيَّن</span>
        </template>

        <template #item.scheduled_date="{ item }">
          <div>
            <div>{{ formatDate(item.scheduled_date) }}</div>
            <AppVisitWindow class="text-caption text-disabled" :from="item.time_from" :to="item.time_to" :ends-next-day="item.visit_ends_next_day" />
          </div>
        </template>

        <template #item.status="{ item }">
          <VChip :color="statusColor(item.status)" size="small" label>{{ item.status_label }}</VChip>
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
            <VTooltip activator="parent" location="top">عرض الطلب</VTooltip>
          </VBtn>
        </template>
      </AppDataTableServer>
    </template>
  </div>
</template>
