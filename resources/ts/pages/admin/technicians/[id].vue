<script setup lang="ts">
import AppDataTableServer from '@/components/table/AppDataTableServer.vue'
import { useLookup } from '@/composables/useLookups'
import { useServerTable } from '@/composables/useServerTable'
import { useToast } from '@/composables/useToast'
import TechnicianTabDocuments from '@/views/admin/technicians/TechnicianTabDocuments.vue'
import type { ApiResponse, TableHeader } from '@/types/api'

interface Doc { id: number; type: string; type_label: string; url: string }

interface Technician {
  id: number
  name: string
  phone: string
  status: string
  status_label: string
  source: string
  source_label: string
  governorate_id: number
  district_id: number
  governorate?: { id: number; name: string }
  district?: { id: number; name: string }
  specializations?: { id: number; name: string }[]
  orders_count?: number
  documents?: Record<string, Doc | Doc[] | null>
  documents_complete?: boolean
  missing_documents?: { type: string; label: string }[]
  created_at: string | null
}

const route = useRoute('admin-technicians-id')
const toast = useToast()
const governorates = useLookup('/admin/governorates')
const specializations = useLookup('/admin/specializations')

const technician = ref<Technician | null>(null)
const loading = ref(true)
const form = reactive({
  name: '', phone: '',
  governorate_id: null as number | null,
  district_id: null as number | null,
  specialization_ids: [] as number[],
})

const errors = ref<Record<string, string[]>>({})
const savingProfile = ref(false)
const districts = ref<{ id: number; name: string }[]>([])
const uploading = ref<string | null>(null)
const changingStatus = ref(false)
const confirmRemove = ref<Doc | null>(null)

const loadDistricts = async (governorateId: number | null) => {
  districts.value = []
  if (!governorateId)
    return

  const res = await $api<ApiResponse<any[]>>('/admin/districts', {
    query: { governorate_id: governorateId, per_page: 200 },
  })

  districts.value = res.data ?? []
}

const applyToForm = (t: Technician) => {
  form.name = t.name
  form.phone = t.phone
  form.governorate_id = t.governorate_id
  form.district_id = t.district_id
  form.specialization_ids = t.specializations?.map(s => s.id) ?? []
}

const load = async () => {
  loading.value = true
  try {
    const res = await $api<ApiResponse<Technician>>(`/admin/technicians/${route.params.id}`)

    technician.value = res.data
    applyToForm(res.data)
    await loadDistricts(res.data.governorate_id)
  }
  finally {
    loading.value = false
  }
}

watch(() => form.governorate_id, async (next, prev) => {
  if (prev !== null && next !== prev)
    form.district_id = null

  await loadDistricts(next)
})

const identity = () => ({
  name: form.name,
  phone: form.phone,
  governorate_id: form.governorate_id,
  district_id: form.district_id,
  specialization_ids: form.specialization_ids,
})

/** One multipart write path for both the profile fields and the images. */
const submit = async (files: Record<string, File | File[]> = {}) => {
  const body = new FormData()

  body.append('_method', 'PUT')
  for (const [k, v] of Object.entries(identity())) {
    if (Array.isArray(v))
      v.forEach(x => body.append(`${k}[]`, String(x)))
    else if (v !== null && v !== undefined)
      body.append(k, String(v))
  }
  for (const [k, v] of Object.entries(files)) {
    if (Array.isArray(v))
      v.forEach(f => body.append(`${k}[]`, f))
    else
      body.append(k, v)
  }

  const res = await $api<ApiResponse<Technician>>(`/admin/technicians/${route.params.id}`, {
    method: 'POST',
    body,
  })

  technician.value = res.data
  applyToForm(res.data)

  return res
}

const reportFailure = (e: any, fallback: string) => {
  const errs = e?.data?.errors

  errors.value = errs ?? {}
  toast.error(errs ? Object.values(errs).flat()[0] as string : e?.data?.message ?? fallback)
}

const saveProfile = async () => {
  savingProfile.value = true
  errors.value = {}
  try {
    toast.success((await submit()).message)
  }
  catch (e: any) {
    reportFailure(e, 'تعذّر الحفظ')
  }
  finally {
    savingProfile.value = false
  }
}

const uploadSlot = async (slot: string, file: File) => {
  uploading.value = slot
  errors.value = {}
  try {
    toast.success((await submit({ [slot]: file })).message)
  }
  catch (e: any) {
    reportFailure(e, 'تعذّر الرفع')
  }
  finally {
    uploading.value = null
  }
}

const addSamples = async (files: File[]) => {
  uploading.value = 'work_samples'
  errors.value = {}
  try {
    toast.success((await submit({ work_samples: files })).message)
  }
  catch (e: any) {
    reportFailure(e, 'تعذّر الرفع')
  }
  finally {
    uploading.value = null
  }
}

const removeMedia = async () => {
  const doc = confirmRemove.value
  if (!doc)
    return

  confirmRemove.value = null
  try {
    const res = await $api<{ message: string }>(
      `/admin/technicians/${route.params.id}/media/${doc.id}`,
      { method: 'DELETE' },
    )

    toast.success(res.message)
    await load()
  }
  catch (e: any) {
    toast.error(e?.data?.message ?? 'تعذّر الحذف')
  }
}

const setStatus = async (status: string) => {
  changingStatus.value = true
  try {
    const res = await $api<ApiResponse<Technician>>(`/admin/technicians/${route.params.id}/status`, {
      method: 'PATCH',
      body: { status },
    })

    technician.value = res.data
    toast.success(res.message)
  }
  catch (e: any) {
    reportFailure(e, 'تعذّر تغيير الحالة')
  }
  finally {
    changingStatus.value = false
  }
}

const orderHeaders: TableHeader[] = [
  { title: 'رقم الطلب', key: 'order_number' },
  { title: 'الزبون', key: 'user', sortable: false },
  { title: 'الخدمة', key: 'service', sortable: false },
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

const ordersTable = useServerTable<any>(`/admin/technicians/${route.params.id}/orders`, {
  filters: { status: null },
})

const statusOptions = [
  { title: 'نشط', value: 'active' },
  { title: 'قيد الانتظار', value: 'pending' },
  { title: 'غير نشط', value: 'inactive' },
  { title: 'موقوف', value: 'suspended' },
]

const avatar = computed(() => (technician.value?.documents?.personal as Doc | null)?.url)

const statusColor = (status: string) => ({
  active: 'success', pending: 'warning', inactive: 'secondary', suspended: 'error',
  confirmed: 'info', assigned: 'primary', inspected: 'secondary', completed: 'success', cancelled: 'error',
}[status] ?? 'secondary')


onMounted(load)
</script>

<template>
  <div>
    <VProgressLinear v-if="loading" indeterminate />

    <template v-else-if="technician">
      <VCard class="mb-6">
        <VCardText class="d-flex flex-wrap align-center gap-x-6 gap-y-4">
          <VAvatar
            rounded
            :size="72"
            :color="avatar ? undefined : 'primary'"
            :variant="avatar ? undefined : 'tonal'"
          >
            <VImg v-if="avatar" :src="avatar" cover />
            <span v-else class="text-2xl font-weight-medium">{{ avatarText(technician.name) }}</span>
          </VAvatar>

          <div>
            <div class="d-flex align-center flex-wrap gap-2">
              <h4 class="text-h4 mb-0">{{ technician.name }}</h4>
              <VChip :color="statusColor(technician.status)" size="small" label>
                {{ technician.status_label }}
              </VChip>
              <VChip
                :color="technician.documents_complete ? 'success' : 'warning'"
                size="small"
                variant="tonal"
                label
              >
                {{ technician.documents_complete ? 'الأوراق مكتملة' : `ناقص ${technician.missing_documents?.length ?? 0}` }}
              </VChip>
              <VChip
                :color="technician.source === 'application' ? 'warning' : 'secondary'"
                size="small"
                variant="tonal"
                label
              >
                {{ technician.source_label }}
              </VChip>
            </div>

            <div class="d-flex flex-wrap gap-x-5 gap-y-1 text-body-2 text-disabled mt-2">
              <span dir="ltr">{{ technician.phone }}</span>
              <span>{{ technician.governorate?.name }} / {{ technician.district?.name }}</span>
              <span>{{ technician.orders_count ?? 0 }} طلب</span>
              <span>أُضيف {{ formatDate(technician.created_at) }}</span>
            </div>
          </div>

          <VSpacer />

          <VBtn variant="tonal" color="secondary" :loading="changingStatus" append-icon="tabler-chevron-down">
            تغيير الحالة
            <VMenu activator="parent">
              <VList density="compact">
                <VListItem
                  v-for="option in statusOptions"
                  :key="option.value"
                  :active="technician.status === option.value"
                  @click="setStatus(option.value)"
                >
                  <VListItemTitle>{{ option.title }}</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </VBtn>
        </VCardText>
      </VCard>

      <VCard title="بيانات الفني" class="mb-6">
        <VCardText>
          <VRow>
            <VCol cols="12" md="4">
              <AppTextField v-model="form.name" label="اسم الفني" :error-messages="errors.name?.[0]" />
            </VCol>

            <VCol cols="12" md="4">
              <AppTextField v-model="form.phone" label="رقم الهاتف" dir="ltr" :error-messages="errors.phone?.[0]" />
            </VCol>

            <VCol cols="12" md="4">
              <AppSelect
                v-model="form.specialization_ids"
                label="الاختصاصات"
                :items="specializations.items.value"
                item-title="name"
                item-value="id"
                multiple
                chips
                closable-chips
                :error-messages="errors.specialization_ids?.[0]"
              />
            </VCol>

            <VCol cols="12" md="4">
              <AppSelect
                v-model="form.governorate_id"
                label="المحافظة"
                :items="governorates.items.value"
                item-title="name"
                item-value="id"
                :error-messages="errors.governorate_id?.[0]"
              />
            </VCol>

            <VCol cols="12" md="4">
              <AppSelect
                v-model="form.district_id"
                label="القضاء"
                :items="districts"
                item-title="name"
                item-value="id"
                :disabled="!form.governorate_id"
                :error-messages="errors.district_id?.[0]"
              />
            </VCol>

            <VCol cols="12" class="d-flex gap-3">
              <VBtn :loading="savingProfile" @click="saveProfile">حفظ التعديلات</VBtn>
              <VBtn color="secondary" variant="tonal" :disabled="savingProfile" @click="load">تراجع</VBtn>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <TechnicianTabDocuments
        class="mb-6"
        :documents="technician.documents"
        :missing="technician.missing_documents"
        :uploading="uploading"
        :errors="errors"
        @upload="uploadSlot"
        @add-samples="addSamples"
        @remove="doc => confirmRemove = doc"
      />

      <AppDataTableServer
        title="طلبات الفني"
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

        <template #item.user="{ item }">
          <div v-if="item.user">
            <div>{{ item.user.name }}</div>
            <div class="text-caption text-disabled" dir="ltr">{{ item.user.phone }}</div>
          </div>
          <span v-else class="text-disabled">—</span>
        </template>

        <template #item.service="{ item }">{{ item.service?.name ?? '—' }}</template>

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

    <VDialog :model-value="confirmRemove !== null" max-width="400" @update:model-value="confirmRemove = null">
      <VCard title="حذف الملف">
        <VCardText>سيتم حذف «{{ confirmRemove?.type_label }}» نهائياً.</VCardText>
        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="confirmRemove = null">إلغاء</VBtn>
          <VBtn color="error" @click="removeMedia">حذف</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
