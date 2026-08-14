<script setup lang="ts">
import { useToast } from '@/composables/useToast'
import type { ApiResponse } from '@/types/api'

interface Doc { id: number; type: string; type_label: string; url: string }

interface Application {
  id: number
  full_name: string
  phone: string
  whatsapp: string | null
  status: string
  status_label: string
  allowed_next_statuses: { value: string; label: string }[]
  note: string | null
  governorate?: { id: number; name: string }
  district?: { id: number; name: string }
  specializations?: { id: number; name: string }[]
  documents?: Record<string, Doc | Doc[] | null>
  reviewed_by?: string | null
  reviewed_at: string | null
  created_at: string | null
}

const route = useRoute('admin-technician-applications-id')
const router = useRouter()
const toast = useToast()

const application = ref<Application | null>(null)
const loading = ref(true)
const acting = ref(false)
const preview = ref<Doc | null>(null)

const rejectDialog = ref(false)
const rejectReason = ref('')
const deleteDialog = ref(false)
const acceptDialog = ref(false)

const load = async () => {
  loading.value = true
  try {
    const res = await $api<ApiResponse<Application>>(`/admin/technician-applications/${route.params.id}`)

    application.value = res.data
  }
  finally {
    loading.value = false
  }
}

const setStatus = async (status: string, note?: string) => {
  acting.value = true
  try {
    const res = await $api<ApiResponse<Application>>(
      `/admin/technician-applications/${route.params.id}/status`,
      { method: 'PATCH', body: { status, note } },
    )

    application.value = res.data
    toast.success(res.message)

    return true
  }
  catch (e: any) {
    const errs = e?.data?.errors

    toast.error(errs ? Object.values(errs).flat()[0] as string : e?.data?.message ?? 'تعذّر تنفيذ العملية')

    return false
  }
  finally {
    acting.value = false
  }
}

const accept = async () => {
  acting.value = true
  try {
    const res = await $api<ApiResponse<{ technician: { id: number; name: string } }>>(
      `/admin/technician-applications/${route.params.id}/accept`,
      { method: 'POST' },
    )

    toast.success(res.message)
    // The application is gone from here on — its data lives in the technician.
    await router.replace({ name: 'admin-technicians-id', params: { id: res.data.technician.id } })
  }
  catch (e: any) {
    const errs = e?.data?.errors

    toast.error(errs ? Object.values(errs).flat()[0] as string : e?.data?.message ?? 'تعذّر القبول')
  }
  finally {
    acting.value = false
    acceptDialog.value = false
  }
}

const confirmReject = async () => {
  if (await setStatus('rejected', rejectReason.value)) {
    rejectDialog.value = false
    rejectReason.value = ''
  }
}

const remove = async () => {
  acting.value = true
  try {
    const res = await $api<{ message: string }>(`/admin/technician-applications/${route.params.id}`, { method: 'DELETE' })

    toast.success(res.message)
    await router.replace({ name: 'admin-technician-applications' })
  }
  catch (e: any) {
    const errs = e?.data?.errors

    toast.error(errs ? Object.values(errs).flat()[0] as string : e?.data?.message ?? 'تعذّر الحذف')
  }
  finally {
    acting.value = false
    deleteDialog.value = false
  }
}

const can = (status: string) =>
  application.value?.allowed_next_statuses.some(s => s.value === status) ?? false

/** The five required cards, then the work samples, in one flat list to render. */
const documentCards = computed(() => {
  const docs = application.value?.documents
  if (!docs)
    return []

  return Object.entries(docs)
    .filter(([key]) => key !== 'work_sample')
    .map(([key, value]) => ({ key, doc: value as Doc | null }))
})

const workSamples = computed(() => (application.value?.documents?.work_sample as Doc[] | undefined) ?? [])

const details = computed(() => {
  const record = application.value
  if (!record)
    return []

  return [
    { icon: 'tabler-phone', label: 'رقم الهاتف', value: record.phone, ltr: true },
    { icon: 'tabler-map-2', label: 'المحافظة', value: record.governorate?.name ?? '—' },
    { icon: 'tabler-map-pin', label: 'القضاء', value: record.district?.name ?? '—' },
    { icon: 'tabler-calendar-plus', label: 'تاريخ التقديم', value: formatDate(record.created_at) },
    { icon: 'tabler-user-check', label: 'راجعها', value: record.reviewed_by ?? '—' },
    { icon: 'tabler-clock-check', label: 'وقت المراجعة', value: formatDateTime(record.reviewed_at) },
  ]
})

const statusColor = (status: string) => ({
  pending: 'warning', under_review: 'info', accepted: 'success', rejected: 'error',
}[status] ?? 'secondary')

onMounted(load)
</script>

<template>
  <div>
    <VProgressLinear v-if="loading" indeterminate />

    <template v-else-if="application">
      <VCard class="mb-6">
        <VCardText class="d-flex flex-wrap align-center gap-x-6 gap-y-4">
          <VAvatar rounded :size="72" color="primary" variant="tonal">
            <VImg v-if="(application.documents?.personal as Doc)?.url" :src="(application.documents?.personal as Doc).url" cover />
            <span v-else class="text-2xl font-weight-medium">{{ avatarText(application.full_name) }}</span>
          </VAvatar>

          <div>
            <div class="d-flex align-center flex-wrap gap-2">
              <h4 class="text-h4 mb-0">{{ application.full_name }}</h4>
              <VChip :color="statusColor(application.status)" size="small" label>
                {{ application.status_label }}
              </VChip>
            </div>

            <div class="d-flex flex-wrap gap-x-5 gap-y-1 text-body-2 text-disabled mt-2">
              <span dir="ltr">{{ application.phone }}</span>
              <span>{{ application.governorate?.name }} / {{ application.district?.name }}</span>
              <span>قُدّمت {{ formatDate(application.created_at) }}</span>
            </div>

            <div class="d-flex flex-wrap gap-1 mt-2">
              <VChip
                v-for="s in application.specializations ?? []"
                :key="s.id"
                size="small"
                variant="tonal"
                label
              >
                {{ s.name }}
              </VChip>
            </div>
          </div>

          <VSpacer />

          <div class="d-flex flex-wrap gap-2">
            <VBtn
              v-if="application.whatsapp"
              :href="application.whatsapp"
              target="_blank"
              color="success"
              variant="tonal"
              prepend-icon="tabler-brand-whatsapp"
            >
              مراسلة المتقدّم
            </VBtn>

            <VBtn
              v-if="can('under_review')"
              :loading="acting"
              color="info"
              variant="tonal"
              prepend-icon="tabler-file-search"
              @click="setStatus('under_review')"
            >
              قيد المراجعة
            </VBtn>

            <VBtn
              :loading="acting"
              color="success"
              prepend-icon="tabler-user-check"
              @click="acceptDialog = true"
            >
              قبول
            </VBtn>

            <VBtn
              v-if="can('rejected')"
              :loading="acting"
              color="error"
              variant="tonal"
              prepend-icon="tabler-user-x"
              @click="rejectDialog = true"
            >
              رفض
            </VBtn>

            <VBtn
              :loading="acting"
              color="error"
              variant="text"
              icon
              @click="deleteDialog = true"
            >
              <VIcon icon="tabler-trash" />
              <VTooltip activator="parent" location="top">حذف الاستمارة</VTooltip>
            </VBtn>
          </div>
        </VCardText>
      </VCard>

      <VAlert
        v-if="application.note"
        :type="application.status === 'rejected' ? 'error' : 'info'"
        variant="tonal"
        class="mb-6"
      >
        <div class="text-body-2">{{ application.note }}</div>
      </VAlert>

      <VCard title="بيانات المتقدّم" class="mb-6">
        <VCardText>
          <VRow>
            <VCol v-for="row in details" :key="row.label" cols="12" sm="6" md="4">
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

      <VCard title="الأوراق الرسمية" class="mb-6">
        <VCardText>
          <VRow>
            <VCol
              v-for="card in documentCards"
              :key="card.key"
              cols="12"
              sm="6"
              md="4"
              lg="3"
            >
              <div class="text-caption text-disabled mb-1">{{ card.doc?.type_label }}</div>
              <VCard
                variant="outlined"
                class="document-tile"
                @click="card.doc && (preview = card.doc)"
              >
                <VImg v-if="card.doc" :src="card.doc.url" height="150" cover />
                <div v-else class="d-flex align-center justify-center text-disabled" style="block-size: 150px;">
                  <VIcon icon="tabler-photo-off" size="28" />
                </div>
              </VCard>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <VCard :title="`نماذج الأعمال (${workSamples.length})`">
        <VCardText>
          <VRow v-if="workSamples.length">
            <VCol
              v-for="sample in workSamples"
              :key="sample.id"
              cols="12"
              sm="6"
              md="4"
              lg="3"
            >
              <VCard variant="outlined" class="document-tile" @click="preview = sample">
                <VImg :src="sample.url" height="150" cover />
              </VCard>
            </VCol>
          </VRow>

          <div v-else class="text-body-2 text-disabled">لم يرفق المتقدّم نماذج أعمال</div>
        </VCardText>
      </VCard>
    </template>

    <VDialog :model-value="preview !== null" max-width="900" @update:model-value="preview = null">
      <VCard :title="preview?.type_label">
        <VCardText>
          <VImg :src="preview?.url" max-height="70vh" contain />
        </VCardText>
        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="preview = null">إغلاق</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="acceptDialog" max-width="480">
      <VCard title="قبول الاستمارة">
        <VCardText>
          سيتحوّل <strong>{{ application?.full_name }}</strong> إلى فني بحالة «قيد الانتظار» مع كل صوره،
          وتُحذف الاستمارة من هذه الصفحة نهائياً.
        </VCardText>
        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="acceptDialog = false">تراجع</VBtn>
          <VBtn color="success" :loading="acting" @click="accept">قبول ونقل للفنيين</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="rejectDialog" max-width="480">
      <VCard title="رفض الاستمارة">
        <VCardText>
          <AppTextarea v-model="rejectReason" label="سبب الرفض" rows="3" placeholder="اكتب سبباً واضحاً" />
        </VCardText>
        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="rejectDialog = false">تراجع</VBtn>
          <VBtn color="error" :disabled="!rejectReason.trim()" :loading="acting" @click="confirmReject">رفض</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="deleteDialog" max-width="440">
      <VCard title="حذف الاستمارة">
        <VCardText>
          سيتم حذف الاستمارة وكل صورها نهائياً، ويتحرّر رقم الهاتف لتقديم جديد.
        </VCardText>
        <VCardActions class="px-6 pb-4">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="deleteDialog = false">إلغاء</VBtn>
          <VBtn color="error" :loading="acting" @click="remove">حذف</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style lang="scss" scoped>
.document-tile {
  cursor: pointer;
  transition: border-color 0.15s ease;

  &:hover {
    border-color: rgb(var(--v-theme-primary));
  }
}
</style>
